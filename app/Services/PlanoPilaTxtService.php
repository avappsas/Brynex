<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlanoPilaTxtService
{
    // Tarifa ARL por nivel: formato decimal 0.XXXXXXX (9 chars, campo 61)
    private const TARIFA_ARL = [
        1 => '0.0052200', 2 => '0.0104400', 3 => '0.0243600',
        4 => '0.0435000', 5 => '0.0696000',
    ];

    // Actividad económica por nivel de riesgo (Decreto 768/2022) — campo 98, 7 chars N
    private const ACTECO_ARL = [
        1 => '1141001', 2 => '2141003', 3 => '3139202',
        4 => '4131301', 5 => '5131201',
    ];

    // Mapeo NIT AFP → código PILA oficial (cuando pensiones.codigo no tiene el valor correcto)
    private const AFP_PILA = [
        '900336004' => '25-14',  // Colpensiones (NIT real en DB)
        '800197268' => '25-14',  // Colpensiones (NIT alterno)
        '830002385' => '25-11',  // Protección
        '830001236' => '25-05',  // Porvenir
        '800200780' => '25-07',  // Skandia / Old Mutual
        '830025804' => '25-10',  // Colfondos
    ];



    /** Alfanumérico → mayúsculas Latin-1, relleno espacios derecha */
    private function A(string $val, int $len): string
    {
        $val = mb_strtoupper(trim($val), 'UTF-8');
        $val = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $val);
        return str_pad(substr($val, 0, $len), $len, ' ', STR_PAD_RIGHT);
    }

    /** Numérico → solo dígitos, relleno ceros izquierda */
    private function N(string $val, int $len): string
    {
        $val = preg_replace('/[^0-9]/', '', $val);
        if ($val === '') $val = '0';
        return str_pad(substr($val, -$len), $len, '0', STR_PAD_LEFT);
    }

    /** Fecha AAAA-MM-DD o 10 espacios — maneja formatos español y SQL Server */
    private function fecha(?string $val): string
    {
        if (empty($val)) return str_repeat(' ', 10);
        $val = trim((string)$val);

        // 1) Formato ISO directo: YYYY-MM-DD (lo que guarda SQL Server)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // 2) Formato español: "26-abr.", "26-abr-2026", "26/abr/2026"
        static $meses = [
            'ene'=>'01','feb'=>'02','mar'=>'03','abr'=>'04',
            'may'=>'05','jun'=>'06','jul'=>'07','ago'=>'08',
            'sep'=>'09','oct'=>'10','nov'=>'11','dic'=>'12',
        ];
        if (preg_match('/(\d{1,2})[-\/\s]([a-z]{3})\.?(?:[-\/\s](\d{4}))?/i', $val, $m)) {
            $d  = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mo = $meses[strtolower($m[2])] ?? '01';
            $y  = !empty($m[3]) ? $m[3] : date('Y');
            return "{$y}-{$mo}-{$d}";
        }

        // 3) Cualquier otro formato — dejar a Carbon
        try { return \Carbon\Carbon::parse($val)->format('Y-m-d'); }
        catch (\Exception $e) { return str_repeat(' ', 10); }
    }

    /** Redondeo PILA: al multiplo de 100 superior (ceil) */
    private function roundPila(float $val): int
    {
        return (int)(ceil($val / 100) * 100);
    }

    public function generar(array $params): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $aliadoId      = $params['aliado_id'];
        $razonSocialId = $params['razon_social_id'];
        $mesPago       = (int)$params['mes'];
        $anioPago      = (int)$params['anio'];
        $nPlano        = (int)$params['n_plano'];
        $tiposModal    = $params['tipos_modalidad'] ?? [];

        // mesPago = mes de pago (ej: 5=Mayo)
        // mesVencido = mes que se liquida (ej: 4=Abril) — los dependientes tienen mes_plano = vencido
        $mesVencido  = $mesPago > 1 ? $mesPago - 1 : 12;
        $anioVencido = $mesPago > 1 ? $anioPago    : $anioPago - 1;

        $rs = DB::table('razones_sociales')
            ->where('id', $razonSocialId)->where('aliado_id', $aliadoId)->first();
        if (!$rs) throw new \RuntimeException("Razón social {$razonSocialId} no encontrada.");

        $codigoArlRs = null;
        if (!empty($rs->arl_nit)) {
            $codigoArlRs = DB::table('arls')
                ->where(DB::raw('CAST(nit AS VARCHAR(20))'), (string)$rs->arl_nit)
                ->value('codigo');
        }

        $query = DB::table('planos AS p')
            ->leftJoin('facturas AS f',       'f.id',          '=', 'p.factura_id')
            ->leftJoin('clientes AS cl',      'cl.cedula',     '=', 'p.no_identifi')
            ->leftJoin('ciudades AS c',       'c.id_ciudad_t', '=', 'cl.municipio_id')
            ->leftJoin('departamentos AS d',  'd.id',          '=', 'cl.departamento_id')
            ->leftJoin('pensiones AS afp_t',  DB::raw('CAST(afp_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_afp'))
            ->leftJoin('eps AS eps_t',        DB::raw('CAST(eps_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_eps'))
            ->leftJoin('cajas AS caj_t',      DB::raw('CAST(caj_t.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_caja'))
            ->leftJoin('arl_tarifas AS arl_t','arl_t.nivel',  '=', 'p.nivel_riesgo')
            ->leftJoin('arls AS arl_m',       DB::raw('CAST(arl_m.nit AS VARCHAR(20))'), '=', DB::raw('p.cod_arl'))
            ->where('p.aliado_id',       $aliadoId)
            ->where('p.razon_social_id', $razonSocialId)
            ->where('p.n_plano',         $nPlano)
            ->whereIn('p.tipo_reg',      ['planilla', 'retiro'])
            ->where(fn($q) => $q->where('p.num_dias', '>=', 1)->orWhere('p.tipo_reg', '!=', 'retiro'))
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($mesPago, $anioPago, $mesVencido, $anioVencido) {
                // Independientes (tipo 11): usan mes de pago
                // Dependientes (otros): usan mes vencido (mes anterior al pago)
                $q->where(function ($i) use ($mesPago, $anioPago) {
                    $i->where('p.tipo_modalidad_id', 11)
                      ->where('p.mes_plano', $mesPago)->where('p.anio_plano', $anioPago);
                })->orWhere(function ($i) use ($mesVencido, $anioVencido) {
                    $i->where('p.tipo_modalidad_id', '<>', 11)
                      ->where('p.mes_plano', $mesVencido)->where('p.anio_plano', $anioVencido);
                });
            })
            ->select([
                'p.tipo_doc','p.no_identifi','p.tipo_modalidad_id','p.tipo_p',
                'p.primer_nombre','p.segundo_nombre','p.primer_ape','p.segundo_ape',
                'p.cod_eps','p.cod_afp','p.cod_arl','p.cod_caja',
                'p.salario_basico','p.num_dias','p.nivel_riesgo',
                DB::raw("CONVERT(VARCHAR(10), p.fecha_ing, 23) AS fecha_ing"),
                DB::raw("CONVERT(VARCHAR(10), p.fecha_ret, 23) AS fecha_ret"),
                DB::raw('eps_t.codigo  AS cod_eps_pila'),
                DB::raw('afp_t.codigo  AS cod_afp_pila'),
                DB::raw('arl_m.codigo  AS cod_arl_pila'),
                DB::raw('caj_t.codigo  AS cod_caj_pila'),
                'f.v_eps','f.v_afp','f.v_arl','f.v_caja','f.dias_cotizados',
                'cl.genero',
                DB::raw("DATEDIFF(YEAR, cl.fecha_nacimiento, GETDATE()) AS edad_calculada"),
                DB::raw('d.id                    AS dep_id'),
                DB::raw('CAST(c.Municipio AS INT) AS mun_id'),
            ]);

        if (!empty($tiposModal)) $query->whereIn('p.tipo_modalidad_id', $tiposModal);
        $planos = $query->orderBy('p.primer_ape')->orderBy('p.primer_nombre')->get();

        $nit     = preg_replace('/[^0-9]/', '', (string)($rs->nit ?? ''));
        $periodo = sprintf('%04d%02d', $anioPago, $mesPago);
        $actEco  = $this->N((string)($rs->actividad_economica ?? '0'), 7);

        // Campo 20 Tipo1: valor_total_nomina = SUMA de IBC caja de cada cotizante
        // (MiPlanilla valida que coincida con sumatoria de campo 45 del Tipo 2)
        $valorNomina = 0;
        foreach ($planos as $_p) {
            $ibcF  = (int)($_p->salario_basico ?? 0);
            $dias_ = (int)($_p->num_dias ?? $_p->dias_cotizados ?? 30);
            $ibcP  = $dias_ < 30 ? (int)(ceil($ibcF * $dias_ / 30 / 100) * 100) : $ibcF;
            $cajP  = !empty($_p->cod_caj_pila) ? $_p->cod_caj_pila : 'CCF68';
            $valorNomina += ($cajP === 'CCF68') ? 100 : $ibcP;
        }

        $lineas   = [];
        $lineas[] = $this->tipo1($rs, $nit, $periodo, count($planos), $codigoArlRs, (int)$valorNomina);

        // periodoLiq = mes vencido (para validar que ING/RET estén dentro del período)
        $periodoLiq = sprintf('%04d-%02d', $anioVencido, $mesVencido); // ej: '2026-04'

        foreach ($planos as $i => $p) {
            $lineas[] = $this->tipo2($p, $i + 1, $actEco, $codigoArlRs, $periodoLiq);
        }

        $filename  = "miplanilla_{$periodo}_RS{$razonSocialId}.txt";
        $contenido = implode("\r\n", $lineas);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $filename, [
            'Content-Type'        => 'text/plain; charset=latin-1',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Registro Tipo 1 — 359 chars, 22 campos (Resolución 2388) ─────────────
    private function tipo1(object $rs, string $nit, string $periodo, int $total, ?string $codigoArlRs, int $valorNomina): string
    {
        $anio = (int)substr($periodo, 0, 4);
        $mes  = (int)substr($periodo, 4, 2);

        // periodoNoSalud = mes vencido (un mes antes del pago) ej: 2026-04
        // periodoSalud   = mes de pago ej: 2026-05
        $mesVenc  = $mes > 1 ? $mes - 1 : 12;
        $anioVenc = $mes > 1 ? $anio    : $anio - 1;
        $periodoNoSalud = sprintf('%04d-%02d', $anioVenc, $mesVenc);
        $periodoSalud   = sprintf('%04d-%02d', $anio, $mes);

        $dv = str_pad(preg_replace('/[^0-9]/', '', (string)($rs->dv ?? '0')), 1, '0', STR_PAD_LEFT);

        $linea =
            '01'                                                   // 1  tipo_registro       N 2   pos 1-2
            . '1'                                                  // 2  modalidad_planilla   N 1   pos 3   (1=electrónica)
            . '0001'                                               // 3  secuencia            N 4   pos 4-7
            . $this->A($rs->razon_social ?? '', 200)               // 4  razon_social         A 200 pos 8-207
            . $this->A('NI', 2)                                    // 5  tipo_doc_aportante   A 2   pos 208-209
            . $this->A($nit, 16)                                   // 6  num_doc_aportante    A 16  pos 210-225
            . $dv                                                   // 7  digito_verificacion  N 1   pos 226
            . $this->A('E', 1)                                     // 8  tipo_planilla        A 1   pos 227 (E=empresa)
            . $this->A('', 10)                                     // 9  num_planilla_asoc    N 10  pos 228-237 (vacío si no es corrección)
            . $this->A('', 10)                                     // 10 fecha_pago_asoc      A 10  pos 238-247
            . $this->A('S', 1)                                     // 11 forma_presentacion   A 1   pos 248 (S=sucursal)
            . $this->A($rs->codigo_sucursal ?? '', 10)             // 12 codigo_sucursal      A 10  pos 249-258
            . $this->A($rs->nombre_sucursal ?? '', 40)             // 13 nombre_sucursal      A 40  pos 259-298
            . $this->A($codigoArlRs ?? '', 6)                      // 14 codigo_arl           A 6   pos 299-304
            . $this->A($periodoNoSalud, 7)                         // 15 periodo_no_salud     A 7   pos 305-311
            . $this->A($periodoSalud, 7)                           // 16 periodo_salud        A 7   pos 312-318
            . str_pad('1', 10, '0', STR_PAD_LEFT)                  // 17 num_radicacion       N 10  pos 319-328
            . $this->A('', 10)                                     // 18 fecha_pago           A 10  pos 329-338
            . str_pad((string)$total, 5, '0', STR_PAD_LEFT)        // 19 total_empleados      N 5   pos 339-343
            . str_pad((string)$valorNomina, 12, '0', STR_PAD_LEFT) // 20 valor_total_nomina   N 12  pos 344-355
            . '01'                                                  // 21 tipo_aportante       N 2   pos 356-357
            . '88';                                                 // 22 codigo_operador      N 2   pos 358-359

        if (strlen($linea) !== 359) {
            throw new \RuntimeException("Tipo 1 generó " . strlen($linea) . " chars (esperado 359).");
        }
        return $linea;
    }

    // ── Registro Tipo 2 — 693 chars, 98 campos ───────────────────────────────
    private function tipo2(object $p, int $seq, string $actEco, ?string $codigoArlRs, string $periodoLiq = ''): string
    {
        $esIndep   = in_array((int)$p->tipo_modalidad_id, [10, 11]);
        $tipoCot   = $esIndep ? '2' : '1';
        $exonerado = $esIndep ? 'N' : 'S';

        // Normalizar: valor '0' equivale a null (sin entidad asignada)
        $codAfpRaw = (trim((string)($p->cod_afp  ?? '')) === '0') ? null : ($p->cod_afp  ?? null);
        $codEpsRaw = (trim((string)($p->cod_eps  ?? '')) === '0') ? null : ($p->cod_eps  ?? null);
        $codCajRaw = (trim((string)($p->cod_caja ?? '')) === '0') ? null : ($p->cod_caja ?? null);

        $edad   = isset($p->edad_calculada) ? (int)$p->edad_calculada : null;
        $genero = strtoupper(trim($p->genero ?? ''));

        // Exención por edad (sin obligación de cotizar pensión)
        $isExento = $edad !== null
            && (($genero === 'M' && $edad >= 55) || ($genero === 'F' && $edad >= 50));

        $vAfpFactura = (int)($p->v_afp ?? 0);

        // Reglas tienePension:
        // 1) Sin cod_afp (null/'0')               → false
        // 2) Con cod_afp, cualquier edad, v_afp>0  → true  (sigue pagando)
        // 3) Con cod_afp, exento por edad, v_afp=0 → false (subtipo 03)
        // 4) Con cod_afp, joven, v_afp=0           → true  (calcula igual)
        $tienePension = !empty($codAfpRaw) && (!$isExento || $vAfpFactura > 0);

        $subtipo = '00';
        if (!$tienePension && $edad !== null) {
            $subtipo = $isExento ? '03' : '04';
        }

        $tipoDoc = strtoupper(trim($p->tipo_doc ?? 'CC'));
        $mapaDoc = ['C' => 'CC', 'NIT' => 'CC', 'PT' => 'CE', 'NUIP' => 'CC'];
        $tipoDoc = $mapaDoc[$tipoDoc] ?? $tipoDoc;
        $esExtranjero = !in_array($tipoDoc, ['CC', 'TI', 'RC', 'SC']) ? 'X' : ' ';

        $ibcFull = (int)($p->salario_basico ?? 0);
        // p.num_dias = fuente de verdad; f.dias_cotizados puede venir erróneo de la migración
        $dias    = (int)($p->num_dias ?? $p->dias_cotizados ?? 30);
        $esIntegral = strtoupper(trim($p->tipo_p ?? '')) === 'I' ? 'X' : 'F';

        // Campo 40 = salario completo (MiPlanilla lo muestra como salario base)
        // Campos 42-44 = IBC proporcional con round() estándar (no ceil/100)
        // round(1,750,905 × 5/30) = 291,818 ← lo que MiPlanilla espera
        $ibcProp = $dias < 30
            ? (int)round($ibcFull * $dias / 30)
            : $ibcFull;

        // Cotizaciones desde IBC proporcional con ceil al 100 superior
        $nivel = max(1, min(5, (int)($p->nivel_riesgo ?? 1)));
        static $tasasArl = [
            1 => 0.00522, 2 => 0.01044, 3 => 0.02436,
            4 => 0.04350, 5 => 0.06960,
        ];
        $vAfp = $tienePension ? $this->roundPila($ibcProp * 0.16) : 0;
        $vEps = $this->roundPila($ibcProp * ($exonerado === 'S' ? 0.04 : 0.125));
        $vArl = $this->roundPila($ibcProp * $tasasArl[$nivel]);

        // Códigos entidades
        $codCajTmp = !empty($p->cod_caj_pila) ? $p->cod_caj_pila : (empty($codCajRaw) ? 'CCF68' : $codCajRaw);

        // Sin caja propia (CCF68): IBC caja = $100, aporte = $100
        $ibcCaj = ($codCajTmp === 'CCF68') ? 100 : $ibcProp;
        $vCaj   = ($codCajTmp === 'CCF68') ? 100 : $this->roundPila($ibcProp * 0.04);

        $codEps = !empty($p->cod_eps_pila) ? $p->cod_eps_pila : ($codEpsRaw ?? '');
        $nitAfp   = preg_replace('/[^0-9]/', '', (string)($codAfpRaw ?? ''));
        $codAfpDb = $p->cod_afp_pila ?? null;
        if ($tienePension) {
            $codAfp = self::AFP_PILA[$nitAfp]
                ?? ((!empty($codAfpDb)) ? $codAfpDb : '');
        } else {
            $codAfp = ''; // sin pensión → campo AFP en blanco
        }

        $codArl = !empty($p->cod_arl_pila) ? $p->cod_arl_pila : ($codigoArlRs ?? '');
        $codCaj = !empty($p->cod_caj_pila) ? $p->cod_caj_pila : (empty($codCajRaw) ? 'CCF68' : $codCajRaw);

        $depId = str_pad((string)($p->dep_id ?? ''), 2, '0', STR_PAD_LEFT);
        $munId = str_pad((string)($p->mun_id ?? ''), 3, '0', STR_PAD_LEFT);
        if ($codCaj === 'CCF68') {
            $depId = '94';
            $munId = '001';
        }

        $tarifaArl   = self::TARIFA_ARL[$nivel];
        $tarifaSalud = $exonerado === 'S' ? '0.04000' : '0.12500';
        $tarifaSENA  = $exonerado === 'S' ? '0.00000' : '0.02000';
        $tarifaICBF  = $exonerado === 'S' ? '0.00000' : '0.03000';

        $ibcOtros = $exonerado === 'S' ? 0 : $ibcProp;
        $vSENA    = $exonerado === 'S' ? 0 : $this->roundPila($ibcProp * 0.02);
        $vICBF    = $exonerado === 'S' ? 0 : $this->roundPila($ibcProp * 0.03);

        // ING/RET: 'X' solo si la fecha está dentro del período liquidado
        // No marcar novedad si el ingreso/retiro fue en un período anterior
        $fechaIng  = $this->fecha($p->fecha_ing ?? null);
        $fechaRet  = $this->fecha($p->fecha_ret ?? null);
        $blanco10  = str_repeat(' ', 10);
        $periodoYm = substr($fechaIng, 0, 7); // 'YYYY-MM'
        $ing = ($fechaIng !== $blanco10 && $periodoYm >= $periodoLiq) ? 'X' : ' ';
        $periodoYmR = substr($fechaRet, 0, 7);
        $ret = ($fechaRet !== $blanco10 && $periodoYmR >= $periodoLiq) ? 'X' : ' ';




        $linea =
            $this->N('02', 2)                                   // 1  pos 1-2
            . $this->N((string)$seq, 5)                         // 2  pos 3-7
            . $this->A($tipoDoc, 2)                             // 3  pos 8-9
            . $this->A((string)$p->no_identifi, 16)             // 4  pos 10-25
            . $this->N($tipoCot, 2)                             // 5  pos 26-27
            . $this->N($subtipo, 2)                             // 6  pos 28-29
            . $this->A($esExtranjero, 1)                        // 7  pos 30
            . $this->A(' ', 1)                                  // 8  pos 31
            . $this->A($depId, 2)                               // 9  pos 32-33
            . $this->A($munId, 3)                               // 10 pos 34-36
            . $this->A($p->primer_ape    ?? '', 20)             // 11 pos 37-56
            . $this->A($p->segundo_ape   ?? '', 30)             // 12 pos 57-86
            . $this->A($p->primer_nombre ?? '', 20)             // 13 pos 87-106
            . $this->A($p->segundo_nombre ?? '', 30)            // 14 pos 107-136
            . $this->A($ing, 1)                                 // 15 pos 137
            . $this->A($ret, 1)                                 // 16 pos 138
            . $this->A(' ', 1)                                  // 17 TDE pos 139
            . $this->A(' ', 1)                                  // 18 TAE pos 140
            . $this->A(' ', 1)                                  // 19 TDP pos 141
            . $this->A(' ', 1)                                  // 20 TAP pos 142
            . $this->A(' ', 1)                                  // 21 VSP pos 143
            . $this->A(' ', 1)                                  // 22 Correcciones pos 144
            . $this->A(' ', 1)                                  // 23 VST pos 145
            . $this->A(' ', 1)                                  // 24 SLN pos 146
            . $this->A(' ', 1)                                  // 25 IGE pos 147
            . $this->A(' ', 1)                                  // 26 LMA pos 148
            . $this->A(' ', 1)                                  // 27 VAC-LR pos 149
            . $this->A(' ', 1)                                  // 28 AVP pos 150
            . $this->A(' ', 1)                                  // 29 VCT pos 151
            . $this->N('0', 2)                                  // 30 IRL pos 152-153
            . $this->A($codAfp, 6)                              // 31 pos 154-159
            . $this->A('', 6)                                   // 32 AFP traslada pos 160-165
            . $this->A($codEps, 6)                              // 33 pos 166-171
            . $this->A('', 6)                                   // 34 EPS traslada pos 172-177
            . $this->A($codCaj, 6)                              // 35 pos 178-183
            . $this->N((string)($tienePension ? $dias : 0), 2) // 36 días pensión 184-185
            . $this->N((string)$dias, 2)                        // 37 días salud 186-187
            . $this->N((string)$dias, 2)                        // 38 días riesgos 188-189
            . $this->N((string)$dias, 2)                        // 39 días CCF 190-191
            . $this->N((string)$ibcFull, 9)                      // 40 salario completo 192-200
            . $this->A($esIntegral, 1)                          // 41 tipo salario 201
            . ($tienePension ? $this->N((string)$ibcProp, 9) : $this->N('0', 9)) // 42 IBC pensión 202-210
            . $this->N((string)$ibcProp, 9)                       // 43 IBC salud 211-219
            . $this->N((string)$ibcProp, 9)                       // 44 IBC riesgos 220-228
            . $this->N((string)$ibcCaj, 9)                        // 45 IBC CCF 229-237 (100 si CCF68)
            . ($tienePension ? '0.16000' : '0000000')             // 46 tarifa pensión 238-244
            . ($tienePension ? $this->N((string)$vAfp, 9) : $this->N('0', 9)) // 47 cotización pensión 245-253
            . $this->N('0', 9)                                    // 48 aporte vol afiliado 254-262 (siempre 0)
            . $this->N('0', 9)                                    // 49 aporte vol aportante 263-271 (siempre 0)
            . ($tienePension ? $this->N((string)$vAfp, 9) : $this->N('0', 9)) // 50 total pensión 272-280
            . $this->N('0', 9)                                    // 51 FSP solidaridad 281-289 (siempre 0)
            . $this->N('0', 9)                                    // 52 FSP subsistencia 290-298 (siempre 0)
            . $this->N('0', 9)                                    // 53 valor no retenido 299-307 (siempre 0)
            . $tarifaSalud                                       // 54 tarifa salud 308-314
            . $this->N((string)$vEps, 9)                        // 55 cotización salud 315-323
            . $this->N('0', 9)                                  // 56 ADRES/UPC 324-332
            . $this->A('', 15)                                  // 57 auth incapacidad 333-347
            . $this->N('0', 9)                                  // 58 valor incapacidad 348-356
            . $this->A('', 15)                                  // 59 auth licencia 357-371
            . $this->N('0', 9)                                  // 60 valor licencia 372-380
            . $tarifaArl                                         // 61 tarifa riesgos 381-389
            . $this->N('1', 9)                                  // 62 centro de trabajo 390-398
            . $this->N((string)$vArl, 9)                        // 63 cotización ARL 399-407
            . '0.04000'                                          // 64 tarifa CCF 408-414 (formato decimal)
            . $this->N((string)$vCaj, 9)                        // 65 valor CCF 415-423
            . $tarifaSENA                                        // 66 tarifa SENA 424-430
            . $this->N((string)$vSENA, 9)                       // 67 valor SENA 431-439
            . $tarifaICBF                                        // 68 tarifa ICBF 440-446
            . $this->N((string)$vICBF, 9)                       // 69 valor ICBF 447-455
            . '0.00000'                                          // 70 tarifa ESAP 456-462
            . $this->N('0', 9)                                  // 71 valor ESAP 463-471
            . '0.00000'                                          // 72 tarifa MEN 472-478
            . $this->N('0', 9)                                  // 73 valor MEN 479-487
            . $this->A('', 2)                                   // 74 tipo doc principal 488-489
            . $this->A('', 16)                                  // 75 num doc principal 490-505
            . $this->A($exonerado, 1)                           // 76 exonerado 506
            . $this->A($codArl, 6)                              // 77 código ARL 507-512
            . $this->A((string)$nivel, 1)                       // 78 clase de riesgo 513
            . ' '                                                // 79 ind tarifa especial 514 (blanco=normal 16%)
            . $this->A($fechaIng, 10)                           // 80 fecha ingreso 515-524
            . $this->A($fechaRet, 10)                           // 81 fecha retiro 525-534
            . str_repeat(' ', 10)                               // 82 fecha VSP 535-544
            . str_repeat(' ', 10)                               // 83 fecha SLN ini 545-554
            . str_repeat(' ', 10)                               // 84 fecha SLN fin 555-564
            . str_repeat(' ', 10)                               // 85 fecha IGE ini 565-574
            . str_repeat(' ', 10)                               // 86 fecha IGE fin 575-584
            . str_repeat(' ', 10)                               // 87 fecha LMA ini 585-594
            . str_repeat(' ', 10)                               // 88 fecha LMA fin 595-604
            . str_repeat(' ', 10)                               // 89 fecha VAC ini 605-614
            . str_repeat(' ', 10)                               // 90 fecha VAC fin 615-624
            . str_repeat(' ', 10)                               // 91 fecha VCT ini 625-634
            . str_repeat(' ', 10)                               // 92 fecha VCT fin 635-644
            . str_repeat(' ', 10)                               // 93 fecha IRL ini 645-654
            . str_repeat(' ', 10)                               // 94 fecha IRL fin 655-664
            . $this->N((string)$ibcOtros, 9)                    // 95 IBC otros paraf 665-673
            . $this->N((string)(8 * $dias), 3)                  // 96 horas laboradas 674-676
            . str_repeat(' ', 10)                               // 97 fecha radicación 677-686
            . self::ACTECO_ARL[$nivel];                          // 98 actividad económica 687-693 (Decreto 768/2022)

        if (strlen($linea) !== 693) {
            throw new \RuntimeException(
                "Tipo 2 generó " . strlen($linea) . " chars (esperado 693) en cotizante " . $p->no_identifi
            );
        }
        return $linea;
    }
}
