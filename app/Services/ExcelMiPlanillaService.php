<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\DB;

class ExcelMiPlanillaService
{
    private const HEADERS = [
        'TIPO ADMIN', 'CODIGO', 'DESCRIPCION', 'TIPO DOC', 'DOC', 'PRIMER APELLIDO', 'SEGUNDO APELLIDO',
        'PRIMER NOMBRE', 'SEGUNDO NOMBRE', 'COLOMBIANO EN EL EXTERIOR', 'FECHA RADICACION EN EL EXTERIOR',
        'TOTAL A PAGAR ANTES DE IGE LMA IRP E INTERES MORA', 'COTIZACION OBLIGATORIA', 'SALARIO', 'IBC',
        'DIAS COTIZADOS', 'TARIFA', 'CLASE DE RIESGO', 'VALOR UPC', 'APORTE VOLUNTARIO AFILIADO',
        'APORTE VOLUNTARIO APORTANTE', 'APORTE FONDO SOLIDARIDAD', 'APORTE FONDO SUBSISTENCIA', 'TIPO COTIZANTE',
        'SUBTIPO COTIZANTE', 'CODIGO MUNICIPIO UBICACION LABORAL', 'CODIGO DPTO UBICACION LABORAL', 'TIPO DE SALARIO',
        'EXONERADO PAGO DE PARAFISCALES Y SALUD', 'ING', 'RET', 'TDE', 'TAE', 'TDP', 'TAP', 'VSP', 'VTE', 'VST',
        'SLN', 'IGE', 'LMA', 'VAC - LR', 'AVP', 'VCT', 'IRL', 'TIPO DE DOCUMENTO RESPONSABLE DE PAGO DE UPC ADICIONAL',
        'NUMERO DE DOCUMENTO RESPONSABLE DE PAGO DE UPC ADICIONAL', 'HORAS LABORADAS', 'FECHA INGRESO', 'FECHA RETIRO',
        'FECHA INICIO VSP', 'FECHA INICIO SLN', 'FECHA FIN SLN', 'FECHA INICIO IGE', 'FECHA FIN IGE', 'FECHA INICIO LMA',
        'FECHA FIN LMA', 'FECHA INICIO VAC - LR', 'FECHA FIN VAC - LR', 'FECHA INICIO VCT', 'FECHA FIN VCT',
        'FECHA INICIO IRL', 'FECHA FIN IRL'
    ];

    public function generar(array $params): Spreadsheet
    {
        $aliadoId      = $params['aliado_id'];
        $razonSocialId = $params['razon_social_id'];
        $mes           = (int) $params['mes'];
        $anio          = (int) $params['anio'];
        $nPlano        = (int) $params['n_plano'];

        // Obtener planos de tipo 'retiro' en la RS origen correspondientes al traslado (numero_factura = 0)
        $planosRetiro = DB::table('planos AS p')
            ->leftJoin('clientes AS cl', function ($join) use ($aliadoId) {
                $join->on('cl.cedula', '=', 'p.no_identifi')
                     ->where('cl.aliado_id', '=', $aliadoId);
            })
            ->leftJoin('pensiones AS afp_t', DB::raw('CAST(afp_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_afp'))
            ->leftJoin('eps AS eps_t', DB::raw('CAST(eps_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_eps'))
            ->leftJoin('cajas AS caj_t', DB::raw('CAST(caj_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_caja'))
            ->leftJoin('arls AS arl_m', DB::raw('CAST(arl_m.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_arl'))
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
            ->where('p.aliado_id', $aliadoId)
            ->where('p.razon_social_id', $razonSocialId)
            ->where('p.mes_plano', $mes)
            ->where('p.anio_plano', $anio)
            ->where('p.n_plano', $nPlano)
            ->where('p.tipo_reg', 'retiro')
            ->where('p.numero_factura', 0)
            ->whereNull('p.deleted_at')
            ->select([
                'p.*',
                'cl.primer_nombre AS cl_primer_nombre', 'cl.segundo_nombre AS cl_segundo_nombre',
                'cl.primer_apellido AS cl_primer_apellido', 'cl.segundo_apellido AS cl_segundo_apellido',
                'cl.tipo_doc AS cl_tipo_doc', 'cl.genero AS cl_genero',
                DB::raw("DATEDIFF(YEAR, cl.fecha_nacimiento, GETDATE()) AS edad_calculada"),
                DB::raw('afp_t.codigo AS codigo_afp'), DB::raw('afp_t.razon_social AS nombre_afp_t'),
                DB::raw('eps_t.codigo AS codigo_eps'), DB::raw('eps_t.nombre AS nombre_eps_t'),
                DB::raw('caj_t.codigo AS codigo_caj'), DB::raw("COALESCE(caj_t.razon_social, caj_t.nombre, '') AS nombre_caj_t"),
                DB::raw('arl_m.codigo AS codigo_arl_pila'), DB::raw('arl_m.nombre_arl AS nombre_arl_t'),
                'tm.es_tiempo_parcial',
            ])
            ->get();

        $spreadsheet = new Spreadsheet();
        // Traza invisible de quién exportó (propiedades del documento).
        app(TrazaArchivoService::class)->marcarExcel($spreadsheet);
        $spreadsheet->getProperties()
            ->setTitle('Planilla MiPlanilla — Traslado')
            ->setCreator('BryNex');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('MiPlanilla');

        // Escribir cabeceras
        foreach (self::HEADERS as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $header);
        }

