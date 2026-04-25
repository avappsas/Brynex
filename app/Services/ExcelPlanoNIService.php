<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\DB;

/**
 * ExcelPlanoNIService
 *
 * Genera el archivo Excel de ayuda para pago de planillas SS
 * en el formato requerido por operadores Simple / ARUS / Enlace.
 *
 * Hoja: "Planilla base"
 *   Fila 1  -> encabezados del aportante (22 columnas)
 *   Fila 2  -> valores del aportante
 *   Fila 3  -> encabezados de los trabajadores (98 columnas)
 *   Fila 4+ -> un trabajador por fila
 */
class ExcelPlanoNIService
{
    // --- Encabezados fila 1: Aportante (22 columnas formato NI Simple/ARUS) ------
    private const HEADERS_APORTANTE = [
        'Tipo de Registro',                                          // 1
        'Modalidad de la Planilla',                                  // 2  -> 1
        'Secuencia',                                                 // 3
        'Nombre o Razon Social del Aportante',                       // 4
        'Tipo de Documento de Identificacion del Aportante',         // 5  -> NI
        'Numero de Identificacion del Aportante',                    // 6
        'Digito de Verificacion',                                    // 7
        'Tipo Planilla',                                             // 8  -> E
        'Numero PI. Factura',                                        // 9
        'Fecha PI. Factura',                                         // 10
        'Forma de Presentacion',                                     // 11 -> S
        'Codigo Sucursal Aportante',                                 // 12 -> 01
        'Nombre Sucursal',                                           // 13 -> SUCURSAL
        'Codigo ARL',                                                // 14 -> de tabla arls
        'Periodo Pago Sistemas Diferentes a Salud',                  // 15 -> mes vencido
        'Periodo Pago al Sistema de Salud',                          // 16 -> mes actual
        'Numero de Planilla',                                        // 17
        'Numero de Cotizantes',                                      // 18
        'Valor Total de la Nomina',                                  // 19
        'Tipo de Aportante',                                         // 20 -> 1
        'Codigo del Operador de Informacion',                        // 21 -> 89 ARUS
        'Version del Formato',                                       // 22
    ];

    // --- Encabezados fila 3: Trabajadores (98 columnas — formato operadores SS) ----
    private const HEADERS_TRABAJADORES = [
        'Tipo de registro',          //  1
        'Secuencia',                 //  2
        'Tipo documento cotizante',  //  3
        'Documento cotizante',       //  4
        'Tipo de cotizante',         //  5
        'Subtipo de cotizante',      //  6
        'Extranjero',                //  7
        'Colombiano en el exterior', //  8
        'Departamento',              //  9
        'Municipio',                 // 10
        'Primer apellido',           // 11
        'Segundo apellido',          // 12
        'Primer nombre',             // 13
        'Segundo nombre',            // 14
        'ING',                       // 15
        'RET',                       // 16
        'TDE',                       // 17
        'TAE',                       // 18
        'TDP',                       // 19
        'TAP',                       // 20
        'VSP',                       // 21
        'Línea',                      // 22
        'VST',                       // 23
        'SLN',                       // 24
        'IGE',                       // 25
        'LMA',                       // 26
        'VAC-LR',                    // 27
        'AVP',                       // 28
        'VCT',                       // 29
        'IRL',                       // 30
        'AFP',                       // 31
        'AFP Traslado',              // 32
        'EPS',                       // 33
        'EPS Traslado',              // 34
        'CCF',                       // 35
        'Días AFP',                  // 36
        'Días EPS',                  // 37
        'Días ARL',                  // 38
        'Días CCF',                  // 39
        'Salario básico',            // 40
        'Salario integral',          // 41
        'IBC AFP',                   // 42
        'IBC EPS',                   // 43
        'IBC ARL',                   // 44
        'IBC CCF',                   // 45
        'Tarifa AFP',                // 46
        'Cotización AFP',            // 47
        'AVP afiliado',              // 48
        'AVP aportante',             // 49
        'Total AFP',                 // 50
        'Aporte FSP',                // 51
        'Aporte FSPS',               // 52
        'Valor no retenido',         // 53
        'Tarifa EPS',                // 54
        'Cotización EPS',            // 55
        'Valor UPC',                 // 56
        'Número IGE',               // 57
        'Valor IGE',                 // 58
        'Número LMA',               // 59
        'Valor LMA',                 // 60
        'Tarifa ARL',                // 61
        'Centro de trabajo',         // 62
        'Cotización ARL',            // 63
        'Tarifa CCF',                // 64
        'Aporte CCF',                // 65
        'Tarifa SENA',               // 66
        'Aporte SENA',               // 67
        'Tarifa ICBF',               // 68
        'Aporte ICBF',               // 69
        'Tarifa ESAP',               // 70
        'Aporte ESAP',               // 71
        'Tarifa MEN',                // 72
        'Aporte MEN',                // 73
        'Tipo documento UPC',        // 74
        'Documento UPC',             // 75
        'Exonerado',                 // 76
        'ARL',                       // 77
        'Clase riesgo',              // 78
        'Tarifa especial AFP',       // 79
        'Fecha ING',                 // 80
        'Fecha RET',                 // 81
        'Fecha inicio VSP',          // 82
        'Fecha inicio SLN',          // 83
        'Fecha final SLN',           // 84
        'Fecha inicio IGE',          // 85
        'Fecha final IGE',           // 86
        'Fecha inicio LMA',          // 87
        'Fecha final LMA',           // 88
        'Fecha inicio VAC-LR',       // 89
        'Fecha final VAC-LR',        // 90
        'Fecha inicio VCT',          // 91
        'Fecha final VCT',           // 92
        'Fecha inicio IRL',          // 93
        'Fecha final IRL',           // 94
        'IBC otros parafiscales',    // 95
        'Número horas laboradas',    // 96
        'Fecha radicación exterior', // 97
        'Actividad económica ARL',   // 98
    ];

