<?php

namespace App\Services;

use App\Models\ConfiguracionBrynex;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio central para el cálculo de mora al cliente.
 *
 * La mora al cliente es la penalidad que el aliado le cobra a su cliente
 * por pagar tarde la factura de Seguridad Social. Es independiente de la
 * mora PILA (que paga el aliado al operador PILA cuando lleva tarde la planilla).
 *
 * Normativa base: Decreto 1990 de 2016 | Art. 635 Estatuto Tributario
 */
class MoraClienteService
{
    /**
     * Tabla legal de días hábiles de vencimiento PILA por últimos 2 dígitos del NIT.
     * Decreto 1990 de 2016 → tabla: últimos dígitos → día hábil.
     */
    private const TABLA_DIA_HABIL = [
        [0,  7,  2],
        [8,  14, 3],
        [15, 21, 4],
        [22, 28, 5],
        [29, 35, 6],
        [36, 42, 7],
        [43, 49, 8],
        [50, 56, 9],
        [57, 63, 10],
        [64, 69, 11],
        [70, 75, 12],
        [76, 81, 13],
        [82, 87, 14],
        [88, 93, 15],
        [94, 99, 16],
    ];

    /**
     * Festivos colombianos fijos + móviles aproximados (Ley Emiliani).
     * Para mayor precisión en producción, conectar a una API de festivos.
     *
     * @return array<string> Fechas en formato Y-m-d
     */
    public static function festivosColombia(int $anio): array
    {
        return [
            // Fijos
            "{$anio}-01-01", "{$anio}-05-01", "{$anio}-07-04",
            "{$anio}-07-20", "{$anio}-08-07", "{$anio}-12-08", "{$anio}-12-25",
            // Móviles aproximados (Ley Emiliani — varían cada año)
            "{$anio}-01-06", "{$anio}-03-23", "{$anio}-03-24",
            "{$anio}-05-29", "{$anio}-06-19", "{$anio}-06-23",
            "{$anio}-06-30", "{$anio}-08-18", "{$anio}-10-13",
            "{$anio}-11-03", "{$anio}-11-17",
        ];
    }

    /**
     * Determina el N-ésimo día hábil de un mes/año dado.
     * Excluye sábados, domingos y festivos colombianos.
     */
    public static function getNthDiaHabil(int $anio, int $mes, int $n): ?Carbon
    {
        $festivos = array_flip(self::festivosColombia($anio));
        $fecha    = Carbon::create($anio, $mes, 1);
        $cont     = 0;

        while ($fecha->month === $mes) {
            $dow = $fecha->dayOfWeek; // 0=domingo, 6=sábado
            $key = $fecha->format('Y-m-d');

            if ($dow !== Carbon::SUNDAY && $dow !== Carbon::SATURDAY && !isset($festivos[$key])) {
                $cont++;
                if ($cont === $n) {
                    return $fecha->copy();
                }
            }
            $fecha->addDay();
        }

        return null; // mes muy corto o n demasiado grande
    }

    /**
     * Obtiene el día hábil de vencimiento para una razón social y aliado dados.
     *
     * Prioridad:
     * 1. Si el aliado configuró mora_dia_habil_inicio → ese día para TODOS sus clientes.
     * 2. Si la RS tiene dia_habil configurado directamente → usarlo.
     * 3. Calcular por últimos 2 dígitos del NIT de la RS (tabla Decreto 1990/2016).
     *
     * @param int   $aliadoId
     * @param int   $rsNit          NIT real de la razón social (columna nit o id)
     * @param int|null $rsDiaHabil  Día hábil ya configurado en la RS (null = calcular)
     * @return int Número de día hábil (2–16)
     */
    public static function diaHabilVencimiento(int $aliadoId, int $rsNit, ?int $rsDiaHabil = null): int
    {
        // 1. Configuración global del aliado (mora_dia_habil_inicio)
        $cfgAliado = DB::table('configuracion_aliado')
            ->where('aliado_id', $aliadoId)
            ->whereNull('plan_id')
            ->first(['mora_dia_habil_inicio']);

        if ($cfgAliado && !is_null($cfgAliado->mora_dia_habil_inicio)) {
            return (int) $cfgAliado->mora_dia_habil_inicio;
        }

        // 2. Día hábil guardado directamente en la RS
        if (!is_null($rsDiaHabil) && $rsDiaHabil >= 2 && $rsDiaHabil <= 16) {
            return $rsDiaHabil;
        }

        // 3. Calcular por últimos 2 dígitos del NIT
        $ultDos = abs($rsNit) % 100;
        foreach (self::TABLA_DIA_HABIL as [$desde, $hasta, $dia]) {
            if ($ultDos >= $desde && $ultDos <= $hasta) {
                return $dia;
            }
        }

        return 2; // fallback mínimo legal
    }

