<?php

namespace App\Services\Adres;

use Carbon\Carbon;

/**
 * Interpreta los períodos compensados de ADRES y saca hallazgos.
 *
 * OJO CON EL ALCANCE: este reporte es de COMPENSACIÓN EN SALUD. Dice en qué
 * meses una EPS compensó por la persona, no cuántas semanas de pensión tiene.
 * Las semanas de pensión las certifica el fondo (Colpensiones o la AFP), no
 * ADRES. Es un buen indicio de que hubo aporte, pero no es el histórico
 * pensional y no debe presentarse como tal.
 *
 * Todo hallazgo se redacta como observación de lo que aparece publicado, nunca
 * como veredicto sobre el operador que hizo el aporte. El propio aviso de ADRES
 * advierte que esta información es complemento y nunca criterio único; si un
 * hallazgo insinúa que un tercero está fallando, se marca requiere_asesor y lo
 * revisa una persona antes de decirle nada al cliente.
 */
class DiagnosticoCompensados
{
    /** La compensación va detrás del pago; los últimos meses aún no aparecen. */
    private const REZAGO_NORMAL_MESES = 2;

    private const DIAS_MES_COMPLETO = 30;

    /** Observaciones que NO representan un pago con cotización real. */
    private const OBSERVACIONES_SIN_COTIZACION = ['estado emergencia'];

    public const SEV_INFO     = 'info';
    public const SEV_ATENCION = 'atencion';
    public const SEV_ALTA     = 'alta';

    /**
     * @param  array<int, array{eps:string, periodo:string, anio:int, mes:int, dias:int, tipo_afiliado:?string, observacion:?string}>  $filas
     */
    public static function analizar(array $filas, ?Carbon $hoy = null): array
    {
        $hoy = $hoy ? $hoy->copy() : Carbon::now();

        if (empty($filas)) {
            return [
                'resumen'         => null,
                'hallazgos'       => [[
                    'codigo'    => 'sin_registros',
                    'severidad' => self::SEV_ALTA,
                    'titulo'    => 'No aparece ningún período compensado',
                    'detalle'   => 'ADRES no reporta compensaciones para este documento. Puede que nunca haya '
                        . 'cotizado como dependiente o independiente, o que esté afiliado al régimen subsidiado.',
                    'periodos'  => [],
                ]],
                'requiere_asesor' => true,
            ];
        }

        // Se ordena por fecha ascendente: ADRES los entrega del más reciente al
        // más viejo y todos los cálculos de continuidad asumen lo contrario.
        usort($filas, fn ($a, $b) => [$a['anio'], $a['mes']] <=> [$b['anio'], $b['mes']]);

        $primera = $filas[0];
        $ultima  = $filas[count($filas) - 1];

        $ultimoPeriodo = Carbon::create($ultima['anio'], $ultima['mes'], 1)->startOfMonth();
        $rezago = $ultimoPeriodo->diffInMonths($hoy->copy()->startOfMonth());

        $hallazgos = array_merge(
            self::mesesIncompletos($filas),
            self::huecos($filas, $hoy),
            self::observacionesSinCotizacion($filas),
            self::posibleInactividad($rezago, $ultima),
            self::cambiosDeEps($filas),
        );

        // Más severo primero: es el orden en que hay que contárselo a la persona.
        $peso = [self::SEV_ALTA => 0, self::SEV_ATENCION => 1, self::SEV_INFO => 2];
        usort($hallazgos, fn ($a, $b) => $peso[$a['severidad']] <=> $peso[$b['severidad']]);

        $requiereAsesor = (bool) array_filter($hallazgos, fn ($h) => $h['severidad'] === self::SEV_ALTA);

        return [
            'resumen' => [
                'eps_actual'      => $ultima['eps'],
                'tipo_afiliado'   => $ultima['tipo_afiliado'],
                'primer_periodo'  => $primera['periodo'],
                'ultimo_periodo'  => $ultima['periodo'],
                'rezago_meses'    => $rezago,
                'meses_con_aporte' => count($filas),
                'dias_totales'    => array_sum(array_column($filas, 'dias')),
                'eps_historicas'  => array_values(array_unique(array_column($filas, 'eps'))),
            ],
            'hallazgos'       => array_values($hallazgos),
            'requiere_asesor' => $requiereAsesor,
        ];
    }

    /** Meses pagados por menos de 30 días: cobertura parcial en ese mes. */
    private static function mesesIncompletos(array $filas): array
    {
        $incompletos = array_values(array_filter($filas, fn ($f) => $f['dias'] < self::DIAS_MES_COMPLETO));
        if (!$incompletos) {
            return [];
        }

        $etiquetas = array_map(fn ($f) => "{$f['periodo']} ({$f['dias']} días)", $incompletos);

        return [[
            'codigo'    => 'meses_incompletos',
            'severidad' => self::SEV_ALTA,
            'titulo'    => count($incompletos) === 1
                ? 'Hay un mes pagado incompleto'
                : 'Hay ' . count($incompletos) . ' meses pagados incompletos',
            'detalle'   => 'En estos meses aparecen menos de 30 días compensados, es decir que el aporte no '
                . 'cubrió el mes entero: ' . implode(', ', $etiquetas) . '.',
            'periodos'  => array_column($incompletos, 'periodo'),
        ]];
    }