    /**
     * Genera el Spreadsheet con los datos del plano.
     *
     * @param array $params [
     *   'aliado_id'       => int,
     *   'razon_social_id' => string|int,
     *   'mes'             => int,   // mes de PAGO (UI)
     *   'anio'            => int,   // anno de pago
     *   'n_plano'         => int,
     *   'tipos_modalidad' => array, // IDs a filtrar (vacio = todos)
     * ]
     */
    public function generar(array $params): Spreadsheet
    {
        $aliadoId      = $params['aliado_id'];
        $razonSocialId = $params['razon_social_id'];
        $mesPago       = (int) $params['mes'];
        $anioPago      = (int) $params['anio'];
        $nPlano        = (int) $params['n_plano'];
        $tiposModal    = $params['tipos_modalidad'] ?? [];

        // -- Mes vencido para dependientes ------------------------------------------
        $mesVencido  = $mesPago > 1 ? $mesPago - 1 : 12;
        $anioVencido = $mesPago > 1 ? $anioPago    : $anioPago - 1;

        // -- 1. Datos del aportante ------------------------------------------------
        $rs = DB::table('razones_sociales')
            ->where('id', $razonSocialId)
            ->where('aliado_id', $aliadoId)
            ->first();

        if (!$rs) {
            throw new \RuntimeException("Razon social {$razonSocialId} no encontrada.");
        }

        // Operador seleccionado por el usuario (si pasa operador_id, busca ese; si no, el primero activo)
        $operadorId = $params['operador_id'] ?? null;
        $queryOp = DB::table('operadores_planilla')
            ->where(function ($q) use ($aliadoId) {
                $q->whereNull('aliado_id')->orWhere('aliado_id', $aliadoId);
            })
            ->where('activo', true);

        $operador = $operadorId
            ? $queryOp->where('id', $operadorId)->first()
            : $queryOp->orderBy('orden')->first();

        // Codigo de la ARL de la empresa (por NIT)
        $codigoArl = null;
        if ($rs->arl_nit) {
            $codigoArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('codigo');
        }

        // -- 2. Planos del periodo (logica mes vencido) ----------------------------
        $query = DB::table('planos AS p')
            ->join('facturas AS f',   'f.id',          '=', 'p.factura_id')
            ->leftJoin('clientes AS cl',  'cl.cedula',     '=', 'p.no_identifi')
            ->leftJoin('ciudades AS c',      'c.id_ciudad_t', '=', 'cl.municipio_id')
            ->leftJoin('departamentos AS d', 'd.id',          '=', 'cl.departamento_id')
            // Códigos PILA de entidades (NIT del plano → codigo en tabla maestra)
            ->leftJoin('pensiones AS afp_t', 'afp_t.nit', '=', 'p.cod_afp')
            ->leftJoin('eps AS eps_t',       'eps_t.nit', '=', 'p.cod_eps')
            ->leftJoin('cajas AS caj_t',     'caj_t.nit', '=', 'p.cod_caja')
            // Tarifa ARL según nivel de riesgo del plano
            ->leftJoin('arl_tarifas AS arl_t', 'arl_t.nivel', '=', 'p.nivel_riesgo')
            // Código PILA de la ARL (NIT del plano → codigo en tabla arls)
            ->leftJoin('arls AS arl_m', 'arl_m.nit', '=', 'p.cod_arl')
            ->where('p.aliado_id',       $aliadoId)
            ->where('p.razon_social_id', $razonSocialId)
            ->where('p.n_plano',         $nPlano)
            ->where('p.tipo_reg',        'planilla')
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($mesPago, $anioPago, $mesVencido, $anioVencido) {
                $q->where(function ($i) use ($mesPago, $anioPago) {
                    // Independientes (tipo_modalidad_id = 11) → mes actual
                    $i->where('p.tipo_modalidad_id', 11)
                      ->where('p.mes_plano',  $mesPago)
                      ->where('p.anio_plano', $anioPago);
                })->orWhere(function ($i) use ($mesVencido, $anioVencido) {
                    // Todos los demás → mes vencido
                    $i->where('p.tipo_modalidad_id', '<>', 11)
                      ->where('p.mes_plano',  $mesVencido)
                      ->where('p.anio_plano', $anioVencido);
                });
            })
            ->select([
                // Identificación
                'p.tipo_doc',
                'p.no_identifi',
                'p.tipo_modalidad_id',
                // Nombres
                'p.primer_nombre',
                'p.segundo_nombre',
                'p.primer_ape',
                'p.segundo_ape',
                // NITs originales del plano (para referencia interna)
                'p.cod_eps',
                'p.cod_afp',
                'p.cod_arl',
                'p.cod_caja',
                // Códigos PILA de las entidades
                DB::raw('afp_t.codigo  AS codigo_afp'),
                DB::raw('eps_t.codigo  AS codigo_eps'),
                DB::raw('caj_t.codigo  AS codigo_caj'),
                DB::raw('arl_m.codigo  AS codigo_arl_pila'),
                // Tarifa ARL
                DB::raw('arl_t.porcentaje AS tarifa_arl'),
                // IBC / salario
                'p.salario_basico',
                'p.num_dias',
                'p.nivel_riesgo',
                // Fechas ING/RET
                'p.fecha_ing',
                'p.fecha_ret',
                // Factura: valores reales de aporte
                'f.v_eps',
                'f.v_afp',
                'f.v_arl',
                'f.v_caja',
                'f.total_ss',
                'f.dias_cotizados',
                // Cliente: datos demográficos
                'cl.genero',
                'cl.fecha_nacimiento',
                DB::raw('d.id                          AS cod_departamento'),
                DB::raw('CAST(c.Municipio AS INT)       AS cod_municipio'),
            ]);

