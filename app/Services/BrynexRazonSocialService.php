<?php

namespace App\Services;

use App\Models\BrynexCalendarioVencimiento;
use App\Models\BrynexObligacion;
use App\Models\BrynexObligacionCatalogo;
use App\Models\BrynexRazonSocial;
use App\Models\BrynexRazonSocialVinculo;
use Illuminate\Support\Facades\DB;

/**
 * Consolida por NIT lo que en BryNex está repartido por aliado.
 *
 * Todo lo de aquí atraviesa `brynex_razon_social_vinculos`: la ficha conoce su
 * NIT, el vínculo sabe qué filas de `razones_sociales` le corresponden en cada
 * aliado, y de ahí salen los afiliados, el dinero y el checklist.
 *
 * Ojo con la latencia: el SQL Server es remoto y cada consulta cuesta ~250 ms
 * (ver docs y la nota de rendimiento en UsuarioPermisoController). Los métodos
 * de aquí están escritos para hacer UNA consulta por pregunta, no una por
 * razón social.
 */
class BrynexRazonSocialService
{
    /**
     * Ids de razón social por ficha, memorizados dentro de la petición.
     *
     * La ficha los pide dos veces (afiliados y movimientos) y cada viaje al
     * SQL Server cuesta ~250 ms: sin esto, medio segundo tirado por pantalla.
     */
    private array $idsCache = [];

    // ─── Vínculos ─────────────────────────────────────────────────────

