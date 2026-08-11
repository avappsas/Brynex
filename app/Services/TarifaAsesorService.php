<?php

namespace App\Services;

use App\Models\AfiliacionArlModalidad;
use App\Models\Asesor;
use App\Models\AsesorNivel;
use App\Models\AsesorNivelTarifa;
use App\Models\AsesorTarifa;
use App\Models\ConfiguracionAliado;
use App\Models\Contrato;
use App\Models\TipoModalidad;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve el tarifario de una afiliación: cuánto cuesta, cómo se reparte y cuánto le queda
 * al asesor, para una combinación (aliado, plan, modalidad, nivel de riesgo ARL).
 *
 * Ver docs/plan-tarifario-asesores.md. Reglas que NO se deben reinventar en los llamadores:
 *
 *   asesor = valor de su matriz    (celda del asesor → celda de su nivel → comisión plana)
 *   retiro = valor del PLAN        (celda de Parámetros → % dist_retiro_pct de respaldo)
 *   otros  = valor del PLAN        (celda de Parámetros → 0)
 *   aliado = público − retiro − otros − asesor          (mínimo 0)
 *
 * El retiro y los "otros" los manda siempre el plan, nunca el asesor. Y lo que se escribe a
 * mano en la matriz es la parte del ASESOR: si mañana sube el precio público, el asesor sigue
 * ganando lo mismo y la diferencia se la queda el aliado.
 *
 * OJO con la latencia: cada consulta a este SQL Server cuesta ~250ms de red. Todo lo que se
 * lee por celda está cacheado por petición en los arrays estáticos de abajo — nunca meter una
 * consulta dentro de un bucle sobre celdas.
 */
class TarifaAsesorService
{
    /** Modalidades donde se cobra afiliación cada vez, pero la renovación le paga admon al asesor. */
    public const MODALIDAD_INGRESO_RETIRO = 12;

    public const MODALIDAD_GESTION_ARL = 15;

    public const MODALIDADES_RENOVABLES = [
        self::MODALIDAD_INGRESO_RETIRO,
        self::MODALIDAD_GESTION_ARL,
    ];

    /**
     * Modalidades que comparten tarifa con otra: la clave se cobra igual que el valor.
     *
     * Independientes Mes Actual (11) vale lo mismo que Independientes (10) — se diferencian en
     * cómo se factura el primer mes (proporcional vs afiliación pura, ver CobroContratoService),
     * no en el precio. Así se configura una sola vez y no hay dos celdas que se puedan
     * desincronizar. Solo afecta al TARIFARIO: la facturación sigue distinguiéndolas.
     */
    public const MODALIDADES_ALIAS = [11 => 10];

    /** Modalidad bajo la que se guarda y se busca la tarifa de esta modalidad. */
    public static function modalidadTarifa(int $modalidadId): int
    {
        return self::MODALIDADES_ALIAS[$modalidadId] ?? $modalidadId;
    }

    /**
     * Modalidades que se muestran juntas en una sola tarjeta del tarifario.
     *
     * Estudiante K, ARL Tipo Y y Gestión ARL son las tres variantes de vender "Solo ARL": K y Y
     * son la misma planilla partida por nivel de riesgo (1-3 y 4-5), y Gestión ARL es la
     * afiliación sin planilla. Se agrupan para no tener tres tarjetas sueltas, pero **cada una
     * conserva sus propias casillas**: Gestión ARL no cuesta lo mismo (va con descuento).
     *
     * Es solo presentación: no cambia ids, ni nombres en BD, ni la facturación.
     */
    public const GRUPOS_TARIFARIO = [
        'solo_arl' => [
            'nombre' => 'Solo ARL',
            'modalidades' => [-1, 8, 15],
        ],
    ];