    /**
     * Obtiene la configuración de tramos de mora para un aliado.
     *
     * @return array{mora_minimo: int, mora_segundo: int}
     */
    public static function tramosAliado(int $aliadoId): array
    {
        $cfg = DB::table('configuracion_aliado')
            ->where('aliado_id', $aliadoId)
            ->whereNull('plan_id')
            ->first(['mora_minimo', 'mora_segundo']);

        return [
            'mora_minimo'  => (int) ($cfg->mora_minimo  ?? 2000),
            'mora_segundo' => (int) ($cfg->mora_segundo ?? 5000),
        ];
    }

    /**
     * Aplica los tramos de mora configurados por el aliado.
     *
     * Tramos:
     *   mora_real < mora_minimo  → cobrar mora_minimo
     *   mora_real < mora_segundo → cobrar mora_segundo
     *   mora_real >= mora_segundo → cobrar mora_real
     *
     * @param float $moraReal Mora calculada matemáticamente
     * @param int   $aliadoId
     * @return int  Mora final a cobrar (redondeada a entero)
     */
    public static function aplicarTramos(float $moraReal, int $aliadoId): int
    {
        if ($moraReal <= 0) {
            return 0;
        }

        ['mora_minimo' => $min, 'mora_segundo' => $seg] = self::tramosAliado($aliadoId);

        if ($moraReal < $min) {
            return $min;
        }
        if ($moraReal < $seg) {
            return $seg;
        }
        return (int) round($moraReal);
    }

    /**
     * Calcula la mora completa para un cliente dado su total SS y su RS.
     *
     * @param int      $aliadoId
     * @param int      $rsNit       NIT de la razón social (columna nit o id)
     * @param int|null $rsDiaHabil  Día hábil configurado en la RS (null = calcular)
     * @param float    $totalSS     Total de seguridad social del cliente
     * @param int      $mes         Mes del periodo de PAGO (mes del filtro UI)
     * @param int      $anio        Año del periodo de PAGO
     * @param Carbon|null $fechaHoy Fecha de referencia (default: today). Para tests.
     *
     * @return array{
     *   mora: int,
     *   mora_real: float,
     *   dias_mora: int,
     *   fecha_vence: Carbon|null,
     *   dia_habil: int,
     *   aplica: bool
     * }
     */
    public static function calcular(
        int $aliadoId,
        int $rsNit,
        ?int $rsDiaHabil,
        float $totalSS,
        int $mes,
        int $anio,
        ?Carbon $fechaHoy = null
    ): array {
        $hoy       = ($fechaHoy ?? Carbon::today())->startOfDay();
        $diaHabil  = self::diaHabilVencimiento($aliadoId, $rsNit, $rsDiaHabil);
        $fechaVence = self::getNthDiaHabil($anio, $mes, $diaHabil);

        $resultado = [
            'mora'        => 0,
            'mora_real'   => 0.0,
            'dias_mora'   => 0,
            'fecha_vence' => $fechaVence,
            'dia_habil'   => $diaHabil,
            'aplica'      => false,
        ];

        if (!$fechaVence || $totalSS <= 0) {
            return $resultado;
        }

        $diasMora = (int) $hoy->diffInDays($fechaVence, false); // negativo = mora
        $diasMora = $diasMora < 0 ? abs($diasMora) : 0;         // solo positivo si está en mora

        if ($diasMora <= 0) {
            return $resultado;
        }

        // Tasa de mora configurada en BryNex (Art. 635 ET)
        $tasaAnual = (float) ConfiguracionBrynex::obtener('tasa_mora_pila', 26.17);
        $diasAnio  = (int) $fechaVence->format('L') === 1 ? 366 : 365; // bisiesto
        $moraReal  = $totalSS * ($tasaAnual / 100) / $diasAnio * $diasMora;

        $resultado['mora_real'] = round($moraReal, 2);
        $resultado['dias_mora'] = $diasMora;
        $resultado['aplica']    = true;
        $resultado['mora']      = self::aplicarTramos($moraReal, $aliadoId);

        return $resultado;
    }