        if (!empty($tiposModal)) {
            $query->whereIn('p.tipo_modalidad_id', $tiposModal);
        }

        $planos = $query
            ->orderBy('p.primer_ape')
            ->orderBy('p.primer_nombre')
            ->get();

        // -- 3. Numero planilla (del primer registro con numero_planilla) -----------
        $numeroPlanilla = DB::table('planos')
            ->where('aliado_id',       $aliadoId)
            ->where('razon_social_id', $razonSocialId)
            ->where('n_plano',         $nPlano)
            ->whereNotNull('numero_planilla')
            ->value('numero_planilla');

        // -- 4. Totales para fila 2 ------------------------------------------------
        $totalCotizantes = $planos->count();
        $totalNomina     = $planos->sum('total_ss');

        // -- 5. Periodos AAAAMM separados ------------------------------------------
        // Col 15: Sistemas diferentes a Salud (AFP, ARL, CCF) = mes VENCIDO
        $periodoSS     = sprintf('%04d%02d', $anioVencido, $mesVencido);
        // Col 16: Sistema de Salud (EPS) = mes ACTUAL (de pago)
        $periodoSalud  = sprintf('%04d%02d', $anioPago,   $mesPago);

        // -- 6. Construir Spreadsheet ----------------------------------------------
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planilla base');

