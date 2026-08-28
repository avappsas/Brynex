<?php

namespace App\Services;

use App\Models\BrynexCalendarioVencimiento;
use App\Models\BrynexObligacion;
use App\Models\BrynexObligacionCatalogo;
use App\Models\BrynexRazonSocial;
use App\Models\BrynexRazonSocialVinculo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            ->get(['id', 'aliado_id', 'nombre', 'nit', 'banco', 'numero_cuenta', 'razon_social_id', 'id_legacy'])
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
     * Base     = administración + afiliación de las facturas que se pagaron
     *            por esas cuentas: lo único que se le factura a la DIAN.
     *
     * Ojo con lo que las entradas NO son: la mayor parte de lo que entra a una
     * razón social de estas es plata de los afiliados para pagar su seguridad
     * social, no ingreso de la empresa. Sirve para conciliar contra el extracto
     * y para saber cuánto se movió, no como base gravable. La base gravable es
     * la otra columna, y es entre un 15 y un 25 % de las entradas.
     *
     * Los meses migrados los responde la base vieja. La migración no trajo el
     * `banco_cuenta_id` de las consignaciones, así que de enero a abril de 2026
     * BryNex tiene la plata registrada pero sin decir a qué cuenta entró: el
     * mes salía en cero. El legacy sí lo dice, y esos meses ya no cambian
     * nunca, así que se leen de allá y se cachean.
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
            'total_base' => 0.0,
            'neto' => 0.0,
            'por_aliado' => collect(),
            'legacy' => null,
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

        foreach ($this->baseFacturada($cuentas, $anio) as $mes => $fila) {
            $meses[$mes]['base'] = $fila['base'];
            $meses[$mes]['n_base'] = $fila['n'];
        }

        $legacy = $this->movimientosLegacy($cuentas, $anio);

        // Se suma, no se reemplaza: son conjuntos ajenos. Lo que el legacy
        // trae es lo que la migración dejó sin cuenta, y lo que BryNex tiene
        // con cuenta se registró después del corte. Que no se pisen está
        // verificado — ninguna factura del legacy tiene además una
        // consignación de BryNex apuntando a estas cuentas.
        foreach ($legacy['meses'] ?? [] as $mes => $fila) {
            $meses[$mes]['entradas'] += $fila['entradas'];
            $meses[$mes]['n_entradas'] += $fila['n_entradas'];
            $meses[$mes]['base'] += $fila['base'];
            $meses[$mes]['n_base'] += $fila['n_base'];
            $meses[$mes]['legacy'] = true;
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
            'total_base' => array_sum(array_column($meses, 'base')),
            'neto' => $totalEntradas - $totalSalidas,
            'por_aliado' => $porAliado,
            'legacy' => $legacy,
        ];
    }

    /**
     * Administración + afiliación de las facturas que se pagaron por estas
     * cuentas, mes a mes. Es exactamente la base que se le sube a Dataico.
     *
     * Se agrupa por `fecha_pago` y no por la fecha de la consignación porque
     * así es como la emisión arma los meses: una factura se emite en el mes en
     * que se pagó. Y la plata llega por dos caminos — la consignación del
     * momento de facturar y el abono posterior contra un préstamo — así que
     * mirar solo consignaciones dejaba los préstamos fuera.
     *
     * @param  array<int,object>  $cuentas
     * @return array<int,array{base:float,n:int}>
     */
    private function baseFacturada(array $cuentas, int $anio): array
    {
        $ids = array_column($cuentas, 'id');
        $aliados = array_values(array_unique(array_map(fn ($c) => (int) $c->aliado_id, $cuentas)));

        $filas = DB::table('facturas as f')
            ->whereIn('f.aliado_id', $aliados)
            ->whereNull('f.deleted_at')
            ->whereYear('f.fecha_pago', $anio)
            ->where(function ($w) use ($ids) {
                $w->whereExists(fn ($s) => $s->select(DB::raw(1))->from('consignaciones as cs')
                    ->whereColumn('cs.factura_id', 'f.id')->whereIn('cs.banco_cuenta_id', $ids)
                    ->whereNull('cs.deleted_at'))
                    ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('abonos as ab')
                        ->whereColumn('ab.factura_id', 'f.id')->whereIn('ab.banco_cuenta_id', $ids));
            })
            ->groupBy(DB::raw('MONTH(f.fecha_pago)'))
            ->get([
                DB::raw('MONTH(f.fecha_pago) as mes'),
                DB::raw('SUM(ISNULL(CAST(f.admon AS BIGINT), 0)
                           + ISNULL(CAST(f.afiliacion AS BIGINT), 0)) as base'),
                DB::raw('COUNT(*) as cuantas'),
            ]);

        $salida = [];
        foreach ($filas as $f) {
            $salida[(int) $f->mes] = ['base' => (float) $f->base, 'n' => (int) $f->cuantas];
        }

        return $salida;
    }

    /**
     * Lo que la base vieja registró en estas cuentas y la migración perdió.
     *
     * Devuelve `null` cuando no aplica — que es casi siempre. Solo entra en
     * juego si la cuenta pertenece al aliado dueño de la base legacy
     * configurada y trae `id_legacy`; ver `config/legacy.php` sobre por qué
     * esa guarda no es opcional.
     *
     * El resultado se cachea: son meses cerrados que ya no cambian, y la base
     * vieja vive en otro servidor al que no vale la pena pegarle en cada
     * recarga de la ficha. Si no responde, la ficha se pinta igual sin ella.
     *
     * @param  array<int,object>  $cuentas
     */
    private function movimientosLegacy(array $cuentas, int $anio): ?array
    {
        $aliadoLegacy = (int) config('legacy.aliado_id');

        $cuentasLegacy = array_values(array_filter(
            $cuentas,
            fn ($c) => (int) $c->aliado_id === $aliadoLegacy && $c->id_legacy !== null && $c->id_legacy !== ''
        ));

        if (! $cuentasLegacy || $anio > (int) substr((string) config('legacy.corte'), 0, 4)) {
            return null;
        }

        $llaves = array_map(fn ($c) => (string) $c->id_legacy, $cuentasLegacy);
        $clave = 'legacy.movimientos.'.$anio.'.'.implode('-', $llaves);

        return Cache::remember($clave, now()->addHours(12), function () use ($llaves, $anio) {
            try {
                $filas = DB::connection(config('legacy.conexion'))->select(
                    'SELECT MONTH(Fecha_Pago) mes,
                            COUNT(*) n,
                            SUM(ISNULL(TRY_CAST(Valor_Consignado AS float), 0)) entradas,
                            SUM(ISNULL(TRY_CAST(Admon AS float), 0)
                              + ISNULL(TRY_CAST(Afiliaciones AS float), 0)) base
                       FROM FACTURACION
                      WHERE Consignacion IN ('.implode(',', array_fill(0, count($llaves), '?')).')
                        AND YEAR(Fecha_Pago) = ?
                      GROUP BY MONTH(Fecha_Pago)',
                    array_merge($llaves, [$anio])
                );
            } catch (\Throwable $e) {
                Log::warning('razones sociales: la base legacy no respondió', ['error' => $e->getMessage()]);

                return null;
            }

            $meses = [];
            foreach ($filas as $f) {
                $meses[(int) $f->mes] = [
                    'entradas' => (float) $f->entradas,
                    'n_entradas' => (int) $f->n,
                    'base' => (float) $f->base,
                    'n_base' => (int) $f->n,
                ];
            }

            return ['meses' => $meses, 'cuentas' => $llaves];
        });
    }

    private function mesesEnCero(): array
    {
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $meses[$m] = [
                'entradas' => 0.0, 'salidas' => 0.0, 'base' => 0.0,
                'n_entradas' => 0, 'n_salidas' => 0, 'n_base' => 0,
                'neto' => 0.0, 'acumulado' => 0.0, 'legacy' => false,
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