    /**
     * Calcula mora para múltiples facturas (uso en vistas empresa/cobros).
     * Agrupa por razon_social_id para minimizar queries.
     *
     * @param int   $aliadoId
     * @param array $filas  Cada fila debe tener: rs_nit, rs_dia_habil, total_ss, mes, anio
     * @return array  Misma estructura con campo 'mora' agregado a cada fila
     */
    public static function calcularLote(int $aliadoId, array $filas): array
    {
        // Pre-cargar config del aliado (1 query para todos)
        $cfg = DB::table('configuracion_aliado')
            ->where('aliado_id', $aliadoId)
            ->whereNull('plan_id')
            ->first();

        $diaHabilGlobal = $cfg?->mora_dia_habil_inicio ? (int) $cfg->mora_dia_habil_inicio : null;
        $moraMinimo     = (int) ($cfg->mora_minimo  ?? 2000);
        $moraSeg        = (int) ($cfg->mora_segundo ?? 5000);
        $tasaAnual      = (float) ConfiguracionBrynex::obtener('tasa_mora_pila', 26.17);
        $hoy            = Carbon::today()->startOfDay();

        return array_map(function ($fila) use (
            $aliadoId, $diaHabilGlobal, $moraMinimo, $moraSeg, $tasaAnual, $hoy
        ) {
            $fila = (array) $fila;

            $totalSS    = (float) ($fila['total_ss'] ?? 0);
            $mes        = (int) ($fila['mes']        ?? now()->month);
            $anio       = (int) ($fila['anio']       ?? now()->year);
            $rsNit      = (int) ($fila['rs_nit']     ?? 0);
            $rsDiaHabil = isset($fila['rs_dia_habil']) ? (int) $fila['rs_dia_habil'] : null;

            if ($totalSS <= 0) {
                $fila['mora'] = 0;
                $fila['mora_aplica'] = false;
                return $fila;
            }

            // Determinar día hábil
            if (!is_null($diaHabilGlobal)) {
                $diaH = $diaHabilGlobal;
            } elseif (!is_null($rsDiaHabil) && $rsDiaHabil >= 2 && $rsDiaHabil <= 16) {
                $diaH = $rsDiaHabil;
            } else {
                $ultDos = abs($rsNit) % 100;
                $diaH   = 2;
                foreach (self::TABLA_DIA_HABIL as [$desde, $hasta, $dia]) {
                    if ($ultDos >= $desde && $ultDos <= $hasta) { $diaH = $dia; break; }
                }
            }

            $fechaVence = self::getNthDiaHabil($anio, $mes, $diaH);
            if (!$fechaVence) {
                $fila['mora'] = 0; $fila['mora_aplica'] = false; return $fila;
            }

            $diasMora = (int) $hoy->diffInDays($fechaVence, false);
            $diasMora = $diasMora < 0 ? abs($diasMora) : 0;

            if ($diasMora <= 0) {
                $fila['mora'] = 0; $fila['mora_aplica'] = false; return $fila;
            }

            $diasAnio = (int) $fechaVence->format('L') === 1 ? 366 : 365;
            $moraReal = $totalSS * ($tasaAnual / 100) / $diasAnio * $diasMora;

            $mora = $moraReal < $moraMinimo ? $moraMinimo
                  : ($moraReal < $moraSeg  ? $moraSeg
                  : (int) round($moraReal));

            $fila['mora']         = $mora;
            $fila['mora_real']    = round($moraReal, 2);
            $fila['mora_dias']    = $diasMora;
            $fila['mora_aplica']  = true;
            $fila['mora_dia_hab'] = $diaH;
            $fila['fecha_vence']  = $fechaVence->format('Y-m-d');

            return $fila;
        }, $filas);
    }
}
