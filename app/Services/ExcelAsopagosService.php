<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;

class ExcelAsopagosService
{
    private const SECCION_INFO = [
        'TIPO DOCUMENTO','NUMERO DOCUMENTO','TIPO COTIZANTE','SUBTIPO DE COTIZANTE',
        'EXTRANJERO NO OBLIGADO A COTIZAR PENSIÓN','COLOMBIANO EN EL EXTERIOR',
        'FECHA DE RADICACIÓN EN EL EXTERIOR','EXONERADO',
        'CÓDIGO DE DEPARTAMENTO','CÓDIGO DE MUNICIPIO',
        'PRIMER APELLIDO','SEGUNDO APELLIDO','PRIMER NOMBRE','SEGUNDO NOMBRE',
        'SALARIO BÁSICO','SALARIO INTEGRAL',
    ];
    private const SECCION_NOVEDADES = [
        'ING','FECHA INGRESO','RET','FECHA RETIRO',
        'TOE','TAE','EPS A LA QUE SE TRASLADA',
        'TOP','TAP','AFP A LA QUE SE TRASLADA',
        'VSP','VSP F_INICIO','VSP F_FIN','VST',
        'SLN','SLN F_INICIO','SLN F_FIN',
        'IGE','IGE F_INICIO','IGE F_FIN',
        'LMA','LMA F_INICIO','LMA F_FIN',
        'VAC-LR','VAC F_INICIO','VAC F_FIN',
        'VCT','VCT F_INICIO','VCT F_FIN',
        'IRL','IRL F_INICIO','IRL F_FIN',
    ];
    private const SECCION_SALUD = [
        'EPS','DÍAS COTIZADOS SALUD','IBC SALUD','TARIFA SALUD',
        'COTIZACIÓN EPS','VALOR UPC',
        'TIPO DE DOCUMENTO DEL COTIZANTE PRINCIPAL UPC',
        'NUMERO DE DOCUMENTO COTIZANTE PRINCIPAL UPC',
    ];
    private const SECCION_PENSION = [
        'INDICADOR TARIFA ESPECIAL','AFP',
        'DÍAS COTIZADOS PENSIÓN','IBC PENSIÓN','TARIFA PENSIÓN',
        'COTIZACIÓN AFP','APORTE VOLUNTARIO DEL AFILIADO',
        'APORTE VOLUNTARIO DEL APORTANTE','VALOR NO RETENIDO',
    ];
    private const SECCION_RIESGOS = [
        'ARL AFILIADO','DÍAS COTIZADOS RIESGOS','IBC RIESGOS',
        'CENTRO DE TRABAJO','CLASE DE RIESGO','TARIFA RIESGOS','COTIZACIÓN ARL',
    ];
    private const SECCION_CCF = [
        'CCF','IBC CCF','TARIFA CCF','COTIZACIÓN CCF',
    ];
    private const SECCION_PARAFISCALES = [
        'IBC OTROS PARAFISCALES','TARIFA SENA','COTIZACIÓN SENA',
        'TARIFA ICBF','COTIZACIÓN ICBF','TARIFA ESAP','COTIZACIÓN ESAP',
        'TARIFA MIN','COTIZACIÓN MIN','HORAS LABORADAS','ACTIVIDAD ECONÓMICA',
    ];

    private const COLOR_SECCION  = '1E3A5F';
    private const COLOR_HEADER   = '2563EB';
    private const COLOR_AMARILLO = 'FACC15';

    // Columnas con fondo amarillo (cotizaciones calculadas)
    // 53=COT EPS, 62=COT AFP, 72=COT ARL, 76=COT CCF, 79=COT SENA, 81=COT ICBF, 83=COT ESAP, 85=COT MIN
    private const COLS_AMARILLO = [53, 62, 72, 76, 79, 81, 83, 85];

    // ── Redondear al cien superior ──────────────────────────────────────────
    private function cienSuperior(int $valor): int
    {
        if ($valor <= 0) return 0;
        return (int)(ceil($valor / 100) * 100);
    }