        // Fila 1: Encabezados del aportante
        $col = 1;
        foreach (self::HEADERS_APORTANTE as $header) {
            $sheet->getCell([$col++, 1])->setValue($header);
        }

        // Fila 2: Valores del aportante
        $this->escribirFilaAportante($sheet, 2, [
            'tipo_registro'      => 1,
            'modalidad_plan'     => '1',         // 1 = electrónica ordinaria
            'secuencia'          => 1,
            'razon_social'       => $rs->razon_social,
            'tipo_doc_ap'        => 'NI',        // NI = NIT para aportante empresa
            'nit'                => (string) $rs->id,
            'dv'                 => $rs->dv,
            'tipo_planilla'      => 'E',         // E = Ordinaria
            'nro_pi_factura'     => null,
            'fecha_p_factura'    => null,
            'forma_presentacion' => $rs->forma_presentacion,
            'codigo_suc'         => $rs->codigo_sucursal,
            'nombre_suc'         => $rs->nombre_sucursal,
            'codigo_arl'         => $codigoArl ?? '',  // NIT/código ARL de la empresa
            'periodo_ss'         => $periodoSS,       // mes vencido para AFP/ARL/CCF
            'periodo_salud'      => $periodoSalud,    // mes actual para EPS
            'numero_planilla'    => $numeroPlanilla,
            'numero_cotizantes'  => $totalCotizantes,
            'valor_nomina'       => (int) $totalNomina,
            'tipo_aportante'     => 1,
            'codigo_operador'    => $operador?->codigo_ni ?? '',   // código numérico PILA (89=ARUS)
            'version_formato'    => null,
        ]);

        // Fila 3: Encabezados de los trabajadores
        $col = 1;
        foreach (self::HEADERS_TRABAJADORES as $header) {
            $sheet->getCell([$col++, 3])->setValue($header);
        }

        // Filas 4+: Un trabajador por fila
        $fila = 4;
        $seq  = 1;
        foreach ($planos as $p) {
            $this->escribirFilaTrabajador($sheet, $fila, $p, $seq);
            $fila++;
            $seq++;
        }

