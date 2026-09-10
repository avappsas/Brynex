<?php

namespace App\Services;

use App\Models\OperadorPlanillaApi;

/**
 * PlanillaDosPasosService — reglas del flujo de dos pasos de las modalidades
 * "Solo Caja" y "Solo Pensión". Ver PilaCotizanteDosPasos para el cálculo de
 * cada fila.
 *
 * El pago son dos liquidaciones encadenadas contra la API del operador:
 *
 *   paso 1 → planilla E con un día de pensión y la caja en cero. Se paga.
 *   paso 2 → planilla N que corrige la anterior y anexa lo que se vendió.
 *
 * ## La fecha de pago la da el operador
 *
 * El paso 2 necesita dos datos de la planilla del paso 1: su número y **la
 * fecha en que se pagó** (campos 9 y 10 del registro tipo 1). La API nunca
 * devuelve esa fecha, y por eso la E-1 depende de que alguien confirme el pago
 * a mano en BryNex —ver PlanillaE1Service—.
 *
 * Aquí no: el propio validador la delata. Se manda la corrección con una fecha
 * tentativa y el operador responde una de dos cosas (probado el 9-sep-2026 en
 * ARUS Enlace):
 *
 *   `eo.val.1.043` → "debe existir una planilla con código X pagada en la
 *                     fecha AAAA-MM-DD". Está pagada y **esa es la fecha**:
 *                     se rearma el archivo con ella y se reintenta.
 *   `eo.val.1.171` → "el número de planilla registrado no registra en nuestra
 *                     base de datos". Todavía no está pagada; no hay nada que
 *                     corregir y hay que esperar el pago.
 *
 * Así el paso 2 es un botón que funciona solo, sin depender de que el pago se
 * haya registrado en BryNex.
 */
class PlanillaDosPasosService
{
    /** El operador delata la fecha de pago de la planilla que se corrige. */
    private const REGLA_FECHA_PAGO = 'eo.val.1.043';

    /** La planilla que se quiere corregir no aparece entre las pagadas. */
    private const REGLAS_SIN_PAGAR = ['eo.val.1.171', 'eo.val.1.142'];