    public function generar(array $params): Spreadsheet
    {
        $aliadoId      = $params['aliado_id'];
        $razonSocialId = $params['razon_social_id'];
        $mesPago       = (int) $params['mes'];
        $anioPago      = (int) $params['anio'];
        $nPlano        = (int) $params['n_plano'];
        $tiposModal    = $params['tipos_modalidad'] ?? [];

        $mesVencido  = $mesPago > 1 ? $mesPago - 1 : 12;
        $anioVencido = $mesPago > 1 ? $anioPago    : $anioPago - 1;

        $rs = DB::table('razones_sociales')
            ->where('id', $razonSocialId)
            ->where('aliado_id', $aliadoId)
            ->first();

        if (!$rs) {
            throw new \RuntimeException("Razón social {$razonSocialId} no encontrada.");
        }

        // Código ARL de la razón social como fallback
        $codigoArlRs = null;
        if (!empty($rs->arl_nit)) {
            $codigoArlRs = DB::table('arls')
                ->where(DB::raw('CAST(nit AS VARCHAR(20))'), (string)$rs->arl_nit)
                ->value('codigo');
        }

        $query = DB::table('planos AS p')
            ->leftJoin('facturas AS f',      'f.id',          '=', 'p.factura_id')
            ->leftJoin('clientes AS cl',     'cl.cedula',     '=', 'p.no_identifi')
            ->leftJoin('ciudades AS c',      'c.id_ciudad_t', '=', 'cl.municipio_id')
            ->leftJoin('departamentos AS d', 'd.id',          '=', 'cl.departamento_id')
            ->leftJoin('pensiones AS afp_t', DB::raw('CAST(afp_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_afp'))
            ->leftJoin('eps AS eps_t',       DB::raw('CAST(eps_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_eps'))
            ->leftJoin('cajas AS caj_t',     DB::raw('CAST(caj_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_caja'))
            ->leftJoin('arl_tarifas AS arl_t','arl_t.nivel',  '=', 'p.nivel_riesgo')
            ->leftJoin('arls AS arl_m',      DB::raw('CAST(arl_m.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_arl'))
            ->where('p.aliado_id',       $aliadoId)
            ->where('p.razon_social_id', $razonSocialId)
            ->where('p.n_plano',         $nPlano)
            ->whereIn('p.tipo_reg',      ['planilla', 'retiro'])
            ->where(fn($q) => $q->where('p.num_dias', '>=', 1)->orWhere('p.tipo_reg', '!=', 'retiro'))
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($mesPago, $anioPago, $mesVencido, $anioVencido) {
                $q->where(function ($i) use ($mesPago, $anioPago) {
                    $i->where('p.tipo_modalidad_id', 11)
                      ->where('p.mes_plano',  $mesPago)
                      ->where('p.anio_plano', $anioPago);
                })->orWhere(function ($i) use ($mesVencido, $anioVencido) {
                    $i->where('p.tipo_modalidad_id', '<>', 11)
                      ->where('p.mes_plano',  $mesVencido)
                      ->where('p.anio_plano', $anioVencido);
                });
            })
            ->select([
                'p.tipo_doc', 'p.no_identifi', 'p.tipo_modalidad_id',
                'p.primer_nombre', 'p.segundo_nombre', 'p.primer_ape', 'p.segundo_ape',
                'p.cod_eps', 'p.cod_afp', 'p.cod_arl', 'p.cod_caja',
                'p.salario_basico', 'p.num_dias', 'p.nivel_riesgo',
                'p.fecha_ing', 'p.fecha_ret', 'p.tipo_p',
                DB::raw('COALESCE(eps_t.nombre_asopagos, p.cod_eps) AS nombre_asopagos_eps'),
                DB::raw('COALESCE(afp_t.nombre_asopagos, p.cod_afp) AS nombre_asopagos_afp'),
                DB::raw('COALESCE(arl_m.nombre_asopagos, p.cod_arl) AS nombre_asopagos_arl'),
                DB::raw('COALESCE(caj_t.nombre_asopagos, p.cod_caja) AS nombre_asopagos_caj'),
                // Códigos tipo EPS010 de las tablas maestras
                DB::raw('eps_t.codigo  AS codigo_eps'),
                DB::raw('afp_t.codigo  AS codigo_afp'),
                DB::raw('arl_m.codigo  AS codigo_arl'),
                DB::raw('caj_t.codigo  AS codigo_caj'),
                DB::raw('arl_t.porcentaje AS tarifa_arl'),
                'f.v_eps', 'f.v_afp', 'f.v_arl', 'f.v_caja', 'f.total_ss', 'f.dias_cotizados',
                'cl.genero', 'cl.fecha_nacimiento',
                DB::raw('d.id                    AS cod_departamento'),
                DB::raw('CAST(c.Municipio AS INT) AS cod_municipio'),
            ]);