        return $spreadsheet;
    }

    // --- Escribe fila 2 (aportante, 22 columnas) ---------------------------------
    private function escribirFilaAportante($sheet, int $fila, array $d): void
    {
        // Exactamente 22 columnas en el mismo orden que HEADERS_APORTANTE
        $valores = [
            /* 01 */ $d['tipo_registro'],       // 1
            /* 02 */ $d['modalidad_plan'],      // '1'
            /* 03 */ $d['secuencia'],           // 1
            /* 04 */ $d['razon_social'],        // Razón social
            /* 05 */ $d['tipo_doc_ap'],         // 'NI'
            /* 06 */ $d['nit'],                 // NIT (texto)
            /* 07 */ $d['dv'],                  // DV
            /* 08 */ $d['tipo_planilla'],       // 'E'
            /* 09 */ $d['nro_pi_factura'],      // null
            /* 10 */ $d['fecha_p_factura'],     // null
            /* 11 */ $d['forma_presentacion'],  // 'S'
            /* 12 */ $d['codigo_suc'],          // '01'
            /* 13 */ $d['nombre_suc'],          // 'SUCURSAL'
            /* 14 */ $d['codigo_arl'],          // NIT/código ARL
            /* 15 */ $d['periodo_ss'],          // AAAAMM mes vencido
            /* 16 */ $d['periodo_salud'],       // AAAAMM mes actual
            /* 17 */ $d['numero_planilla'],     // número planilla
            /* 18 */ $d['numero_cotizantes'],   // total personas
            /* 19 */ $d['valor_nomina'],        // total SS
            /* 20 */ $d['tipo_aportante'],      // 1
            /* 21 */ $d['codigo_operador'],     // 89 (ARUS)
            /* 22 */ $d['version_formato'],     // null
        ];

        // Columnas que deben ser texto para evitar notacion cientifica
        $colTexto = [6, 14]; // NIT aportante, Código ARL

        $col = 1;
        foreach ($valores as $v) {
            $cell = $sheet->getCell([$col, $fila]);
            $cell->setValue($v ?? '');

            if (in_array($col, $colTexto)) {
                $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            $col++;
        }
    }

    // --- Escribe una fila de trabajador -------------------------------------------
    private function escribirFilaTrabajador($sheet, int $fila, object $p, int $seq): void
    {
        $edad = null;
        if ($p->fecha_nacimiento) {
            $edad = sqldate($p->fecha_nacimiento)?->age;
        }

        $genero = strtoupper(trim($p->genero ?? ''));

        // -- Determinar si el plan incluye pensión (AFP) ----------------------------
        // El plan incluye AFP si tiene cod_afp registrado Y el valor v_afp > 0.
        // Si no tiene AFP en el plan → omitePension = true → subtipo 3 (vejez) o 4.
        $tienePension = !empty($p->cod_afp) && (int)($p->v_afp ?? 0) > 0;
        $omitePension = !$tienePension;

        // -- Subtipo cotizante PILA -------------------------------------------------
        // 0 = estándar (tiene pensión incluida en el plan)
        // 3 = pensionado por vejez (sin AFP: hombre ≥55 o mujer ≥50)
        // 4 = pensionado por otra causa (sin AFP, pero no alcanzó edad de vejez)
        if ($tienePension) {
            $subtipoCotizante = 0;
        } else {
            // Sin AFP: determinar si es subtipo 3 (vejez) o 4 (otro pensionado)
            if ($edad !== null && (($genero === 'M' && $edad >= 55) || ($genero === 'F' && $edad >= 50))) {
                $subtipoCotizante = 3; // pensionado por vejez
            } else {
                $subtipoCotizante = 4; // pensionado por otra causa
            }
        }

        // -- Extranjero: 'X' si el tipo de documento NO es colombiano ---------------
        // Documentos colombianos: CC (Cédula), TI (Tarjeta Identidad), NUIP
        // Documentos extranjeros: CE, PA, PE, PT, AS, MS, CD, SC → X
        $tipoDocNorm    = strtoupper(trim($p->tipo_doc ?? 'CC'));
        $docsColombiano = ['CC', 'TI', 'NUIP', 'RC'];
        $esExtranjero   = !in_array($tipoDocNorm, $docsColombiano) ? 'X' : null;

        // Tipo cotizante PILA: 1 = Dependiente, 2 = Independiente
        $esIndependiente = in_array((int)$p->tipo_modalidad_id, [10, 11]);
        $tipoCotizante   = $esIndependiente ? 2 : 1;

        // -- IBC y valores SS -------------------------------------------------------
        $ibc   = (int)($p->salario_basico ?? 0);
        $dias  = (int)($p->dias_cotizados ?? $p->num_dias ?? 30);
        $vEps  = (int)($p->v_eps  ?? 0);
        $vAfp  = $omitePension ? 0 : (int)($p->v_afp  ?? 0);
        $vArl  = (int)($p->v_arl  ?? 0);
        $vCaja = (int)($p->v_caja ?? 0);

        // -- Fechas ING / RET → formato YYYY-MM-DD para el Excel PILA ---------------
        $fechaIng = $p->fecha_ing ? sqldate($p->fecha_ing)?->format('Y-m-d') : null;
        $fechaRet = $p->fecha_ret ? sqldate($p->fecha_ret)?->format('Y-m-d') : null;
        $esIng    = $p->fecha_ing ? 'X' : null;  // X = ingresó en el período
        $esRet    = $p->fecha_ret ? 'X' : null;  // X = se retiró en el período

        // -- Tipo documento ---------------------------------------------------------
        $tipoDoc = $tipoDocNorm; // ya calculado arriba

        // -- NITs de entidades (del plano, como texto) --------------------------------
        $nitAfp  = (string)($p->cod_afp  ?? '');
        $nitEps  = (string)($p->cod_eps  ?? '');
        $nitArl  = (string)($p->cod_arl  ?? '');
        $nitCaja = (string)($p->cod_caja ?? '');

        // -- Códigos PILA de la entidad (de las tablas maestras) ----------------------
        $codAfp    = $p->codigo_afp      ?? null;
        $codEps    = $p->codigo_eps      ?? null;
        $codCaj    = $p->codigo_caj      ?? null;  // null si sin caja
        $codCajFin = $codCaj ?: 'CCF68';           // 'CCF68' cuando no tiene caja
        $codArlPila = $p->codigo_arl_pila ?? null;

        // -- Tarifa ARL: la BD guarda porcentaje en 0-100 → dividir entre 100 ----------
        // Ejemplo: nivel 1 = 0.5220 en BD → 0.5220/100 = 0.00522 en el Excel
        $tarifaArl = $p->tarifa_arl !== null ? round((float)$p->tarifa_arl / 100, 6) : null;

        // -- IBC / Aporte CCF: 100 si no hay caja asociada ---------------------------
        $ibcCcf    = $nitCaja ? $ibc  : 100;
        $aporteCcf = $vCaja   ? $vCaja : 100;

        // -- Número de horas laboradas: (240 / 30) * dias = 8 * dias ------------------
        $horasLaboradas = 8 * $dias;

        // -- 98 campos del trabajador (orden exacto del formato operadores SS) ---------
        // Regla: lo que no sabemos o no aplica → null (celda vacía)
        $valores = [
            /*  1 */ 2,                                // Tipo de registro (siempre 2)
            /*  2 */ $seq,                             // Secuencia
            /*  3 */ $tipoDoc,                         // Tipo documento cotizante
            /*  4 */ (string)$p->no_identifi,          // Documento cotizante
            /*  5 */ $tipoCotizante,                   // Tipo de cotizante (1=dep, 2=indep)
            /*  6 */ $subtipoCotizante ?: null,        // Subtipo: 0=estándar, 3=vejez, 4=otro pensionado
            /*  7 */ $esExtranjero,                    // Extranjero: 'X' si doc no colombiano
            /*  8 */ null,                             // Colombiano en el exterior
            /*  9 */ $p->cod_departamento ?? null,     // Departamento (cód. DANE)
            /* 10 */ $p->cod_municipio    ?? null,     // Municipio (Municipio_No PILA)
            /* 11 */ $p->primer_ape,                   // Primer apellido
            /* 12 */ $p->segundo_ape,                  // Segundo apellido
            /* 13 */ $p->primer_nombre,                // Primer nombre
            /* 14 */ $p->segundo_nombre,               // Segundo nombre
            /* 15 */ $esIng,                           // ING → 'X' o vacío
            /* 16 */ $esRet,                           // RET → 'X' o vacío
            /* 17 */ null,                             // TDE (Traslado Desde EPS)
            /* 18 */ null,                             // TAE (Traslado A EPS)
            /* 19 */ null,                             // TDP (Traslado Desde AFP)
            /* 20 */ null,                             // TAP (Traslado A AFP)
            /* 21 */ null,                             // VSP (Variación Salario Permanente)
            /* 22 */ null,                             // Línea
            /* 23 */ null,                             // VST (Variación Salario Transitoria)
            /* 24 */ null,                             // SLN (Licencia No Remunerada)
            /* 25 */ null,                             // IGE (Incapacidad General)
            /* 26 */ null,                             // LMA (Licencia Maternidad)
            /* 27 */ null,                             // VAC-LR (Vacaciones / Lic. Remunerada)
            /* 28 */ null,                             // AVP (Aporte Voluntario Pens.)
            /* 29 */ null,                             // VCT (Variación Centro Trabajo)
            /* 30 */ 0,                                // IRL = 0
            /* 31 */ $codAfp,                          // AFP → codigo PILA (pensiones.codigo)
            /* 32 */ null,                             // AFP Traslado
            /* 33 */ $codEps,                          // EPS → codigo PILA (EPS.codigo)
            /* 34 */ null,                             // EPS Traslado
            /* 35 */ $codCajFin,                       // CCF → codigo PILA ('CCF68' si sin caja)
            /* 36 */ $omitePension ? 0 : $dias,        // Días AFP (0 si omite pensión)
            /* 37 */ $dias,                            // Días EPS
            /* 38 */ $dias,                            // Días ARL
            /* 39 */ $dias,                            // Días CCF
            /* 40 */ $ibc,                             // Salario básico
            /* 41 */ 'F',                              // Tipo salario: F = fijo
            /* 42 */ $omitePension ? 0 : $ibc,         // IBC AFP (0 si omite pensión)
            /* 43 */ $ibc,                             // IBC EPS
            /* 44 */ $ibc,                             // IBC ARL
            /* 45 */ $ibcCcf,                          // IBC CCF (100 si no tiene caja)
            /* 46 */ 0.16,                             // Tarifa AFP = 0,16
            /* 47 */ $vAfp ?: null,                    // Cotización AFP
            /* 48 */ 0,                                // AVP afiliado = 0
            /* 49 */ 0,                                // AVP aportante = 0
            /* 50 */ $vAfp ?: null,                    // Total AFP
            /* 51 */ 0,                                // Aporte FSP = 0
            /* 52 */ 0,                                // Aporte FSPS = 0
            /* 53 */ 0,                                // Valor no retenido = 0
            /* 54 */ 0.04,                             // Tarifa EPS = 0,04
            /* 55 */ $vEps ?: null,                    // Cotización EPS
            /* 56 */ 0,                                // Valor UPC = 0
            /* 57 */ null,                             // Número IGE
            /* 58 */ 0,                                // Valor IGE = 0
            /* 59 */ null,                             // Número LMA
            /* 60 */ 0,                                // Valor LMA = 0
            /* 61 */ $tarifaArl,                       // Tarifa ARL (porcentaje/100)
            /* 62 */ (int)($p->nivel_riesgo ?? 1),     // Centro de trabajo = nivel ARL (1-5)
            /* 63 */ $vArl ?: null,                    // Cotización ARL
            /* 64 */ 0.04,                             // Tarifa CCF = 0,04
            /* 65 */ $aporteCcf,                       // Aporte CCF ($vCaja o 100 si sin caja)
            /* 66 */ 0,                                // Tarifa SENA = 0
            /* 67 */ 0,                                // Aporte SENA = 0
            /* 68 */ 0,                                // Tarifa ICBF = 0
            /* 69 */ 0,                                // Aporte ICBF = 0
            /* 70 */ 0,                                // Tarifa ESAP = 0
            /* 71 */ 0,                                // Aporte ESAP = 0
            /* 72 */ 0,                                // Tarifa MEN = 0
            /* 73 */ 0,                                // Aporte MEN = 0
            /* 74 */ null,                             // Tipo documento UPC
            /* 75 */ null,                             // Documento UPC
            /* 76 */ 'S',                              // Exonerado = S
            /* 77 */ $codArlPila,                      // ARL → codigo PILA (arls.codigo)
            /* 78 */ (int)($p->nivel_riesgo ?? 1),     // Clase riesgo
            /* 79 */ null,                             // Tarifa especial AFP
            /* 80 */ $fechaIng,                        // Fecha ING (YYYY-MM-DD)
            /* 81 */ $fechaRet,                        // Fecha RET (YYYY-MM-DD)
            /* 82 */ null,                             // Fecha inicio VSP
            /* 83 */ null,                             // Fecha inicio SLN
            /* 84 */ null,                             // Fecha final SLN
            /* 85 */ null,                             // Fecha inicio IGE
            /* 86 */ null,                             // Fecha final IGE
            /* 87 */ null,                             // Fecha inicio LMA
            /* 88 */ null,                             // Fecha final LMA
            /* 89 */ null,                             // Fecha inicio VAC-LR
            /* 90 */ null,                             // Fecha final VAC-LR
            /* 91 */ null,                             // Fecha inicio VCT
            /* 92 */ null,                             // Fecha final VCT
            /* 93 */ null,                             // Fecha inicio IRL
            /* 94 */ null,                             // Fecha final IRL
            /* 95 */ 0,                                // IBC otros parafiscales = 0
            /* 96 */ $horasLaboradas,                  // Horas laboradas: (240/30)*dias
            /* 97 */ null,                             // Fecha radicación exterior
            /* 98 */ null,                             // Actividad económica ARL
        ];

        // Columnas que deben ser texto para evitar notación científica en NITs y documentos
        // col 4=doc cotizante, col 31=NIT AFP, col 33=NIT EPS, col 35=NIT CCF, col 77=NIT ARL
        $colTexto = [4, 31, 33, 35, 77];

        $col = 1;
        foreach ($valores as $v) {
            $cell = $sheet->getCell([$col, $fila]);
            $cell->setValue($v ?? '');

            if (in_array($col, $colTexto)) {
                $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            $col++;
        }
    }

    /**
     * Devuelve un StreamedResponse listo para descargar.
     */
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