    /** Clave del grupo al que pertenece la modalidad, o null si va suelta. */
    public static function grupoDeModalidad(int $modalidadId): ?string
    {
        foreach (self::GRUPOS_TARIFARIO as $clave => $grupo) {
            if (in_array($modalidadId, $grupo['modalidades'], true)) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * Niveles de riesgo ARL que aplican a una modalidad. Sin entrada = todos los del plan.
     *
     * Estudiante K y ARL Tipo Y son los dos tipos de planilla PILA para pagar ARL sin salud ni
     * pensión, y se reparten los riesgos: la K cubre 1-3 y la Y es su contraparte para alto
     * riesgo (4-5). Pedir precio para "K riesgo 5" es pedir algo que no se puede vender.
     */
    public const NIVELES_ARL_POR_MODALIDAD = [
        -1 => [1, 2, 3],  // Estudiante K
        8 => [4, 5],      // ARL Tipo Y
    ];

    /**
     * Niveles de riesgo a tarifar para un plan y una modalidad: los 5 si el plan lleva ARL
     * (o solo [1] si no), recortados por la restricción de la modalidad.
     *
     * @return array<int, int>
     */
    public static function nivelesArlPara(\App\Models\PlanContrato $plan, int $modalidadId): array
    {
        $niveles = $plan->incluye_arl ? [1, 2, 3, 4, 5] : [1];

        if (isset(self::NIVELES_ARL_POR_MODALIDAD[$modalidadId])) {
            $niveles = array_values(array_intersect($niveles, self::NIVELES_ARL_POR_MODALIDAD[$modalidadId]));
        }

        return $niveles;
    }

    /** @var array<int, \Illuminate\Support\Collection<string, AfiliacionArlModalidad>> */
    private static array $cacheCeldas = [];

    /** @var array<int, \Illuminate\Support\Collection<string, AsesorNivelTarifa>> */
    private static array $cacheNivel = [];

    /** @var array<int, \Illuminate\Support\Collection<string, AsesorTarifa>> */
    private static array $cacheAsesor = [];

    /** Limpia los cachés de petición. Necesario en comandos largos y en tests. */
    public static function limpiarCache(): void
    {
        self::$cacheCeldas = [];
        self::$cacheNivel = [];
        self::$cacheAsesor = [];
    }

    // ─────────────────────────────────────────────────────────────────
    // Celdas de Parámetros (afiliacion_arl_modalidad)
    // ─────────────────────────────────────────────────────────────────

    /** Todas las celdas del aliado indexadas por "plan_modalidad_nivel". Una sola consulta. */
    public static function celdasDelAliado(int $alidoId): \Illuminate\Support\Collection
    {
        if (! isset(self::$cacheCeldas[$alidoId])) {
            self::$cacheCeldas[$alidoId] = AfiliacionArlModalidad::where('aliado_id', $alidoId)
                ->get()
                ->keyBy(fn ($c) => AsesorNivelTarifa::claveCelda(
                    (int) $c->plan_id,
                    (int) $c->tipo_modalidad_id,
                    (int) $c->nivel_arl
                ));
        }

        return self::$cacheCeldas[$alidoId];
    }

    public static function celda(int $alidoId, int $planId, int $modalidadId, int $nivelArl): ?AfiliacionArlModalidad
    {
        return self::celdasDelAliado($alidoId)
            ->get(AsesorNivelTarifa::claveCelda($planId, self::modalidadTarifa($modalidadId), $nivelArl));
    }

    /**
     * Precio de afiliación al cliente. Celda → costo_afiliacion del plan → del global.
     * Ignora promociones a propósito: el tarifario del asesor es la lista de precios normal,
     * y lo que realmente se cobra viaja en contratos.costo_afiliacion.
     */
    public static function resolverPrecioPublico(int $alidoId, int $planId, int $modalidadId, int $nivelArl): float
    {
        $celda = self::celda($alidoId, $planId, $modalidadId, $nivelArl);
        if ($celda && $celda->costo_afiliacion !== null) {
            return (float) $celda->costo_afiliacion;
        }

        return (float) (ConfiguracionAliado::paraAliado($alidoId, $planId)?->costo_afiliacion ?? 0);
    }

    /**
     * Admon mensual TOTAL al cliente. Celda → administracion de la config GENÉRICA del aliado.
     *
     * El respaldo salta a propósito la fila por plan: en los datos reales esas filas traen
     * administracion = 0 y solo la fila global tiene el valor (46.000 en el aliado 2), que es
     * justo lo que cobra el cotizador — ver la nota de CotizacionPublicaService::cotizar():
     * "administracion/admon_asesor/seguro SIEMPRE de la config genérica, nunca de la fila por
     * plan". Cascadear por plan devolvería 0 en todos los planes.
     *
     * El costo de afiliación es al revés (ahí la fila por plan sí manda), por eso
     * resolverPrecioPublico() sí usa paraAliado($alidoId, $planId).
     */
    public static function resolverAdmonTotal(int $alidoId, int $planId, int $modalidadId, int $nivelArl): float
    {
        $celda = self::celda($alidoId, $planId, $modalidadId, $nivelArl);
        if ($celda && $celda->administracion !== null) {
            return (float) $celda->administracion;
        }

        return (float) (ConfiguracionAliado::paraAliado($alidoId)?->administracion ?? 0);
    }

    /** Retiro en pesos. Celda → porcentaje dist_retiro_pct sobre el precio público. */
    public static function resolverRetiro(int $alidoId, int $planId, int $modalidadId, int $nivelArl, ?float $publico = null): float
    {
        $celda = self::celda($alidoId, $planId, $modalidadId, $nivelArl);
        if ($celda && $celda->retiro !== null) {
            return (float) $celda->retiro;
        }

        $publico ??= self::resolverPrecioPublico($alidoId, $planId, $modalidadId, $nivelArl);
        $pct = (float) (ConfiguracionAliado::paraAliado($alidoId, $planId)?->dist_retiro_pct ?? 0);

        return round($publico * $pct / 100);
    }

    /** En tiempo parcial no se puede cotizar menos de un bloque de 7 días. */
    public const DIAS_MINIMOS_TIEMPO_PARCIAL = 7;

    /**
     * Lo que de verdad cuesta un retiro, con el mismo cotizador que usa la planilla.
     *
     *   • Normal (dependientes, independientes, ARL Tipo Y, Estudiante K, Gestión ARL):
     *     UN día. Se prorratea con ceil igual que la planilla.
     *
     *   • Tiempo Parcial: el bloque mínimo de 7 días, no un día. La planilla de tiempo
     *     parcial no admite fracciones más pequeñas, así que un TP14 que se retira paga lo
     *     mismo que un TP7 de su plan. La ARL va completa porque en tiempo parcial siempre
     *     cotiza el mes entero, pase lo que pase con los días de pensión y caja.
     *     (Antes esto devolvía el MES COMPLETO: calcularCotizacion() ignora $dias en la rama
     *     de tiempo parcial, así que pedirle 1 día daba lo mismo que pedirle 30.)
     *
     * Es el respaldo de resolverRetiro() al facturar una afiliación: si Parámetros no tiene
     * retiro para la celda, se calcula en vez de repartir 0.
     */
    public static function retiroCalculado(Contrato $contrato): int
    {
        $mod = $contrato->tipoModalidad;

        if ($mod && $mod->esTiempoParcial()) {
            // Se cotiza el mismo contrato como si fuera el bloque de 7 días de su plan.
            $contrato = clone $contrato;
            $mod = clone $mod;
            $mod->dias_afp = self::DIAS_MINIMOS_TIEMPO_PARCIAL;
            $mod->dias_caja = self::DIAS_MINIMOS_TIEMPO_PARCIAL;
            $contrato->setRelation('tipoModalidad', $mod);
        }

        $c = $contrato->calcularCotizacion(1);

        return (int) (($c['eps'] ?? 0) + ($c['arl'] ?? 0) + ($c['pen'] ?? 0) + ($c['caja'] ?? 0));
    }

    /** Bolsa "otros" del aliado dentro de la afiliación. Celda → 0. */
    public static function resolverOtros(int $alidoId, int $planId, int $modalidadId, int $nivelArl): float
    {
        $celda = self::celda($alidoId, $planId, $modalidadId, $nivelArl);

        return (float) ($celda?->otros ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // Matriz del asesor
    // ─────────────────────────────────────────────────────────────────

    private static function tarifasDelAsesor(int $asesorId): \Illuminate\Support\Collection
    {
        if (! isset(self::$cacheAsesor[$asesorId])) {
            self::$cacheAsesor[$asesorId] = AsesorTarifa::where('asesor_id', $asesorId)
                ->get()
                ->keyBy(fn ($t) => AsesorNivelTarifa::claveCelda(
                    (int) $t->plan_id,
                    (int) $t->tipo_modalidad_id,
                    (int) $t->nivel_arl
                ));
        }

        return self::$cacheAsesor[$asesorId];
    }

    private static function tarifasDelNivel(int $nivelId): \Illuminate\Support\Collection
    {
        if (! isset(self::$cacheNivel[$nivelId])) {
            self::$cacheNivel[$nivelId] = AsesorNivelTarifa::where('asesor_nivel_id', $nivelId)
                ->get()
                ->keyBy(fn ($t) => AsesorNivelTarifa::claveCelda(
                    (int) $t->plan_id,
                    (int) $t->tipo_modalidad_id,
                    (int) $t->nivel_arl
                ));
        }

        return self::$cacheNivel[$nivelId];
    }

    /**
     * Lo que gana el asesor por la afiliación, con la cascada completa:
     *   celda propia → celda de su nivel → comisión plana de siempre → null (sin asesor).
     *
     * Devuelve null cuando no hay asesor: el llamador debe dejar el contrato con
     * afiliacion_asesor NULL, que es lo que mantiene a los contratos viejos en la lógica vieja.
     *
     * @return array{valor: float, origen: string}|null origen: asesor|nivel|plano
     */
    public static function resolverAfiliacionAsesor(
        ?Asesor $asesor,
        int $planId,
        int $modalidadId,
        int $nivelArl,
        ?float $publico = null
    ): ?array {
        if (! $asesor) {
            return null;
        }

        $clave = AsesorNivelTarifa::claveCelda($planId, self::modalidadTarifa($modalidadId), $nivelArl);

        // 1. Celda propia del asesor
        $propia = self::tarifasDelAsesor((int) $asesor->id)->get($clave);
        if ($propia) {
            return ['valor' => (float) $propia->afil_asesor, 'origen' => 'asesor'];
        }

        // 2. Celda de la plantilla de su nivel
        if ($asesor->nivel_id) {
            $delNivel = self::tarifasDelNivel((int) $asesor->nivel_id)->get($clave);
            if ($delNivel) {
                return ['valor' => (float) $delNivel->afil_asesor, 'origen' => 'nivel'];
            }
        }

        // 3. Comisión plana de siempre — es lo que mantiene funcionando a los asesores
        //    que todavía no tienen nivel asignado.
        $publico ??= self::resolverPrecioPublico(
            (int) $asesor->aliado_id, $planId, $modalidadId, $nivelArl
        );

        return [
            'valor' => $asesor->calcularComisionAfiliacion($publico),
            'origen' => 'plano',
        ];
    }

    /**
     * Admon mensual del asesor. Se guarda en el propio asesor (comision_admon_*), porque al
     * asignarle el nivel se le copia allí el valor único del nivel. Cascada:
     *   comisión del asesor → admon del nivel → 0.
     */
    public static function resolverAdmonAsesor(?Asesor $asesor, float $admonTotal = 0): float
    {
        if (! $asesor) {
            return 0.0;
        }

        $valor = $asesor->calcularComisionAdmon($admonTotal);
        if ($valor > 0) {
            return $valor;
        }

        return (float) ($asesor->nivel?->admon_asesor ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────
    // Desglose completo — lo usan el PDF, la pantalla de niveles y el contrato
    // ─────────────────────────────────────────────────────────────────

    /**
     * Desglose completo de una celda. Si se pasa $publicoCobrado (lo que realmente se le cobró
     * al cliente en el contrato), el sobrante o faltante lo absorbe el aliado — que es
     * justamente la regla "gana = precio cobrado − costo del asesor".
     *
     * @return array{
     *   publico: float, retiro: float, otros: float, asesor: float, aliado: float,
     *   admon_total: float, admon_asesor: float, admon_aliado: float,
     *   origen_asesor: string, descuadrada: bool
     * }
     */
    public static function desglose(
        int $alidoId,
        ?Asesor $asesor,
        int $planId,
        int $modalidadId,
        int $nivelArl,
        ?float $publicoCobrado = null
    ): array {
        $publicoLista = self::resolverPrecioPublico($alidoId, $planId, $modalidadId, $nivelArl);
        $publico = $publicoCobrado ?? $publicoLista;

        // El retiro por porcentaje se calcula sobre el precio de lista, no sobre lo cobrado:
        // así un descuento puntual al cliente no le baja el retiro a la empresa.
        $retiro = self::resolverRetiro($alidoId, $planId, $modalidadId, $nivelArl, $publicoLista);
        $otros = self::resolverOtros($alidoId, $planId, $modalidadId, $nivelArl);

        $resAsesor = self::resolverAfiliacionAsesor($asesor, $planId, $modalidadId, $nivelArl, $publicoLista);
        $afilAsesor = (float) ($resAsesor['valor'] ?? 0);

        $aliado = $publico - $retiro - $otros - $afilAsesor;
        $descuadrada = $aliado < 0;

        $admonTotal = self::resolverAdmonTotal($alidoId, $planId, $modalidadId, $nivelArl);
        $admonAsesor = self::resolverAdmonAsesor($asesor, $admonTotal);

        // Solo se recorta la parte del asesor si hay un total configurado. Con el total en 0
        // (admon sin configurar) recortar borraría una comisión que sí existe.
        if ($admonTotal > 0) {
            $admonAsesor = min($admonAsesor, $admonTotal);
        }

        return [
            'publico' => round($publico),
            'retiro' => round($retiro),
            'otros' => round($otros),
            'asesor' => round($afilAsesor),
            'aliado' => round(max(0, $aliado)),
            'admon_total' => round($admonTotal),
            'admon_asesor' => round($admonAsesor),
            'admon_aliado' => round(max(0, $admonTotal - $admonAsesor)),
            'origen_asesor' => $resAsesor['origen'] ?? 'ninguno',
            'descuadrada' => $descuadrada,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Renovaciones (Ingreso-Retiro y Gestión ARL)
    // ─────────────────────────────────────────────────────────────────

    /**
     * ¿Esta afiliación es en realidad una renovación?
     *
     * Solo aplica a Ingreso-Retiro (12) y Gestión ARL (15): ahí se cobra afiliación cada vez,
     * pero al asesor se le paga admon a partir de la segunda. Se detecta AL FACTURAR y no al
     * crear el contrato, porque Gestión ARL renueva sobre el MISMO contrato cada año (solo
     * cambia fecha_arl) — congelarlo al crear haría que toda renovación pagara afiliación.
     *
     * Criterio: cualquier factura de afiliación no anulada del mismo cliente en esa modalidad,
     * en un periodo ANTERIOR al que se está facturando. Sin ventana de meses: quien afilia
     * cada dos meses sigue siendo cliente conocido.
     */
    public static function esRenovacion(
        int $alidoId,
        int|string $cedula,
        int $modalidadId,
        ?int $mes = null,
        ?int $anio = null
    ): bool {
        if (! in_array($modalidadId, self::MODALIDADES_RENOVABLES, true)) {
            return false;
        }

        $q = DB::table('facturas as f')
            ->join('contratos as c', 'c.id', '=', 'f.contrato_id')
            ->where('f.aliado_id', $alidoId)
            ->whereNull('f.deleted_at')
            ->where('f.cedula', $cedula)
            ->where('c.tipo_modalidad_id', $modalidadId)
            ->where('f.tipo', 'afiliacion')
            ->whereIn('f.estado', ['pagada', 'abono', 'prestamo']);

        // Solo periodos estrictamente anteriores al que se factura, para que re-facturar el
        // mes en curso no se convierta a sí mismo en "renovación".
        if ($mes !== null && $anio !== null) {
            $periodo = $anio * 100 + $mes;
            $q->whereRaw('(f.anio * 100 + f.mes) < ?', [$periodo]);
        }

        return $q->exists();
    }

    // ─────────────────────────────────────────────────────────────────
    // Niveles
    // ─────────────────────────────────────────────────────────────────

    /** Nivel que le correspondería por cartera. Solo sugiere: nada se reasigna solo. */
    public static function sugerirNivel(Asesor $asesor): ?AsesorNivel
    {
        $vigentes = $asesor->contratosVigentes();

        return AsesorNivel::delAliado((int) $asesor->aliado_id)
            ->activos()
            ->orderBy('orden')
            ->get()
            ->first(fn (AsesorNivel $n) => $n->cubreCantidad($vigentes));
    }

    /**
     * Celdas de niveles que quedaron imposibles (retiro + otros + asesor > público), típicamente
     * porque después de llenar los niveles se bajó un precio en Parámetros. No bloquea nada:
     * alimenta el aviso al guardar Parámetros y el badge de la matriz.
     *
     * @return array<int, array{nivel: string, plan_id: int, tipo_modalidad_id: int, nivel_arl: int, publico: float, exceso: float}>
     */
    public static function celdasDescuadradas(int $alidoId): array
    {
        $niveles = AsesorNivel::delAliado($alidoId)->activos()->orderBy('orden')->get();
        if ($niveles->isEmpty()) {
            return [];
        }

        $tarifas = AsesorNivelTarifa::whereIn('asesor_nivel_id', $niveles->pluck('id'))->get();
        $fuera = [];

        foreach ($tarifas as $t) {
            $planId = (int) $t->plan_id;
            $modalidadId = (int) $t->tipo_modalidad_id;
            $nivelArl = (int) $t->nivel_arl;

            $publico = self::resolverPrecioPublico($alidoId, $planId, $modalidadId, $nivelArl);
            $retiro = self::resolverRetiro($alidoId, $planId, $modalidadId, $nivelArl, $publico);
            $otros = self::resolverOtros($alidoId, $planId, $modalidadId, $nivelArl);

            $exceso = ($retiro + $otros + (float) $t->afil_asesor) - $publico;
            if ($exceso > 0) {
                $fuera[] = [
                    'nivel' => (string) ($niveles->firstWhere('id', $t->asesor_nivel_id)?->nombre ?? '—'),
                    'plan_id' => $planId,
                    'tipo_modalidad_id' => $modalidadId,
                    'nivel_arl' => $nivelArl,
                    'publico' => round($publico),
                    'exceso' => round($exceso),
                ];
            }
        }

        return $fuera;
    }

    // ─────────────────────────────────────────────────────────────────
    // Distribución al facturar (la usa la Fase 5)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Reparte el costo de afiliación realmente cobrado en las columnas dist_* de la factura.
     *
     * Mapeo acordado (¡ojo, no es el histórico!):
     *   asesor → dist_asesor · retiro → dist_retiro · otros → dist_admon · aliado → dist_utilidad
     *
     * Devuelve null si el contrato no participa de este esquema (afiliacion_asesor NULL, o sea
     * contrato creado antes de esta función): el llamador debe seguir con
     * ConfiguracionAliado::calcularDistribucion, que es el camino de siempre.
     *
     * @return array{admon: int, asesor: int, retiro: int, utilidad: int, encargado: int}|null
     */
    public static function distribucionFactura(
        Contrato $contrato,
        int $costoAfiliacionCobrado,
        ?int $mes = null,
        ?int $anio = null
    ): ?array {
        if ($contrato->afiliacion_asesor === null) {
            return null;
        }

        $alidoId = (int) $contrato->aliado_id;
        $planId = (int) $contrato->plan_id;
        $modalidadId = (int) $contrato->tipo_modalidad_id;
        $nivelArl = (int) ($contrato->n_arl ?: 1);

        // En una renovación de IR / Gestión ARL se cobra afiliación, pero al asesor le
        // corresponde su admon mensual, no la comisión de afiliación.
        $esRenovacion = self::esRenovacion($alidoId, $contrato->cedula, $modalidadId, $mes, $anio);

        $asesor = $esRenovacion
            ? (int) round((float) $contrato->admon_asesor)
            : (int) round((float) $contrato->afiliacion_asesor);

        $publicoLista = self::resolverPrecioPublico($alidoId, $planId, $modalidadId, $nivelArl);
        $retiro = (int) round(self::resolverRetiro($alidoId, $planId, $modalidadId, $nivelArl, $publicoLista));
        $otros = (int) round(self::resolverOtros($alidoId, $planId, $modalidadId, $nivelArl));

        // Retiro sin configurar en Parámetros ⇒ se calcula. Decisión de ago-2026: en planilla
        // el retiro siempre se calcula, y en afiliación manda Parámetros con el cálculo como
        // respaldo. Así una celda vacía no le regala el retiro a la utilidad.
        if ($retiro === 0) {
            $retiro = self::retiroCalculado($contrato);
        }

        // Gestión ARL nunca paga retiro — regla vigente en FacturacionController.
        if ($modalidadId === self::MODALIDAD_GESTION_ARL) {
            $retiro = 0;
        }

        // El aliado absorbe el sobrante (o el faltante) frente al precio de lista.
        $asesor = min($asesor, $costoAfiliacionCobrado);
        $retiro = min($retiro, max(0, $costoAfiliacionCobrado - $asesor));
        $otros = min($otros, max(0, $costoAfiliacionCobrado - $asesor - $retiro));
        $utilidad = max(0, $costoAfiliacionCobrado - $asesor - $retiro - $otros);

        return [
            'asesor' => $asesor,
            'retiro' => $retiro,
            'admon' => $otros,     // "otros" viaja en dist_admon (mapeo acordado)
            'utilidad' => $utilidad,  // lo del aliado viaja en dist_utilidad
            'encargado' => 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Sugerencia de precios para los planes sin AFP
    // ─────────────────────────────────────────────────────────────────

    /** Del costo mensual, el asesor cobra por afiliar el 85%: la rebaja del 15% acordada. */
    public const SUGERENCIA_PCT = 0.85;

    /** Ninguna afiliación baja de aquí… salvo que el plan cueste menos al mes (ahí manda el mes). */
    public const SUGERENCIA_PISO = 45000;

    /**
     * Propone el precio de afiliación de todos los planes contra lo que cuestan al mes
     * (regla de ago-2026). La referencia siempre es el costo mensual como dependiente a
     * salario mínimo: seguridad social + administración.
     *
     *   • Plan SIN pensión → el 85% de su propio mes. Es la rebaja del 15% acordada: el mes
     *     de un plan sin AFP es bajo y cobrar el mes completo de afiliación no se sostiene.
     *
     *   • Plan CON pensión → el mes de ESE MISMO plan pero sin la pensión, completo y sin
     *     rebaja. La pensión es la mitad larga de la cotización y arrastraba la afiliación a
     *     cifras imposibles: EPS+ARL+AFP cuesta 405.600 al mes, pero su afiliación se cobra
     *     como la de EPS+ARL, o sea 125.400. Sin esto, afiliar sin pensión salía más caro que
     *     afiliar con pensión.
     *
     * En los dos casos: piso 45.000, tope el mes real del plan (nunca cobrar de afiliación
     * más de lo que cuesta el mes), y los riesgos 2-5 salen de la base por la escalera que el
     * aliado ya usa en ese plan — calcular cada riesgo contra su propio mes dispararía el
     * precio, porque la ARL del riesgo 5 vale trece veces la del 1.
     *
     * El precio es del PLAN: el mismo valor va a todas sus modalidades, que es como está
     * armada la tabla hoy.
     *
     * Solo calcula. Escribir es cosa del llamador.
     *
     * @return array{
     *   filas: array<int, array{plan_id:int, plan:string, pension:bool, nivel_arl:int, mes:int, base:int, hoy:int, nuevo:int}>,
     *   celdas: array<int, array{plan_id:int, tipo_modalidad_id:int, nivel_arl:int, valor:int}>
     * }
     */
    public static function proponerPrecios(int $alidoId): array
    {
        $smmlv = (int) \App\Models\ConfiguracionBrynex::salarioMinimo();
        $rs = DB::table('razones_sociales')->where('aliado_id', $alidoId)->value('id');
        $aCien = fn ($v) => (int) (round($v / 100) * 100);

        // Admon de respaldo para los planes sin celda propia: la que más se repite en las
        // celdas del aliado. La config genérica no sirve — en varios aliados quedó con un
        // valor viejo mientras las celdas y los contratos reales usan otro.
        $admonRespaldo = (int) (DB::table('afiliacion_arl_modalidad')
            ->where('aliado_id', $alidoId)
            ->whereNotNull('administracion')->where('administracion', '>', 0)
            ->selectRaw('administracion, count(*) c')->groupBy('administracion')
            ->orderByDesc('c')->value('administracion')
            ?: self::resolverAdmonTotal($alidoId, 0, 0, 1));

        $planes = \App\Models\PlanContrato::where('activo', true)->orderBy('id')->get();

        // La modalidad de referencia (Dependiente E) se carga UNA vez y se le pasa a cada
        // contrato: si no, cada cotización la pedía por su cuenta — eran 79 consultas de
        // ~250ms cada una y el cálculo se iba a 23 segundos.
        $modRef = TipoModalidad::find(0);

        /**
         * Precio que hoy tiene el plan en ese riesgo, mirando TODAS sus modalidades.
         *
         * No sirve preguntar por la modalidad 0: media lista (Solo ARL, ARL+CCF, Solo EPS…)
         * no se vende como Dependiente E, así que salían todos en cero y parecía que el
         * cálculo cambiaba precios que en realidad ya estaban puestos.
         */
        $precioActual = function (int $planId, int $arl) use ($alidoId): int {
            foreach (self::celdasDelAliado($alidoId) as $clave => $celda) {
                [$p, , $a] = explode('_', $clave);
                if ((int) $p === $planId && (int) $a === $arl && $celda->costo_afiliacion !== null) {
                    return (int) $celda->costo_afiliacion;
                }
            }

            return 0;
        };

        /**
         * Lo que cuesta el plan al mes. Con $sinPension se cotiza el mismo plan como si no
         * llevara AFP — es el equivalente exacto de EPS+ARL frente a EPS+ARL+AFP, y sirve
         * también para los planes cuyo equivalente sin pensión no existe en la lista.
         */
        $costoMes = function ($plan, int $arl, bool $sinPension = false) use ($alidoId, $smmlv, $rs, $admonRespaldo, $modRef) {
            $celda = self::celda($alidoId, (int) $plan->id, 0, $arl);
            $admon = ($celda && $celda->administracion !== null)
                ? (int) $celda->administracion
                : $admonRespaldo;

            if ($sinPension) {
                $plan = clone $plan;
                $plan->incluye_pension = false;
            }

            // Contrato en memoria solo para cotizar: nunca se guarda.
            $c = new Contrato([
                'aliado_id' => $alidoId, 'plan_id' => $plan->id, 'tipo_modalidad_id' => 0,
                'n_arl' => $arl, 'salario' => $smmlv, 'razon_social_id' => $rs,
                'administracion' => 0, 'seguro' => 0,
            ]);
            $c->setRelation('plan', $plan);
            $c->setRelation('tipoModalidad', $modRef);
            $m = $c->calcularCotizacion(30);

            return (int) (($m['eps'] ?? 0) + ($m['arl'] ?? 0) + ($m['pen'] ?? 0) + ($m['caja'] ?? 0)) + $admon;
        };

        // Escalera de riesgo que ya usa cada plan, y el promedio para los que no tienen precio.
        // Solo cuentan los planes SIN pensión: los de pensión ya no usan escalera (cada riesgo
        // sale de su propio mes sin AFP) y su curva es la de la prima ARL cruda — trece veces
        // del riesgo 1 al 5 —, que metida en el promedio dispararía a los demás.
        $escaleras = [];
        foreach ($planes as $plan) {
            $p1 = (float) $precioActual((int) $plan->id, 1);
            if ($p1 <= 0 || ! $plan->incluye_arl || $plan->incluye_pension) {
                continue;
            }
            for ($a = 1; $a <= 5; $a++) {
                $pa = (float) $precioActual((int) $plan->id, $a);
                $escaleras[(int) $plan->id][$a] = $pa > 0 ? $pa / $p1 : 1.0;
            }
        }
        $promedio = [];
        for ($a = 1; $a <= 5; $a++) {
            $v = array_column($escaleras, $a);
            $promedio[$a] = $v ? array_sum($v) / count($v) : 1.0;
        }

        $filas = [];
        $precio = [];
        foreach ($planes as $plan) {
            $conPension = (bool) $plan->incluye_pension;

            // Sin pensión: el 85% de su propio mes en el riesgo 1, y los riesgos 2-5 con la
            // escalera del aliado — calcularlos contra su mes dispararía el precio, porque la
            // ARL del riesgo 5 vale trece veces la del 1.
            $mes1 = $costoMes($plan, 1);
            $base = min($mes1, max(self::SUGERENCIA_PISO, $aCien($mes1 * self::SUGERENCIA_PCT)));
            $esc = $escaleras[(int) $plan->id] ?? $promedio;

            foreach ($plan->incluye_arl ? [1, 2, 3, 4, 5] : [1] as $arl) {
                $mes = $costoMes($plan, $arl);
                $sinAfp = $conPension ? $costoMes($plan, $arl, true) : 0;

                // Con pensión: CADA riesgo vale el mes de ese mismo plan sin la AFP, completo.
                // Aquí sí se calcula riesgo por riesgo: el precio del plan sin pensión ya trae
                // dentro la ARL de ese riesgo, así que la escalera saldría duplicada.
                $nuevo = $conPension
                    ? min($mes, max(self::SUGERENCIA_PISO, $sinAfp))
                    : min($mes, $aCien($base * ($esc[$arl] ?? 1.0)));

                $precio[(int) $plan->id][$arl] = $nuevo;

                $filas[] = [
                    'plan_id' => (int) $plan->id,
                    'plan' => $plan->nombre,
                    'pension' => $conPension,
                    'nivel_arl' => $plan->incluye_arl ? $arl : 0,
                    'mes' => $mes,
                    'base' => $sinAfp,
                    'hoy' => $precioActual((int) $plan->id, $arl),
                    'nuevo' => $nuevo,
                ];
            }
        }

        // El mismo precio del plan se replica a todas sus modalidades tarifables… salvo donde
        // el retiro de esa modalidad se lo come. El precio se calcula contra el costo del plan
        // como dependiente, pero el retiro es de la modalidad real: en tiempo parcial la ARL
        // cotiza el mes entero, así que en riesgo 5 el retiro puede costar más que toda la
        // afiliación. Ahí se sube el precio para que quede el mismo margen que en el resto.
        $celdas = [];
        $subidas = [];
        $conservadas = [];
        foreach (self::combinaciones() as $combo) {
            $plan = $combo['plan'];
            $mod = $combo['modalidad'];
            $modId = (int) $mod->id;
            if (! isset($precio[(int) $plan->id])) {
                continue;
            }

            foreach (self::nivelesArlPara($plan, $modId) as $arl) {
                $delPlan = $precio[(int) $plan->id][$arl] ?? $precio[(int) $plan->id][1];

                // Retiro de ESTA celda: en tiempo parcial es el bloque de 7 días, que puede
                // costar más que toda la afiliación calculada contra el plan como dependiente.
                $c = new Contrato([
                    'aliado_id' => $alidoId, 'plan_id' => $plan->id, 'tipo_modalidad_id' => $modId,
                    'n_arl' => $arl, 'salario' => $smmlv, 'razon_social_id' => $rs,
                    'administracion' => 0, 'seguro' => 0,
                ]);
                $c->setRelation('plan', $plan);
                $c->setRelation('tipoModalidad', $mod);
                $retiroCelda = self::retiroCalculado($c);

                // El piso es el retiro de la celda más su bolsa de "otros": por debajo de ahí
                // la afiliación no alcanza ni para sacar a la persona y el aliado pierde. No se
                // le suma margen: subir todas las celdas de tiempo parcial "por si acaso" sería
                // un cambio de precios que nadie pidió. Donde el piso manda, el asesor queda en
                // 0 y hay que subir esa celda a mano si se quiere que gane algo.
                $otrosCelda = (int) self::resolverOtros($alidoId, (int) $plan->id, $modId, $arl);
                $valor = max($delPlan, $aCien($retiroCelda + $otrosCelda));

                if ($valor > $delPlan) {
                    $subidas[] = [
                        'plan' => $plan->nombre,
                        'modalidad' => $mod->observacion ?: $mod->modalidad,
                        'nivel_arl' => $arl,
                        'del_plan' => $delPlan,
                        'retiro' => $retiroCelda,
                        'valor' => $valor,
                    ];
                }

                $clave = $plan->id.'_'.self::modalidadTarifa($modId).'_'.$arl;

                // El botón NUNCA baja una casilla que ya está por encima de lo que propone:
                // eso es un ajuste comercial del aliado y el cálculo no tiene por qué saber
                // por qué está ahí. Para bajar un precio se edita la casilla a mano.
                $actual = (int) (self::celdasDelAliado($alidoId)->get($clave)?->costo_afiliacion ?? 0);
                if ($actual > $valor) {
                    $conservadas[] = [
                        'plan' => $plan->nombre,
                        'modalidad' => $mod->observacion ?: $mod->modalidad,
                        'nivel_arl' => $arl,
                        'propuesto' => $valor,
                        'actual' => $actual,
                    ];
                    $valor = $actual;
                }

                $celdas[$clave] = [
                    'plan_id' => (int) $plan->id,
                    'tipo_modalidad_id' => self::modalidadTarifa($modId),
                    'nivel_arl' => $arl,
                    'valor' => $valor,
                    'hoy' => $actual,
                ];
            }
        }

        return [
            'filas' => $filas,
            'celdas' => array_values($celdas),
            'subidas' => $subidas,
            'conservadas' => $conservadas,
        ];
    }

    /**
     * Retiro que le corresponde a cada celda del tarifario según el salario mínimo vigente.
     *
     * Es lo que cuesta sacar a la persona: un día de seguridad social, o el bloque de 7 días
     * en tiempo parcial (ver retiroCalculado). Se recalcula cada año, cuando cambia el salario.
     *
     * Regla al aplicar (acordada ago-2026): **solo reemplaza si el cálculo es MAYOR** que lo
     * que hay guardado. El salario mínimo siempre sube, así que eso actualiza los retiros del
     * año pasado sin pisar el ajuste de un aliado que puso un valor más alto a propósito.
     *
     * @return array<int, array{plan_id:int, plan:string, tipo_modalidad_id:int, modalidad:string,
     *                          nivel_arl:int, hoy:int|null, calculado:int, sube:bool}>
     */
    public static function proponerRetiros(int $alidoId): array
    {
        $smmlv = (int) \App\Models\ConfiguracionBrynex::salarioMinimo();
        $rs = DB::table('razones_sociales')->where('aliado_id', $alidoId)->value('id');
        $celdas = self::celdasDelAliado($alidoId);

        $filas = [];
        foreach (self::combinaciones() as $combo) {
            $plan = $combo['plan'];
            $mod = $combo['modalidad'];
            $modId = (int) $mod->id;

            foreach (self::nivelesArlPara($plan, $modId) as $arl) {
                // Contrato en memoria con las dos relaciones puestas: sin esto cada cotización
                // pediría plan y modalidad por su cuenta, y son ~140 celdas a 250ms cada una.
                $c = new Contrato([
                    'aliado_id' => $alidoId, 'plan_id' => $plan->id, 'tipo_modalidad_id' => $modId,
                    'n_arl' => $arl, 'salario' => $smmlv, 'razon_social_id' => $rs,
                    'administracion' => 0, 'seguro' => 0,
                ]);
                $c->setRelation('plan', $plan);
                $c->setRelation('tipoModalidad', $mod);

                $calculado = self::retiroCalculado($c);
                $celda = $celdas->get($plan->id.'_'.self::modalidadTarifa($modId).'_'.$arl);
                $hoy = $celda?->retiro !== null ? (int) $celda->retiro : null;

                $filas[] = [
                    'plan_id' => (int) $plan->id,
                    'plan' => $plan->nombre,
                    'tipo_modalidad_id' => self::modalidadTarifa($modId),
                    'modalidad' => $mod->observacion ?: $mod->modalidad,
                    'nivel_arl' => $arl,
                    'hoy' => $hoy,
                    'calculado' => $calculado,
                    // Un cálculo en 0 no se escribe: en la celda, 0 significa «este plan no
                    // paga retiro» y apagaría el respaldo que lo calcula al facturar. Pasa en
                    // UPC, donde la EPS no es un % del IBC sino la tarifa del beneficiario.
                    'sube' => $calculado > 0 && ($hoy === null || $calculado > $hoy),
                ];
            }
        }

        return $filas;
    }

    /**
     * Valores heredados de Parámetros para cada celda del tarifario, ya resueltos con sus
     * respaldos: lo que la matriz de un nivel (o de un asesor) muestra en gris y contra lo que
     * calcula "aliado = público − retiro − otros − asesor".
     *
     * Se arma de una sola pasada para poder mandarlo al navegador como JSON y que el cálculo
     * del sobrante sea en vivo, sin ir al servidor por cada casilla.
     *
     * @return array<string, array{publico: int, retiro: int, otros: int, admon: int}>
     */
    public static function baseTarifario(int $alidoId): array
    {
        $base = [];

        foreach (self::combinaciones() as $combo) {
            $planId = (int) $combo['plan']->id;
            $modalidadId = (int) $combo['modalidad']->id;

            foreach ($combo['niveles'] as $nivelArl) {
                $publico = self::resolverPrecioPublico($alidoId, $planId, $modalidadId, $nivelArl);

                $base[AsesorNivelTarifa::claveCelda($planId, $modalidadId, $nivelArl)] = [
                    'publico' => (int) round($publico),
                    'retiro' => (int) round(self::resolverRetiro($alidoId, $planId, $modalidadId, $nivelArl, $publico)),
                    'otros' => (int) round(self::resolverOtros($alidoId, $planId, $modalidadId, $nivelArl)),
                    'admon' => (int) round(self::resolverAdmonTotal($alidoId, $planId, $modalidadId, $nivelArl)),
                ];
            }
        }

        return $base;
    }

    /**
     * Guarda una matriz de celdas con el mínimo de consultas posible.
     *
     * Es la diferencia entre que la pantalla funcione o se caiga: hacer updateOrCreate celda por
     * celda son 2 consultas × hasta 193 celdas ≈ 96 segundos contra este servidor, y el guardado
     * moría por max_execution_time. Aquí se hace en 3 consultas fijas: leer lo que hay, borrar
     * lo que sobra o cambió, e insertar en bloque lo nuevo o modificado.
     *
     * Una celda modificada se borra y se vuelve a insertar a propósito: así "aplicar porcentaje
     * a todo" cuesta lo mismo que cambiar una sola casilla.
     *
     * @param  string  $clase  modelo de la celda (AsesorTarifa, AsesorNivelTarifa, …)
     * @param  array  $filtro  dueño de la matriz, ej. ['asesor_id' => 5]
     * @param  array  $matrizPost  [plan][modalidad][riesgo] => valor escalar, o array por columna
     * @param  array  $columnas  columnas de valor; con una sola, el post trae escalares
     * @param  array  $extra  columnas fijas a escribir en cada inserción, ej. aliado_id
     * @return array{insertadas: int, borradas: int, sin_cambio: int}
     */
    public static function sincronizarCeldas(
        string $clase,
        array $filtro,
        array $matrizPost,
        array $columnas,
        array $extra = []
    ): array {
        $existentes = $clase::where($filtro)->get()
            ->keyBy(fn ($t) => AsesorNivelTarifa::claveCelda(
                (int) $t->plan_id, (int) $t->tipo_modalidad_id, (int) $t->nivel_arl
            ));

        $escalar = count($columnas) === 1;
        $ahora = now();
        $insertar = [];
        $borrar = [];
        $sinCambio = 0;

        foreach ($matrizPost as $planId => $porModalidad) {
            foreach ($porModalidad as $modalidadId => $porNivel) {
                foreach ($porNivel as $nivelArl => $entrada) {
                    $clave = AsesorNivelTarifa::claveCelda((int) $planId, (int) $modalidadId, (int) $nivelArl);
                    $actual = $existentes->get($clave);

                    // Normalizar: vacío significa "sin valor propio" (cae a la cascada), no 0.
                    $valores = [];
                    foreach ($columnas as $col) {
                        $v = $escalar ? $entrada : ($entrada[$col] ?? null);
                        $valores[$col] = ($v === null || $v === '') ? null : (float) $v;
                    }

                    $vacia = ! collect($valores)->contains(fn ($v) => $v !== null);

                    if ($vacia) {
                        if ($actual) {
                            $borrar[] = $actual->id;
                        }

                        continue;
                    }

                    // ¿Cambió algo? Si no, ni se toca.
                    if ($actual) {
                        $igual = true;
                        foreach ($columnas as $col) {
                            $viejo = $actual->$col === null ? null : (float) $actual->$col;
                            if ($viejo !== $valores[$col]) {
                                $igual = false;
                                break;
                            }
                        }

                        if ($igual) {
                            $sinCambio++;

                            continue;
                        }

                        $borrar[] = $actual->id;
                    }

                    $insertar[] = $filtro + $extra + $valores + [
                        'plan_id' => (int) $planId,
                        'tipo_modalidad_id' => (int) $modalidadId,
                        'nivel_arl' => (int) $nivelArl,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }

        if ($borrar) {
            // SQL Server topa en 2100 parámetros por sentencia.
            foreach (array_chunk($borrar, 500) as $lote) {
                $clase::whereIn('id', $lote)->delete();
            }
        }

        if ($insertar) {
            // insert() en bloque no dispara eventos ni timestamps: por eso van arriba a mano.
            foreach (array_chunk($insertar, 150) as $lote) {
                $clase::insert($lote);
            }
        }

        return [
            'insertadas' => count($insertar),
            'borradas' => count($borrar),
            'sin_cambio' => $sinCambio,
        ];
    }

    /**
     * Estructura de la matriz para las pantallas de nivel y de asesor: por plan, sus modalidades
     * y, por cada nivel de riesgo, lo heredado de Parámetros + lo que gana el asesor + el
     * sobrante del aliado. La comparten las dos pantallas para que no se desincronicen.
     *
     * @param  array  $base  salida de baseTarifario()
     * @param  \Illuminate\Support\Collection  $valores  celdas (de nivel o de asesor) por clave
     */
    public static function armarMatriz(array $base, $valores): array
    {
        $matriz = [];

        // Agrupada en TARJETAS, igual que el tarifario de Parámetros: normalmente una tarjeta
        // por modalidad con sus planes dentro, salvo los grupos (ver GRUPOS_TARIFARIO), donde
        // la tarjeta junta varias modalidades y cada una aparece como una opción más.
        foreach (self::combinaciones() as $combo) {
            $plan = $combo['plan'];
            $modalidad = $combo['modalidad'];
            $modId = (int) $modalidad->id;
            $nombreMod = $modalidad->observacion ?: $modalidad->modalidad;

            $grupo = self::grupoDeModalidad($modId);
            $tarjeta = $grupo ? "g_{$grupo}" : "m_{$modId}";

            $filas = [];
            foreach ($combo['niveles'] as $nivelArl) {
                $clave = AsesorNivelTarifa::claveCelda((int) $plan->id, $modId, $nivelArl);
                $b = $base[$clave] ?? ['publico' => 0, 'retiro' => 0, 'otros' => 0, 'admon' => 0];
                $asesor = $valores->get($clave)?->afil_asesor;

                $filas[$nivelArl] = [
                    'clave' => $clave,
                    'publico' => $b['publico'],
                    'retiro' => $b['retiro'],
                    'otros' => $b['otros'],
                    'admon' => $b['admon'],
                    'asesor' => $asesor,
                    'aliado' => $b['publico'] - $b['retiro'] - $b['otros'] - (float) ($asesor ?? 0),
                ];
            }

            $matriz[$tarjeta]['clave'] = $tarjeta;
            $matriz[$tarjeta]['nombre'] = $grupo
                ? self::GRUPOS_TARIFARIO[$grupo]['nombre']
                : $nombreMod;
            $matriz[$tarjeta]['tiempo_parcial'] = (bool) $modalidad->es_tiempo_parcial;
            $matriz[$tarjeta]['opciones'][] = [
                // En una tarjeta de grupo el botón nombra la MODALIDAD (Estudiante K, Gestión
                // ARL…); en una normal nombra el PLAN, que es lo que varía dentro.
                'etiqueta' => $grupo ? $nombreMod : $plan->nombre,
                'modalidad_id' => $modId,
                'plan' => $plan,
                'niveles_arl' => $combo['niveles'],
                'filas' => $filas,
            ];
        }

        return $matriz;
    }

    // ─────────────────────────────────────────────────────────────────
    // Grilla de seguridad social — la columna "Total mes" del tarifario
    // ─────────────────────────────────────────────────────────────────

    private static function claveCacheSs(int $alidoId): string
    {
        // El salario mínimo entra en la llave para que en enero, al cambiarlo, la grilla se
        // recalcule sola sin que nadie tenga que acordarse de limpiar el caché.
        $sm = (int) \App\Models\ConfiguracionBrynex::salarioMinimo();

        return "tarifario_ss:v1:{$alidoId}:{$sm}";
    }

    /**
     * Seguridad social mensual (EPS+ARL+AFP+CCF a salario mínimo, 30 días) de cada celda del
     * tarifario, indexada por "plan_modalidad_nivel".
     *
     * Se cachea porque cotizar las 193 celdas cuesta ~40 consultas contra un servidor a 250ms
     * por consulta. Solo depende del plan, la modalidad, el riesgo, el salario mínimo y las
     * tarifas ARL del aliado — NO de los precios que el aliado edita, así que sobrevive a
     * guardar Parámetros y la admon se suma encima en pantalla.
     *
     * @return array<string, int>
     */
    public static function gridSeguridadSocial(int $alidoId): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            self::claveCacheSs($alidoId),
            now()->addHours(12),
            function () use ($alidoId) {
                $combinaciones = self::combinaciones();

                // Sembrar el catálogo del cotizador con lo que ya está en memoria: sin esto
                // son 2 consultas por celda.
                CotizadorService::precargarCatalogo(
                    collect($combinaciones)->pluck('plan')->unique('id'),
                    collect($combinaciones)->pluck('modalidad')->unique('id')
                );

                $salario = (float) \App\Models\ConfiguracionBrynex::salarioMinimo();
                $grid = [];

                foreach ($combinaciones as $combo) {
                    foreach ($combo['niveles'] as $nivelArl) {
                        $r = CotizadorService::calcular([
                            'tipo_modalidad_id' => $combo['modalidad']->id,
                            'plan_id' => $combo['plan']->id,
                            'n_arl' => $nivelArl,
                            'salario' => $salario,
                            'administracion' => 0,
                            'admon_asesor' => 0,
                            'seguro' => 0,
                            'dias' => 30,
                        ], $alidoId);

                        $clave = AsesorNivelTarifa::claveCelda(
                            (int) $combo['plan']->id,
                            (int) $combo['modalidad']->id,
                            $nivelArl
                        );

                        $grid[$clave] = (int) round((float) ($r['ss'] ?? 0));
                    }
                }

                return $grid;
            }
        );
    }

    /** Bota la grilla cacheada. Llamar al guardar tarifas ARL o parámetros globales. */
    public static function olvidarGridSs(int $alidoId): void
    {
        \Illuminate\Support\Facades\Cache::forget(self::claveCacheSs($alidoId));
    }

    // ─────────────────────────────────────────────────────────────────
    // Combinaciones tarifables (plan × modalidad) — arma las matrices en pantalla
    // ─────────────────────────────────────────────────────────────────

    /**
     * Combinaciones plan × modalidad que existen de verdad, con los niveles de riesgo que
     * aplican: 1..5 si el plan incluye ARL, y solo [1] si no (ahí el riesgo no cambia nada).
     * Excluye las modalidades marcadas solo_ia, que no se venden por mostrador.
     *
     * @return array<int, array{plan: \App\Models\PlanContrato, modalidad: TipoModalidad, niveles: array<int,int>}>
     */
    public static function combinaciones(): array
    {
        $planes = \App\Models\PlanContrato::where('activo', true)->orderBy('id')->get();

        $relaciones = DB::table('modalidad_planes')
            ->where('solo_ia', false)
            ->get(['plan_id', 'tipo_modalidad_id']);

        // Solo modalidades activas: hay relaciones plan-modalidad de modalidades apagadas
        // (ej. "TipoE- (1 dia pension)") que no se venden y solo ensucian el tarifario.
        $modalidades = TipoModalidad::whereIn(
            'id', $relaciones->pluck('tipo_modalidad_id')->unique()
        )->where('activo', true)->orderBy('orden')->get()->keyBy('id');

        $out = [];
        foreach ($planes as $plan) {
            foreach ($relaciones->where('plan_id', $plan->id) as $rel) {
                // Las modalidades con alias no se tarifan aparte: heredan de su equivalente,
                // así nunca existen dos celdas para el mismo precio.
                if (array_key_exists((int) $rel->tipo_modalidad_id, self::MODALIDADES_ALIAS)) {
                    continue;
                }

                $modalidad = $modalidades->get($rel->tipo_modalidad_id);
                if (! $modalidad) {
                    continue;
                }

                // Cada modalidad puede cubrir solo parte de los riesgos (K: 1-3, Y: 4-5).
                $niveles = self::nivelesArlPara($plan, (int) $modalidad->id);
                if (! $niveles) {
                    continue;
                }

                $out[] = [
                    'plan' => $plan,
                    'modalidad' => $modalidad,
                    'niveles' => $niveles,
                ];
            }
        }

        return $out;
    }
}