        if (!empty($tiposModal)) {
            $query->whereIn('p.tipo_modalidad_id', $tiposModal);
        }

        $planos = $query->orderBy('p.primer_ape')->orderBy('p.primer_nombre')->get();

        // ── Spreadsheet ─────────────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asopagos');

        $secciones = [
            'INFORMACIÓN BÁSICA'                   => self::SECCION_INFO,
            'NOVEDADES'                            => self::SECCION_NOVEDADES,
            'SALUD'                                => self::SECCION_SALUD,
            'PENSIÓN'                              => self::SECCION_PENSION,
            'RIESGOS'                              => self::SECCION_RIESGOS,
            'CCF'                                  => self::SECCION_CCF,
            'OTROS PARAFISCALES DIFERENTES A CAJA' => self::SECCION_PARAFISCALES,
        ];

        // Fila 1: secciones
        $colStart = 1;
        foreach ($secciones as $titulo => $cols) {
            $colEnd = $colStart + count($cols) - 1;
            $sL = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colStart);
            $eL = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colEnd);
            $sheet->mergeCells("{$sL}1:{$eL}1");
            $sheet->getCell("{$sL}1")->setValue($titulo);
            $sheet->getStyle("{$sL}1:{$eL}1")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SECCION]],
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $colStart = $colEnd + 1;
        }

        // Fila 2: encabezados
        $allHeaders = array_merge(
            self::SECCION_INFO, self::SECCION_NOVEDADES,
            self::SECCION_SALUD, self::SECCION_PENSION,
            self::SECCION_RIESGOS, self::SECCION_CCF, self::SECCION_PARAFISCALES
        );

        foreach ($allHeaders as $idx => $header) {
            $col    = $idx + 1;
            $cL     = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $amarillo = in_array($col, self::COLS_AMARILLO);
            $sheet->getCell("{$cL}2")->setValue($header);
            $sheet->getStyle("{$cL}2")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $amarillo ? self::COLOR_AMARILLO : self::COLOR_HEADER]],
                'font'      => ['bold' => true, 'size' => 8,
                                'color' => ['rgb' => $amarillo ? '1E293B' : 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getColumnDimensionByColumn($col)->setWidth(15);
        }
        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(36);

        $fila = 3;
        foreach ($planos as $p) {
            $this->escribirFila($sheet, $fila, $p, $codigoArlRs);
            $fila++;
        }

        $sheet->freezePane('A3');
        return $spreadsheet;
    }

    private function escribirFila($sheet, int $fila, object $p, ?string $codigoArlRs = null): void
    {
        // ── Edad y género ───────────────────────────────────────────────────
        $edad   = null;
        $genero = strtoupper(trim($p->genero ?? ''));
        if (!empty($p->fecha_nacimiento)) {
            try { $edad = \Carbon\Carbon::parse($p->fecha_nacimiento)->age; } catch (\Exception $e) {}
        }

        // ── Tipo / subtipo cotizante ────────────────────────────────────────
        $esIndep    = in_array((int)$p->tipo_modalidad_id, [10, 11]);
        $tipoCot    = $esIndep ? 2 : 1;
        // Pensión
        $tienePension = !empty($p->cod_afp) && (int)($p->v_afp ?? 0) > 0;
        // Subtipo: null si cotiza pensión; 3=edad pensión, 4=sin pensión joven
        $subtipo = null;
        if (!$tienePension) {
            $subtipo = ($edad !== null && (($genero==='M' && $edad>=55)||($genero==='F' && $edad>=50))) ? 3 : 4;
        }

        // ── Tipo documento ──────────────────────────────────────────────────
        $tipoDoc = strtoupper(trim($p->tipo_doc ?? 'CC'));
        // Mapeo: los válidos Asopagos son CC CE TI PA CD SC RC PE
        $mapaDoc = ['C'=>'CC','NIT'=>'CC','CE'=>'CE','TI'=>'TI','PA'=>'PA','CD'=>'CD','SC'=>'SC','RC'=>'RC','PE'=>'PE','NUIP'=>'CC','PT'=>'CE'];
        $tipoDoc = $mapaDoc[$tipoDoc] ?? $tipoDoc;

        $docsNumericos = ['CC','TI','SC','RC','CE'];
        $esExtranjero  = !in_array($tipoDoc, ['CC','TI','NUIP','RC','SC']) ? 'X' : null;

        // ── Exonerado ──────────────────────────────────────────────────────
        // 'S' si cotizante tipo 1 (dependiente) con tipoCot=1 y empresa con exoneración
        // Simplificado: independientes no están exonerados, dependientes sí (empresa paga el 9%)
        $exonerado = $esIndep ? 'N' : 'S';

        // ── Salario integral ────────────────────────────────────────────────
        $esIntegral   = (strtoupper(trim($p->tipo_p ?? '')) === 'I') ? 'X' : null;

        // ── Valores monetarios ──────────────────────────────────────────────
        $ibc  = (int)($p->salario_basico ?? 0);
        $dias = (int)($p->dias_cotizados ?? $p->num_dias ?? 30);
        $vEps = $this->cienSuperior((int)($p->v_eps ?? 0));
        $vAfp = $tienePension ? $this->cienSuperior((int)($p->v_afp ?? 0)) : 0;
        $vArl = $this->cienSuperior((int)($p->v_arl ?? 0));
        $vCaj = $this->cienSuperior((int)($p->v_caja ?? 0));

        // ── Tarifa ARL decimal ──────────────────────────────────────────────
        $tarifaArl = $p->tarifa_arl !== null ? round((float)$p->tarifa_arl / 100, 6) : null;

        // ── Fechas ingreso / retiro ─────────────────────────────────────────
        $fechaIng = null; $fechaRet = null;
        if (!empty($p->fecha_ing)) { try { $fechaIng = \Carbon\Carbon::parse($p->fecha_ing)->format('Y-m-d'); } catch (\Exception $e) {} }
        if (!empty($p->fecha_ret)) { try { $fechaRet = \Carbon\Carbon::parse($p->fecha_ret)->format('Y-m-d'); } catch (\Exception $e) {} }
        // ING: X=ingreso definitivo; RET: X=retiro definitivo
        $esIng = $fechaIng ? 'X' : null;
        $esRet = $fechaRet ? 'X' : null;

        // ── Códigos de entidades tipo EPS010 (blank si no tiene) ─────────────────
        $codEps = !empty($p->codigo_eps) ? trim($p->codigo_eps) : null;
        $codAfp = ($tienePension && !empty($p->codigo_afp)) ? trim($p->codigo_afp) : null;
        // ARL: usar la del plano; si no tiene, usar la de la razón social
        $codArl = !empty($p->codigo_arl) ? trim($p->codigo_arl) : $codigoArlRs;
        $codCaj = !empty($p->codigo_caj) ? trim($p->codigo_caj) : 'CCF68';

        // ── Clase de riesgo ─────────────────────────────────────────────────
        $nivelRiesgo = (int)($p->nivel_riesgo ?? 1);
        $claseRiesgo = (string)$nivelRiesgo; // 1-5

        // ── 87 valores en orden ─────────────────────────────────────────────
        $valores = [
            // INFORMACIÓN BÁSICA (16) ─────────────────────────────────── 1-16
            $tipoDoc,                        //  1 TIPO DOCUMENTO
            (string)$p->no_identifi,         //  2 NUMERO DOCUMENTO
            $tipoCot,                        //  3 TIPO COTIZANTE
            $subtipo,                        //  4 SUBTIPO COTIZANTE
            $esExtranjero,                   //  5 EXTRANJERO
            null,                            //  6 COLOMBIANO EXTERIOR
            null,                            //  7 FECHA RADICACIÓN EXTERIOR
            $exonerado,                      //  8 EXONERADO (S/N)
            $p->cod_departamento,            //  9 CÓDIGO DEPARTAMENTO
            $p->cod_municipio,               // 10 CÓDIGO MUNICIPIO
            $p->primer_ape,                  // 11 PRIMER APELLIDO
            $p->segundo_ape,                 // 12 SEGUNDO APELLIDO
            $p->primer_nombre,               // 13 PRIMER NOMBRE
            $p->segundo_nombre,              // 14 SEGUNDO NOMBRE
            $ibc,                            // 15 SALARIO BÁSICO
            $esIntegral,                     // 16 SALARIO INTEGRAL (X o blank)

            // NOVEDADES (32) ──────────────────────────────────────────── 17-48
            $esIng,    $fechaIng,            // 17-18 ING, FECHA INGRESO
            $esRet,    $fechaRet,            // 19-20 RET, FECHA RETIRO
            null, null, null,                // 21-23 TOE, TAE, EPS TRASLADA
            null, null, null,                // 24-26 TOP, TAP, AFP TRASLADA
            null, null, null, null,          // 27-30 VSP, F_INI, F_FIN, VST
            null, null, null,                // 31-33 SLN, F_INI, F_FIN
            null, null, null,                // 34-36 IGE, F_INI, F_FIN
            null, null, null,                // 37-39 LMA, F_INI, F_FIN
            null, null, null,                // 40-42 VAC-LR, F_INI, F_FIN
            null, null, null,                // 43-45 VCT, F_INI, F_FIN
            null, null, null,                // 46-48 IRL, F_INI, F_FIN

            // SALUD (8) ────────────────────────────────────────────────── 49-56
            $codEps,                         // 49 EPS
            $dias,                           // 50 DÍAS COTIZADOS SALUD
            $ibc,                            // 51 IBC SALUD
            0.04,                            // 52 TARIFA SALUD (4%)
            $vEps ?: null,                   // 53 COTIZACIÓN EPS ★ (cien superior)
            0,                               // 54 VALOR UPC
            null,                            // 55 TIPO DOC UPC
            null,                            // 56 NUM DOC UPC

            // PENSIÓN (9) ─────────────────────────────────────────────── 57-65
            null,                            // 57 INDICADOR TARIFA ESPECIAL
            $codAfp,                         // 58 AFP
            $tienePension ? $dias : 0,       // 59 DÍAS COTIZADOS PENSIÓN
            $tienePension ? $ibc  : 0,       // 60 IBC PENSIÓN
            0.16,                            // 61 TARIFA PENSIÓN (16%)
            $vAfp ?: null,                   // 62 COTIZACIÓN AFP ★ (cien superior)
            null,                            // 63 APORTE VOLUNTARIO AFILIADO
            null,                            // 64 APORTE VOLUNTARIO APORTANTE
            null,                            // 65 VALOR NO RETENIDO

            // RIESGOS (7) ──────────────────────────────────────────────── 66-72
            $codArl,                         // 66 ARL AFILIADO
            $dias,                           // 67 DÍAS COTIZADOS RIESGOS
            $ibc,                            // 68 IBC RIESGOS
            $nivelRiesgo,                    // 69 CENTRO DE TRABAJO
            $claseRiesgo,                    // 70 CLASE DE RIESGO
            $tarifaArl,                      // 71 TARIFA RIESGOS
            $vArl ?: null,                   // 72 COTIZACIÓN ARL ★ (cien superior)

            // CCF (4) ──────────────────────────────────────────────────── 73-76
            $codCaj,                         // 73 CCF
            $ibc,                            // 74 IBC CCF
            0.04,                            // 75 TARIFA CCF (4%)
            $vCaj ?: null,                   // 76 COTIZACIÓN CCF ★ (cien superior)

            // OTROS PARAFISCALES (11) ─────────────────────────────────── 77-87
            0,                               // 77 IBC OTROS PARAFISCALES
            0,                               // 78 TARIFA SENA
            0,                               // 79 COTIZACIÓN SENA ★
            0,                               // 80 TARIFA ICBF
            0,                               // 81 COTIZACIÓN ICBF ★
            0,                               // 82 TARIFA ESAP
            0,                               // 83 COTIZACIÓN ESAP ★
            0,                               // 84 TARIFA MIN
            0,                               // 85 COTIZACIÓN MIN ★
            8 * $dias,                       // 86 HORAS LABORADAS
            null,                            // 87 ACTIVIDAD ECONÓMICA
        ];

        $col = 1;
        foreach ($valores as $v) {
            $cL   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cell = $sheet->getCell("{$cL}{$fila}");
            $cell->setValue($v ?? '');
            // Documento como texto para evitar notación científica
            if ($col === 2) {
                $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            $col++;
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
}