    /**
     * ¿Este lote se paga en dos planillas?
     *
     * Se exige que TODAS las modalidades del filtro sean de dos pasos —las de
     * caja y las de pensión caben juntas en la misma tanda, porque su paso 1 es
     * el mismo archivo—, igual que en la E-1: mezclarlas con otras dejaría sin
     * salud, en el paso 1, a gente que no está en este esquema, y el paso 2
     * solo corrige a los que van en la planilla asociada.
     */
    public static function aplica(array $tiposModalidad): bool
    {
        if ($tiposModalidad === []) {
            return false;
        }

        foreach ($tiposModalidad as $tipo) {
            if (! in_array((int) $tipo, \App\Models\TipoModalidad::IDS_DOS_PASOS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ¿Esta tanda mezcla extraordinarias con gente normal?
     *
     * Mezclarlas no es un detalle estético: en el paso 1 estas modalidades van
     * sin salud, y su corrección se busca después por el **mismo filtro de
     * modalidades** con el que se liquidó (`contextoCorreccion`). Si el paso 1
     * salió sin filtro —con toda la tanda dentro—, el paso 2 no encuentra su
     * planilla y la única salida es volver a liquidar, que duplica la planilla
     * en el operador y cuesta dinero de verdad.
     *
     * Por eso el archivo se arma con estas modalidades solas, y esta consulta
     * es la que deja avisarlo antes de gastar.
     *
     * @param  array  $tiposFiltro  Modalidades marcadas en pantalla; vacío = todas
     */
    public static function tandaMezclada(
        int $aliadoId,
        int $razonSocialId,
        int $mes,
        int $anio,
        int $nPlano,
        array $tiposFiltro
    ): bool {
        // El alias `p` no es capricho: Plano::filtrarPeriodoDePago escribe sus
        // condiciones con ese prefijo, igual que en PlanoPilaTxtService.
        $query = \Illuminate\Support\Facades\DB::table('planos AS p')
            ->where('p.aliado_id', $aliadoId)
            ->where('p.razon_social_id', $razonSocialId)
            ->where('p.n_plano', $nPlano)
            ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
            ->whereRaw('ISNULL(p.num_dias, 0) > 0')
            ->whereNull('p.deleted_at')
            ->tap(fn ($q) => \App\Models\Plano::filtrarPeriodoDePago($q, $mes, $anio));

        if ($tiposFiltro !== []) {
            $query->whereIn('p.tipo_modalidad_id', array_map('intval', $tiposFiltro));
        }

        $modalidades = $query->distinct()
            ->pluck('p.tipo_modalidad_id')
            ->map(fn ($id) => (int) $id);

        $dosPasos = \App\Models\TipoModalidad::IDS_DOS_PASOS;

        return $modalidades->contains(fn ($id) => in_array($id, $dosPasos, true))
            && $modalidades->contains(fn ($id) => ! in_array($id, $dosPasos, true));
    }

    /**
     * Cómo se llama el botón del paso 2, que es lo único que la pantalla
     * necesita saber de la diferencia entre las dos familias.
     */
    public static function etiquetaCorreccion(array $tiposModalidad): string
    {
        foreach ($tiposModalidad as $tipo) {
            if (in_array((int) $tipo, \App\Models\TipoModalidad::IDS_SOLO_PENSION, true)) {
                return 'Corrección (mes de pensión)';
            }
        }

        return 'Corrección (caja del mes)';
    }

    /**
     * Todo lo que el paso 2 necesita para poder salir, o el motivo por el que
     * todavía no puede.
     *
     * A diferencia de la E-1, no exige que el pago esté confirmado en BryNex:
     * la fecha arranca como una tentativa —la del gasto si existe, y si no la
     * de hoy— y la corrige el operador en el primer intento.
     *
     * @param  array  $llave  La misma llave del updateOrCreate del paso 1, sin el paso.
     * @return array{ok: bool, message?: string, planilla_asociada?: array{numero: string, fecha_pago: string}}
     */
    public static function contextoCorreccion(array $llave): array
    {
        $paso1 = OperadorPlanillaApi::where($llave)
            ->where('paso', 1)
            ->where('estado', 'validada')
            ->latest('id')
            ->first();

        if (! $paso1 || empty($paso1->numero_planilla)) {
            return [
                'ok' => false,
                'message' => 'Todavía no hay una primera planilla liquidada para esta tanda. '
                    .'Liquide el paso 1 antes de la corrección.',
            ];
        }

        $pago = PlanillaE1Service::pagoConfirmado(
            (int) $llave['aliado_id'],
            (string) $paso1->numero_planilla
        );

        return [
            'ok' => true,
            'planilla_asociada' => [
                'numero' => (string) $paso1->numero_planilla,
                'fecha_pago' => $pago ? $pago->fecha->format('Y-m-d') : now()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * La fecha de pago real que el operador reveló al rechazar la corrección,
     * si la reveló y es distinta de la que se mandó.
     *
     * @param  array  $errores  `erroresEmpresaPlanilla` de la validación
     */
    public static function fechaPagoDelError(array $errores, string $fechaEnviada): ?string
    {
        foreach ($errores as $error) {
            $error = (array) $error;

            if (($error['idRegla'] ?? '') !== self::REGLA_FECHA_PAGO) {
                continue;
            }

            if (! preg_match('/pagada en la fecha\s+(\d{4}-\d{2}-\d{2})/i', (string) ($error['descripcion'] ?? ''), $m)) {
                continue;
            }

            return $m[1] !== $fechaEnviada ? $m[1] : null;
        }

        return null;
    }

    /**
     * ¿El rechazo dice que la planilla del paso 1 todavía no está pagada?
     *
     * Es un caso que hay que contar distinto: no es un archivo mal armado, es
     * que falta el pago y no hay nada que hacer hasta que entre.
     *
     * @param  array  $errores  `erroresEmpresaPlanilla` de la validación
     */
    public static function faltaElPago(array $errores): bool
    {
        foreach ($errores as $error) {
            $error = (array) $error;
            if (in_array($error['idRegla'] ?? '', self::REGLAS_SIN_PAGAR, true)) {
                return true;
            }
        }

        return false;
    }
}