    /**
     * Meses sin ningún registro entre el primero y el último.
     *
     * No siempre es un error: quien dejó de trabajar un tiempo simplemente no
     * cotizó. Por eso se redacta como "no aparece", no como "le fallaron".
     */
    private static function huecos(array $filas, Carbon $hoy): array
    {
        $presentes = [];
        foreach ($filas as $f) {
            $presentes[sprintf('%04d-%02d', $f['anio'], $f['mes'])] = true;
        }

        $cursor = Carbon::create($filas[0]['anio'], $filas[0]['mes'], 1)->startOfMonth();
        $fin    = Carbon::create(
            $filas[count($filas) - 1]['anio'],
            $filas[count($filas) - 1]['mes'],
            1
        )->startOfMonth();

        $faltantes = [];
        while ($cursor->lessThan($fin)) {
            $cursor->addMonth();
            if ($cursor->greaterThanOrEqualTo($fin)) {
                break;
            }
            $clave = $cursor->format('Y-m');
            if (!isset($presentes[$clave])) {
                $faltantes[] = $cursor->format('m/Y');
            }
        }

        if (!$faltantes) {
            return [];
        }

        $muestra = array_slice($faltantes, 0, 12);
        $resto   = count($faltantes) - count($muestra);

        return [[
            'codigo'    => 'periodos_faltantes',
            'severidad' => self::SEV_ATENCION,
            'titulo'    => count($faltantes) . ' meses sin aporte registrado',
            'detalle'   => 'Entre el primer y el último período aparecen meses sin ninguna compensación: '
                . implode(', ', $muestra)
                . ($resto > 0 ? " y {$resto} más" : '')
                . '. Puede deberse a períodos sin trabajar, no necesariamente a un error.',
            'periodos'  => $faltantes,
        ]];
    }

    /**
     * "Estado Emergencia" (art. 15, Decreto 538 de 2020) aparece como período
     * compensado pero, según la propia nota de ADRES, no tiene pago ni cotización
     * detrás. Es el hallazgo que nadie revisa: meses que parecen cubiertos y no lo
     * están.
     */
    private static function observacionesSinCotizacion(array $filas): array
    {
        $sospechosos = array_values(array_filter($filas, function ($f) {
            $obs = mb_strtolower(trim((string) ($f['observacion'] ?? '')));
            foreach (self::OBSERVACIONES_SIN_COTIZACION as $sin) {
                if (str_contains($obs, $sin)) {
                    return true;
                }
            }
            return false;
        }));

        if (!$sospechosos) {
            return [];
        }

        return [[
            'codigo'    => 'periodos_sin_cotizacion',
            'severidad' => self::SEV_ALTA,
            'titulo'    => count($sospechosos) . ' meses figuran cubiertos pero sin cotización',
            'detalle'   => 'Aparecen marcados como "Estado Emergencia". La nota de ADRES aclara que estos '
                . 'afiliados no cuentan con un pago o cotización al sistema, aunque el período figure compensado.',
            'periodos'  => array_column($sospechosos, 'periodo'),
        ]];
    }

    private static function posibleInactividad(int $rezago, array $ultima): array
    {
        if ($rezago <= self::REZAGO_NORMAL_MESES) {
            return [];
        }

        return [[
            'codigo'    => 'posible_inactividad',
            'severidad' => $rezago >= 6 ? self::SEV_ALTA : self::SEV_ATENCION,
            'titulo'    => "El último período compensado es {$ultima['periodo']}",
            'detalle'   => "Hace {$rezago} meses que no aparece una compensación nueva. La compensación va "
                . 'un mes o dos detrás del pago, así que un rezago corto es normal; este ya es más largo y '
                . 'sugiere que la afiliación no está activa.',
            'periodos'  => [$ultima['periodo']],
        ]];
    }

    private static function cambiosDeEps(array $filas): array
    {
        $transiciones = [];
        for ($i = 1, $n = count($filas); $i < $n; $i++) {
            if ($filas[$i]['eps'] !== $filas[$i - 1]['eps']) {
                $transiciones[] = "{$filas[$i - 1]['eps']} → {$filas[$i]['eps']} en {$filas[$i]['periodo']}";
            }
        }

        if (!$transiciones) {
            return [];
        }

        return [[
            'codigo'    => 'cambios_de_eps',
            'severidad' => self::SEV_INFO,
            'titulo'    => count($transiciones) === 1 ? 'Hubo un cambio de EPS' : 'Hubo ' . count($transiciones) . ' cambios de EPS',
            'detalle'   => implode('; ', $transiciones) . '.',
            'periodos'  => [],
        ]];
    }
}