        // Estilo de la cabecera (Color HSL adaptado azul oscuro elegante)
        $sheet->getStyle('A1:BK1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0C4DE']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $fila = 2;
        foreach ($planosRetiro as $planoRet) {
            // Buscar contrato de destino (el creado en el traslado)
            $contratoDestino = DB::table('contratos AS c')
                ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'c.razon_social_id')
                ->leftJoin('eps AS e', 'e.id', '=', 'c.eps_id')
                ->leftJoin('pensiones AS p_t', 'p_t.id', '=', 'c.pension_id')
                ->leftJoin('arls AS a', 'a.id', '=', 'c.arl_id')
                ->leftJoin('cajas AS cj', 'cj.id', '=', 'c.caja_id')
                ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
                ->where('c.aliado_id', $aliadoId)
                ->where(DB::raw('CAST(c.cedula AS VARCHAR(20))'), (string)$planoRet->no_identifi)
                ->where('c.observacion_afiliacion', 'like', "%Traslado desde RS #{$razonSocialId}%")
                ->where('c.estado', 'vigente')
                ->select([
                    'c.*',
                    'rs.razon_social AS rs_nombre',
                    'e.codigo AS eps_codigo', 'e.nombre AS eps_nombre',
                    'p_t.codigo AS pension_codigo', 'p_t.razon_social AS pension_nombre',
                    'a.codigo AS arl_codigo', 'a.nombre_arl AS arl_nombre',
                    'cj.codigo AS caja_codigo', DB::raw("COALESCE(cj.razon_social, cj.nombre, '') AS caja_nombre"),
                    'tm.es_tiempo_parcial',
                ])
                ->first();

            // Calcular valores para la fila de origen (reversión - negativa)
            $cRet = PilaCotizanteCalculator::calcular($planoRet);

