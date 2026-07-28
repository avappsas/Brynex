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

    /**
     * Observaciones que NO representan un pago con cotización real.
     *
     * Es una expresión regular y no una cadena fija porque ADRES publica el valor
     * con un typo suyo: aparece literalmente "Estado Emeregencia", con una 'e' de
     * más metida justo entre la "emer" y la "g". Ni "estado emergencia" ni
     * siquiera "emerg" enganchan con eso. El comodín del medio cubre las dos
     * grafías y cualquier variante parecida que aparezca después.
     */
    private const PATRON_SIN_COTIZACION = '/emer.{0,2}g/';

    /** Observación que sí corresponde a un aporte real. */
    private const PATRON_CON_COTIZACION = 'cotizacion';

    public const SEV_INFO     = 'info';
    public const SEV_ATENCION = 'atencion';
    public const SEV_ALTA     = 'alta';

    /** Minúsculas y sin tildes, para comparar observaciones sin depender de cómo las escriba ADRES. */
    private static function normalizar(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));

        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    private static function esSinCotizacion(?string $observacion): bool
    {
        return (bool) preg_match(self::PATRON_SIN_COTIZACION, self::normalizar($observacion));
    }

    private static function esObservacionConocida(?string $observacion): bool
    {
        $obs = self::normalizar($observacion);

        return $obs === ''
            || str_contains($obs, self::PATRON_CON_COTIZACION)
            || self::esSinCotizacion($observacion);
    }

    /**
     * Junta las filas por mes.
     *
     * Un mismo período puede traer varias filas (se vio 06/2020 con 27 días de
     * "Estado Emeregencia" más 1 día de "Pago con cotización"). Sin agrupar, ese
     * mes se reportaba como dos meses incompletos distintos y el conteo de meses
     * cotizados salía inflado.
     *
     * Se llevan dos totales por separado: los días que figuran compensados y los
     * que tienen un aporte real detrás. La diferencia entre ambos es lo que hace
     * que un mes parezca cubierto sin estarlo.
     */
    private static function agruparPorPeriodo(array $filas): array
    {
        $meses = [];

        foreach ($filas as $f) {
            $clave = sprintf('%04d-%02d', $f['anio'], $f['mes']);

            if (!isset($meses[$clave])) {
                $meses[$clave] = [
                    'periodo'        => $f['periodo'],
                    'anio'           => $f['anio'],
                    'mes'            => $f['mes'],
                    'dias'           => 0,
                    'dias_cotizados' => 0,
                    'eps'            => $f['eps'],
                    'tipo_afiliado'  => $f['tipo_afiliado'],
                    'observaciones'  => [],
                ];
            }

            $meses[$clave]['dias'] += (int) $f['dias'];
            if (!self::esSinCotizacion($f['observacion'])) {
                $meses[$clave]['dias_cotizados'] += (int) $f['dias'];
            }
            $meses[$clave]['observaciones'][] = $f['observacion'];
        }

        ksort($meses);

        return array_values($meses);
    }

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

        // ADRES entrega del más reciente al más viejo, y todos los cálculos de
        // continuidad asumen lo contrario. Además se juntan las filas del mismo
        // mes: un período puede venir partido en varias.
        $meses = self::agruparPorPeriodo($filas);

        $primera = $meses[0];
        $ultima  = $meses[count($meses) - 1];

        $ultimoPeriodo = Carbon::create($ultima['anio'], $ultima['mes'], 1)->startOfMonth();
        $rezago = $ultimoPeriodo->diffInMonths($hoy->copy()->startOfMonth());

        $hallazgos = array_merge(
            self::mesesIncompletos($meses),
            self::huecos($meses, $hoy),
            self::observacionesSinCotizacion($meses),
            self::posibleInactividad($rezago, $ultima),
            self::cambiosDeEps($meses),
            self::observacionesDesconocidas($meses),
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
                // Meses distintos, no filas: un mismo período puede venir partido.
                'meses_con_aporte' => count($meses),
                'dias_totales'    => array_sum(array_column($meses, 'dias')),
                'dias_cotizados'  => array_sum(array_column($meses, 'dias_cotizados')),
                'eps_historicas'  => array_values(array_unique(array_column($meses, 'eps'))),
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
    private static function observacionesSinCotizacion(array $meses): array
    {
        $sospechosos = array_values(array_filter($meses, fn ($m) => $m['dias'] > $m['dias_cotizados']));

        if (!$sospechosos) {
            return [];
        }

        $diasSinAporte = array_sum(array_map(fn ($m) => $m['dias'] - $m['dias_cotizados'], $sospechosos));

        $etiquetas = array_map(
            fn ($m) => "{$m['periodo']} (" . ($m['dias'] - $m['dias_cotizados']) . ' de ' . $m['dias'] . ' días)',
            array_slice($sospechosos, 0, 6)
        );

        return [[
            'codigo'    => 'periodos_sin_cotizacion',
            'severidad' => self::SEV_ALTA,
            'titulo'    => count($sospechosos) === 1
                ? 'Hay un mes que figura cubierto sin cotización'
                : 'Hay ' . count($sospechosos) . ' meses que figuran cubiertos sin cotización',
            'detalle'   => "Son {$diasSinAporte} días marcados como \"Estado Emergencia\" (art. 15 del Decreto 538 "
                . 'de 2020). La nota de ADRES aclara que esos afiliados no cuentan con un pago ni cotización al '
                . 'sistema, aunque el período aparezca compensado: ' . implode(', ', $etiquetas) . '.',
            'periodos'  => array_column($sospechosos, 'periodo'),
        ]];
    }

    /**
     * Observaciones que no reconocemos.
     *
     * El catálogo real de ADRES no está documentado y ya trae sorpresas (publica
     * "Estado Emeregencia", con typo). En vez de asumir que todo lo desconocido
     * es un aporte normal, se marca para que lo mire una persona: es preferible
     * revisar de más a decirle a alguien que está al día cuando no lo está.
     */
    private static function observacionesDesconocidas(array $meses): array
    {
        $raras = [];
        foreach ($meses as $m) {
            foreach ($m['observaciones'] as $obs) {
                if (!self::esObservacionConocida($obs)) {
                    $raras[$m['periodo']] = trim((string) $obs);
                }
            }
        }

        if (!$raras) {
            return [];
        }

        $valores = array_values(array_unique($raras));

        return [[
            'codigo'    => 'observacion_desconocida',
            'severidad' => self::SEV_ALTA,
            'titulo'    => 'Hay observaciones que no reconozco',
            'detalle'   => 'ADRES reporta valores que no están en el catálogo conocido: "'
                . implode('", "', array_slice($valores, 0, 5)) . '". No se puede concluir nada sobre esos meses '
                . 'sin que los revise una persona.',
            'periodos'  => array_keys($raras),
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