    /**
     * Reconstruye los vínculos de la ficha cruzando el NIT contra
     * `razones_sociales`. Es idempotente y se puede correr cuando sea: si un
     * aliado creó hoy una fila nueva con ese mismo NIT, aquí queda enganchada.
     *
     * @return int cuántos vínculos quedaron
     */
    public function sincronizarVinculos(BrynexRazonSocial $ficha): int
    {
        $filas = DB::table('razones_sociales')
            ->where('nit', $ficha->nit)
            ->get(['id', 'aliado_id']);

        $actuales = $ficha->vinculos()->get(['razon_social_id', 'aliado_id'])
            ->keyBy(fn ($v) => (int) $v->razon_social_id);

        $deben = $filas->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Los que sobran: el aliado borró la razón social o le cambió el NIT.
        $sobran = array_diff($actuales->keys()->all(), $deben);
        if ($sobran) {
            $ficha->vinculos()->whereIn('razon_social_id', $sobran)->delete();
        }

        // Solo se escribe lo que cambió. Este método corre en CADA apertura de
        // la ficha, y el caso normal es que no haya cambiado nada: con
        // updateOrCreate por fila serían dos consultas por vínculo cada vez.
        $nuevos = [];
        foreach ($filas as $fila) {
            $previo = $actuales->get((int) $fila->id);

            if ($previo && (int) $previo->aliado_id === (int) $fila->aliado_id) {
                continue;
            }

            $nuevos[] = [
                'ficha_id' => $ficha->id,
                'razon_social_id' => (int) $fila->id,
                'aliado_id' => (int) $fila->aliado_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($nuevos) {
            // Los que cambiaron de aliado se reemplazan; el resto es alta nueva.
            $ficha->vinculos()
                ->whereIn('razon_social_id', array_column($nuevos, 'razon_social_id'))
                ->delete();
            BrynexRazonSocialVinculo::insert($nuevos);
        }

        // Ya se sabe cuáles son: sembrar la memoria evita que el siguiente
        // razonSocialIds() vuelva a preguntarle lo mismo a la base.
        $this->idsCache[$ficha->id] = $deben;

        return count($deben);
    }

    /** Los ids de `razones_sociales` que representan a esta ficha. */
    public function razonSocialIds(BrynexRazonSocial $ficha): array
    {
        return $this->idsCache[$ficha->id] ??= $ficha->vinculos()
            ->pluck('razon_social_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ─── Afiliados ────────────────────────────────────────────────────

    /**
     * Cuántos afiliados vigentes tiene la razón social, sin importar el
     * aliado. Devuelve el desglose por aliado más el total.
     *
     * Este es el número que no se puede ver desde el panel de un aliado:
     * ELITES CREACIONES tiene 214 vigentes en Grupo Fecop y 143 en BRYGAR, y
     * ante la ley son 357 personas en la misma empresa.
     */
    public function afiliadosActivos(BrynexRazonSocial $ficha): array
    {
        $ids = $this->razonSocialIds($ficha);

        if (! $ids) {
            return ['total' => 0, 'por_aliado' => []];
        }

        $filas = DB::table('contratos as c')
            ->join('aliados as a', 'a.id', '=', 'c.aliado_id')
            ->whereIn('c.razon_social_id', $ids)
            ->where('c.estado', 'vigente')
            ->groupBy('c.aliado_id', 'a.nombre')
            ->orderByRaw('COUNT(*) DESC')
            ->get([
                'c.aliado_id',
                'a.nombre as aliado',
                DB::raw('COUNT(*) as total'),
            ]);

        return [
            'total' => (int) $filas->sum(fn ($f) => (int) $f->total),
            'por_aliado' => $filas,
        ];
    }

    // ─── Dinero ───────────────────────────────────────────────────────

    /**
     * Cuentas bancarias que pertenecen a esta razón social.
     *
     * Se resuelven por dos vías, a propósito:
     *
     *  1. `banco_cuentas.razon_social_id`, que un humano puede corregir a mano.
     *     Es la única forma de enganchar una cuenta cuyo campo `nit` está en
     *     blanco o trae un celular en vez del NIT (los Nequi y Daviplata).
     *  2. El NIT normalizado. `banco_cuentas.nit` es texto libre y viene tanto
     *     '901175588' como '901175588-8', así que se compara solo la parte
     *     numérica antes del guion.
     *
     * Se cargan las 79 cuentas de una sola vez y se filtra en PHP: sale más
     * barato que armar la normalización en SQL Server.
     */
    public function cuentasDe(BrynexRazonSocial $ficha): array
    {
        $vinculadas = $this->razonSocialIds($ficha);

        return DB::table('banco_cuentas')
            ->get(['id', 'aliado_id', 'nombre', 'nit', 'banco', 'numero_cuenta', 'razon_social_id'])
            ->filter(function ($cuenta) use ($ficha, $vinculadas) {
                if ($cuenta->razon_social_id && in_array((int) $cuenta->razon_social_id, array_map('intval', $vinculadas), true)) {
                    return true;
                }

                return $this->nitNormalizado($cuenta->nit) === (string) $ficha->nit;
            })
            ->values()
            ->all();
    }

    /** '901175588-8' → '901175588'. Null y basura devuelven cadena vacía. */
    private function nitNormalizado(?string $nit): string
    {
        if (! $nit) {
            return '';
        }

        // Se queda con lo de antes del guion y solo con dígitos.
        $base = preg_replace('/\D/', '', explode('-', trim($nit))[0]);

        // Un NIT de empresa tiene 9 o 10 dígitos. Lo demás (celulares de
        // Nequi, cédulas, '333333') no se cruza: daría falsos positivos.
        return strlen((string) $base) >= 9 && strlen((string) $base) <= 10 ? (string) $base : '';
    }

    /**
     * Movimientos del año: qué entró y qué salió de las cuentas de la razón
     * social, mes a mes.
     *
     * Entradas = `consignaciones` (lo que consignaron los clientes).
     * Salidas  = `gastos` pagados desde esas cuentas (`banco_origen_id`).
     *
     * Ojo con lo que este número NO es: la mayor parte de lo que entra a una
     * razón social de estas es plata de los afiliados para pagar su seguridad
     * social, no ingreso de la empresa. Sirve para conciliar contra el extracto
     * y para saber cuánto se movió, no como base gravable.
     */
    public function movimientos(BrynexRazonSocial $ficha, int $anio): array
    {
        $cuentas = $this->cuentasDe($ficha);
        $ids = array_column($cuentas, 'id');

        $vacio = [
            'cuentas' => $cuentas,
            'meses' => $this->mesesEnCero(),
            'total_entradas' => 0.0,
            'total_salidas' => 0.0,
            'neto' => 0.0,
            'por_aliado' => collect(),
        ];

        if (! $ids) {
            return $vacio;
        }

        $entradas = DB::table('consignaciones')
            ->whereIn('banco_cuenta_id', $ids)
            ->whereYear('fecha', $anio)
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('MONTH(fecha)'))
            ->get([
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as cuantas'),
            ]);

        $salidas = DB::table('gastos')
            ->whereIn('banco_origen_id', $ids)
            ->whereYear('fecha', $anio)
            ->groupBy(DB::raw('MONTH(fecha)'))
            ->get([
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as cuantas'),
            ]);

        $meses = $this->mesesEnCero();

        foreach ($entradas as $fila) {
            $meses[(int) $fila->mes]['entradas'] = (float) $fila->total;
            $meses[(int) $fila->mes]['n_entradas'] = (int) $fila->cuantas;
        }

        foreach ($salidas as $fila) {
            $meses[(int) $fila->mes]['salidas'] = (float) $fila->total;
            $meses[(int) $fila->mes]['n_salidas'] = (int) $fila->cuantas;
        }

        $acumulado = 0.0;
        foreach ($meses as $m => $datos) {
            $acumulado += $datos['entradas'] - $datos['salidas'];
            $meses[$m]['neto'] = $datos['entradas'] - $datos['salidas'];
            $meses[$m]['acumulado'] = $acumulado;
        }

        // Quién consignó: el desglose por aliado es lo que muestra que la
        // cuenta la mueven varios.
        $porAliado = DB::table('consignaciones as c')
            ->join('aliados as a', 'a.id', '=', 'c.aliado_id')
            ->whereIn('c.banco_cuenta_id', $ids)
            ->whereYear('c.fecha', $anio)
            ->whereNull('c.deleted_at')
            ->groupBy('c.aliado_id', 'a.nombre')
            ->orderByRaw('SUM(c.valor) DESC')
            ->get([
                'a.nombre as aliado',
                DB::raw('SUM(c.valor) as total'),
                DB::raw('COUNT(*) as cuantas'),
            ]);

        $totalEntradas = array_sum(array_column($meses, 'entradas'));
        $totalSalidas = array_sum(array_column($meses, 'salidas'));

        return [
            'cuentas' => $cuentas,
            'meses' => $meses,
            'total_entradas' => $totalEntradas,
            'total_salidas' => $totalSalidas,
            'neto' => $totalEntradas - $totalSalidas,
            'por_aliado' => $porAliado,
        ];
    }

    private function mesesEnCero(): array
    {
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $meses[$m] = [
                'entradas' => 0.0, 'salidas' => 0.0,
                'n_entradas' => 0, 'n_salidas' => 0,
                'neto' => 0.0, 'acumulado' => 0.0,
            ];
        }

        return $meses;
    }

    // ─── Checklist ────────────────────────────────────────────────────

    /**
     * Genera los renglones que le faltan a la ficha, desde el año de
     * constitución hasta el año en curso.
     *
     * Nunca toca un renglón existente: si el contador ya lo marcó como pagada,
     * se queda como está aunque cambie el régimen. Lo único que hace es crear
     * lo que falta.
     *
     * @return int cuántos renglones nuevos creó
     */
    public function generarObligaciones(BrynexRazonSocial $ficha, ?int $soloAnio = null): int
    {
        if (! $ficha->regimen || ! $ficha->fecha_constitucion) {
            return 0;
        }

        $desde = $soloAnio ?? $ficha->fecha_constitucion->year;
        $hasta = $soloAnio ?? (int) now()->year;

        $catalogo = BrynexObligacionCatalogo::where('activo', true)->orderBy('orden')->get();
        $digito = $ficha->ultimoDigitoNit();

        // Lo que ya existe, de una sola consulta: evita un SELECT por renglón.
        $existentes = $ficha->obligaciones()
            ->whereBetween('anio', [$desde, $hasta])
            ->get(['anio', 'obligacion_codigo', 'periodo'])
            ->map(fn ($o) => "{$o->anio}|{$o->obligacion_codigo}|{$o->periodo}")
            ->flip();

        // El calendario completo del rango, también de una sola consulta.
        $calendario = BrynexCalendarioVencimiento::whereBetween('anio', [$desde, $hasta])
            ->where(fn ($q) => $q->where('ultimo_digito', $digito)->orWhereNull('ultimo_digito'))
            ->get()
            ->keyBy(fn ($c) => "{$c->anio}|{$c->obligacion_codigo}|{$c->periodo}");

        $nuevos = [];

        for ($anio = $desde; $anio <= $hasta; $anio++) {
            foreach ($catalogo as $obligacion) {
                if (! $obligacion->aplicaA($ficha)) {
                    continue;
                }

                for ($periodo = 1; $periodo <= $obligacion->periodosPorAnio(); $periodo++) {
                    $llave = "{$anio}|{$obligacion->codigo}|{$periodo}";

                    if ($existentes->has($llave)) {
                        continue;
                    }

                    if ($this->periodoAnteriorALaConstitucion($ficha, $obligacion, $anio, $periodo)) {
                        continue;
                    }

                    $nuevos[] = [
                        'ficha_id' => $ficha->id,
                        'anio' => $anio,
                        'obligacion_codigo' => $obligacion->codigo,
                        'periodo' => $periodo,
                        'periodo_etiqueta' => $obligacion->etiquetaPeriodo($periodo),
                        // Null en los años sin calendario cargado: el renglón
                        // existe para poder chulear y subir el soporte, pero
                        // no entra al semáforo ni dispara alertas.
                        'fecha_vencimiento' => $calendario->get($llave)?->fecha_vencimiento?->toDateString(),
                        'estado' => 'pendiente',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($nuevos, 200) as $lote) {
            BrynexObligacion::insert($lote);
        }

        return count($nuevos);
    }

    /**
     * No tiene sentido pedirle a una empresa constituida en septiembre el
     * anticipo del primer bimestre de ese año.
     */
    private function periodoAnteriorALaConstitucion(
        BrynexRazonSocial $ficha,
        BrynexObligacionCatalogo $obligacion,
        int $anio,
        int $periodo
    ): bool {
        if ($anio !== $ficha->fecha_constitucion->year) {
            return false;
        }

        $mesConstitucion = $ficha->fecha_constitucion->month;

        // Último mes que cubre el período.
        $mesFinal = match ($obligacion->periodicidad) {
            'mensual' => $periodo,
            'bimestral' => $periodo * 2,
            'cuatrimestral' => $periodo * 4,
            default => 12,
        };

        return $mesFinal < $mesConstitucion;
    }
}