            // Calcular valores para la fila de destino (ingreso - positiva)
            $planoDestRow = null;
            if ($contratoDestino) {
                $planoDestRow = (object)[
                    'tipo_doc'          => $planoRet->tipo_doc,
                    'no_identifi'       => $planoRet->no_identifi,
                    'tipo_modalidad_id' => $contratoDestino->tipo_modalidad_id ?? null,
                    'tipo_p'            => $contratoDestino->tipo_modalidad_id ?? null,
                    'primer_nombre'     => $planoRet->primer_nombre,
                    'segundo_nombre'    => $planoRet->segundo_nombre,
                    'primer_ape'        => $planoRet->primer_ape,
                    'segundo_ape'       => $planoRet->segundo_ape,
                    'cod_eps'           => $contratoDestino->eps_codigo ?? null,
                    'cod_eps_pila'      => $contratoDestino->eps_codigo ?? null,
                    'cod_afp'           => $contratoDestino->pension_codigo ?? null,
                    'cod_afp_pila'      => $contratoDestino->pension_codigo ?? null,
                    'cod_arl'           => $contratoDestino->arl_codigo ?? null,
                    'cod_arl_pila'      => $contratoDestino->arl_codigo ?? null,
                    'cod_caja'          => $contratoDestino->caja_codigo ?? null,
                    'cod_caja_pila'     => $contratoDestino->caja_codigo ?? null,
                    'salario_basico'    => $contratoDestino->salario ?? 0,
                    'num_dias'          => $planoRet->num_dias ?? 30,
                    'nivel_riesgo'      => $contratoDestino->n_arl ?? 1,
                    'fecha_ing'         => $contratoDestino->fecha_ingreso ?? null,
                    'fecha_ret'         => null,
                    'genero'            => $planoRet->cl_genero ?? null,
                    'edad_calculada'    => $planoRet->edad_calculada ?? null,
                    'es_tiempo_parcial' => $contratoDestino->es_tiempo_parcial ?? null,
                ];
            }

            $cIng = $planoDestRow ? PilaCotizanteCalculator::calcular($planoDestRow) : null;

            // ── GENERAR FILAS ──
            // Cada cotizante tendrá un par de filas (negativa y positiva) por cada subsistema activo
            $subsistemas = [
                'PENSION' => [
                    'check'      => fn($calc) => $calc['tienePension'] ?? false,
                    'codigo'     => fn($calc, $row) => $calc['codAfpPila'] ?? '',
                    'desc'       => fn($row, $isDest) => $isDest ? ($contratoDestino->pension_nombre ?? '') : ($planoRet->nombre_afp_t ?? ''),
                    'tarifa'     => fn($calc) => 0.1600000,
                    'aporte'     => fn($calc) => $calc['vAfp'] ?? 0,
                    'ibc'        => fn($calc) => $calc['ibcAfp'] ?? 0,
                    'dias'       => fn($calc) => $calc['diasPension'] ?? 30,
                    'clase'      => 1,
                ],
                'SALUD' => [
                    'check'      => fn($calc) => ($calc['tipoCotizante'] !== 23), // no aplica a estudiante K
                    'codigo'     => fn($calc, $row) => $calc['codEpsPila'] ?? '',
                    'desc'       => fn($row, $isDest) => $isDest ? ($contratoDestino->eps_nombre ?? '') : ($planoRet->nombre_eps_t ?? ''),
                    'tarifa'     => fn($calc) => (float)($calc['tarifaEpsStr'] ?? 0.04),
                    'aporte'     => fn($calc) => $calc['vEps'] ?? 0,
                    'ibc'        => fn($calc) => $calc['ibcEps'] ?? 0,
                    'dias'       => fn($calc) => $calc['diasSalud'] ?? 30,
                    'clase'      => 1,
                ],
                'RIESGOS' => [
                    'check'      => fn($calc) => true, // aplica a todos
                    'codigo'     => fn($calc, $row) => $calc['codArlPila'] ?? '',
                    'desc'       => fn($row, $isDest) => $isDest ? ($contratoDestino->arl_nombre ?? '') : ($planoRet->nombre_arl_t ?? ''),
                    'tarifa'     => fn($calc) => (float)($calc['tarifaArlDecimal'] ?? 0.00522),
                    'aporte'     => fn($calc) => $calc['vArl'] ?? 0,
                    'ibc'        => fn($calc) => $calc['ibcArl'] ?? 0,
                    'dias'       => fn($calc) => $calc['diasArl'] ?? 30,
                    'clase'      => fn($calc) => $calc['nivelRiesgo'] ?? 1,
                ],
                'CAJA' => [
                    'check'      => fn($calc) => ($calc['tipoCotizante'] !== 23),
                    'codigo'     => fn($calc, $row) => $calc['codCcfPila'] ?? '',
                    'desc'       => function($row, $isDest) use ($contratoDestino, $planoRet) {
                        if ($isDest) return $contratoDestino->caja_nombre ?? '';
                        if (!empty($planoRet->nombre_caj_t)) return $planoRet->nombre_caj_t;
                        if (trim($planoRet->cod_caja ?? '') === 'CCF68') {
                            return 'Comcaja Caja de Compensacion Fliar Campesina';
                        }
                        return '';
                    },
                    'tarifa'     => fn($calc) => 0.0400000,
                    'aporte'     => fn($calc) => $calc['vCcf'] ?? 0,
                    'ibc'        => fn($calc) => $calc['ibcCcf'] ?? 0,
                    'dias'       => fn($calc) => $calc['diasCcf'] ?? 30,
                    'clase'      => 1,
                ]
            ];

            foreach ($subsistemas as $subKey => $subConf) {
                // Fila Positiva (Ingreso)
                if ($cIng && $subConf['check']($cIng)) {
                    $this->escribirFila(
                        $sheet, $fila, $subKey, true, $cIng, $planoDestRow, $subConf, $planoRet, $contratoDestino
                    );
                    $fila++;
                }

                // Fila Negativa (Reversión)
                if ($subConf['check']($cRet)) {
                    $this->escribirFila(
                        $sheet, $fila, $subKey, false, $cRet, $planoRet, $subConf, $planoRet, $contratoDestino
                    );
                    $fila++;
                }
            }
        }

        // Auto-ajustar anchos de columnas
        foreach (range(1, count(self::HEADERS)) as $colIdx) {
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Formatos de celda
        if ($fila > 2) {
            $lastRow = $fila - 1;
            // Documento y códigos como texto
            $sheet->getStyle('C2:E' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            // IBC y Aportes numéricos con formato
            $sheet->getStyle('L2:M' . $lastRow)->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
            $sheet->getStyle('O2:O' . $lastRow)->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
            // Tarifa con 7 decimales como pidió el usuario
            $sheet->getStyle('Q2:Q' . $lastRow)->getNumberFormat()->setFormatCode('0.0000000');
            // Horas
            $sheet->getStyle('AV2:AV' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        }

        return $spreadsheet;
    }

    private function escribirFila(
        $sheet, int $fila, string $tipoAdmin, bool $isPositive, array $c, object $row, array $subConf, object $planoRet, ?object $contratoDestino
    ): void {
        $tarifaVal = $subConf['tarifa']($c);
        $aporteVal = $subConf['aporte']($c);
        $ibcVal    = $subConf['ibc']($c);
        $diasVal   = $subConf['dias']($c);

        if (!$isPositive) {
            $aporteVal = -$aporteVal;
            $ibcVal    = -$ibcVal;
        }

        // Mapear clase de riesgo
        $claseRiesgo = is_callable($subConf['clase']) ? $subConf['clase']($c) : $subConf['clase'];

        // Exonerado (S / N)
        $exonerado = $c['exonerado'] ?? 'N';

        // ING / RET: En el traslado de retiro, la fila positiva reporta la novedad de retiro y la negativa va limpia.
        $ingVal = '';
        $retVal = ($isPositive && $tipoAdmin !== 'RIESGOS') ? 'X' : '';

        // Horas laboradas
        // Sin aporte a CCF no se reportan horas (error `eo.val.2.636`): el
        // calculador ya devuelve 0 en los cotizantes 23 y 59.
        $horasVal = $isPositive ? ($c['horasLaboradas'] ?? 240) : 0;

        // Fechas
        $fechaIngreso = null;
        $fechaRetiro  = $isPositive ? ($planoRet->fecha_ret ?? null) : null;

        // Tipo / Subtipo cotizante
        $tipoCot = $c['tipoCotizante'] ?? 1;
        $subtipoCot = $c['subtipoCotizante'] ?? 0;

        $valores = [
            1  => $tipoAdmin,                                                // TIPO ADMIN
            2  => $subConf['codigo']($c, $row),                               // CODIGO
            3  => $subConf['desc']($row, $isPositive),                       // DESCRIPCION
            4  => strtoupper(trim($planoRet->tipo_doc ?? 'CC')),             // TIPO DOC
            5  => (string)$planoRet->no_identifi,                            // DOC
            6  => strtoupper($planoRet->primer_ape ?? ''),                   // PRIMER APELLIDO
            7  => strtoupper($planoRet->segundo_ape ?? ''),                  // SEGUNDO APELLIDO
            8  => strtoupper($planoRet->primer_nombre ?? ''),                // PRIMER NOMBRE
            9  => strtoupper($planoRet->segundo_nombre ?? ''),               // SEGUNDO NOMBRE
            10 => '',                                                        // COLOMBIANO EN EL EXTERIOR
            11 => '',                                                        // FECHA RADICACION EN EL EXTERIOR
            12 => $aporteVal,                                                // TOTAL A PAGAR ANTES DE IGE...
            13 => $aporteVal,                                                // COTIZACION OBLIGATORIA
            14 => '',                                                        // SALARIO
            15 => $ibcVal,                                                   // IBC
            16 => $diasVal,                                                  // DIAS COTIZADOS
            17 => $tarifaVal,                                                // TARIFA
            18 => $claseRiesgo,                                              // CLASE DE RIESGO
            19 => 0,                                                         // VALOR UPC
            20 => 0,                                                         // APORTE VOLUNTARIO AFILIADO
            21 => 0,                                                         // APORTE VOLUNTARIO APORTANTE
            22 => 0,                                                         // APORTE FONDO SOLIDARIDAD
            23 => 0,                                                         // APORTE FONDO SUBSISTENCIA
            24 => $tipoCot,                                                  // TIPO COTIZANTE
            25 => $subtipoCot,                                               // SUBTIPO COTIZANTE
            26 => $c['munCod'] ?? '001',                                     // CODIGO MUNICIPIO UBICACION LABORAL
            27 => $c['depCod'] ?? '76',                                      // CODIGO DPTO UBICACION LABORAL
            28 => ($c['tipoSalarioAplica'] ?? true)                          // TIPO DE SALARIO (vacío en 23, 51 y 59: PILA lo prohíbe)
                    ? (($row->tipo_p ?? 'F') === 'I' ? 'I' : 'F')
                    : '',
            29 => $exonerado,                                                // EXONERADO PAGO DE PARAFISCALES
            30 => $ingVal,                                                   // ING
            31 => $retVal,                                                   // RET
            32 => '', 33 => '', 34 => '', 35 => '',                          // TDE, TAE, TDP, TAP
            36 => '', 37 => '', 38 => '',                                    // VSP, VTE, VST
            39 => '', 40 => '', 41 => '',                                    // SLN, IGE, LMA
            42 => '', 43 => '', 44 => '', 45 => '',                          // VAC - LR, AVP, VCT, IRL
            46 => '',                                                        // TIPO DE DOCUMENTO RESPONSABLE UPC ADICIONAL
            47 => '',                                                        // NUMERO DE DOCUMENTO RESPONSABLE UPC ADICIONAL
            48 => $horasVal,                                                 // HORAS LABORADAS
            49 => $fechaIngreso ? substr($fechaIngreso, 0, 10) : '',         // FECHA INGRESO
            50 => $fechaRetiro ? substr($fechaRetiro, 0, 10) : '',           // FECHA RETIRO
            51 => '', 52 => '', 53 => '', 54 => '', 55 => '',                // fechas novedades
            56 => '', 57 => '', 58 => '', 59 => '', 60 => '',
            61 => '', 62 => '', 63 => ''
        ];

        foreach ($valores as $colIdx => $val) {
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$col}{$fila}", $val);
        }
    }

    public function respuesta(Spreadsheet $spreadsheet, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function respuestaCsv(Spreadsheet $spreadsheet, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('');
        $writer->setLineEnding("\r\n");

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
