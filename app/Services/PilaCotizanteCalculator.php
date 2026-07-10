<?php

namespace App\Services;

use App\Models\ConfiguracionBrynex;

/**
 * PilaCotizanteCalculator
 *
 * Centraliza TODAS las reglas de negocio PILA para cotizantes:
 *   - Tipo / subtipo cotizante
 *   - tienePension (reglas v_afp + edad + cod_afp)
 *   - Planilla K (tipo_modalidad_id = -1): solo ARL, tipo 23
 *   - IBC proporcional por días trabajados
 *   - CCF68 (sin caja propia)
 *   - Exonerado SENA/ICBF
 *
 * Uso en cualquier servicio de exportación PILA:
 *   $c = PilaCotizanteCalculator::calcular($planoRow);
 *   // $c['tipoCotizante'], $c['ibcProp'], etc.
 */
class PilaCotizanteCalculator
{
    // ── Constantes de umbrales de edad ──────────────────────────────────────
    private const EDAD_EXENTO_M = 55; // hombres ≥ 55 → subtipo 03
    private const EDAD_EXENTO_F = 50; // mujeres ≥ 50 → subtipo 03

    // ── tipo_modalidad_id especiales ────────────────────────────────────────
    private const TIPO_K_MATRIZ        = -1; // Planilla K: solo ARL, tipo cotizante 23
    private const TIPOS_INDEPENDIENTE  = [10, 11]; // tipo cotizante 2
    // Tiempo parcial: detectado por tm.es_tiempo_parcial = 1, tipo_cot viene de la tabla

    // ── Tarifas ARL por nivel de riesgo ────────────────────────────────────
    public const TARIFAS_ARL = [
        1 => 0.00522, 2 => 0.01044, 3 => 0.02436,
        4 => 0.04350, 5 => 0.06960,
    ];

    /**
     * Calcula todos los valores necesarios para generar un registro PILA.
     *
     * @param  object $p  Fila del plano con sus JOINs ya resueltos
     * @return array {
     *   tipoCotizante: int,       // 1=dep, 2=indep, 23=K
     *   subtipoCotizante: int,    // 0, 3, 4
     *   tienePension: bool,
     *   esKMatriz: bool,          // true si tipo_modalidad_id = -1
     *   esIndependiente: bool,
     *   exonerado: string,        // 'S' o 'N'  (exonerado SENA/ICBF)
     *   ibcFull: int,             // salario completo (campo 40)
     *   ibcProp: int,             // IBC proporcional (EPS/ARL/AFP)
     *   ibcAfp: int,              // 0 si !tienePension o K
     *   ibcEps: int,              // 0 si K
     *   ibcArl: int,              // ibcFull si K (30 días), ibcProp si normal
     *   ibcCcf: int,              // 100 si CCF68, 0 si K
     *   dias: int,                // días reales del plano
     *   diasPension: int,         // 0 si !tienePension o K
     *   diasSalud: int,           // 0 si K
     *   diasArl: int,             // 30 si K, dias si normal
     *   diasCcf: int,             // 0 si K
     *   vAfp: int,
     *   vEps: int,
     *   vArl: int,
     *   vCcf: int,
     *   codAfpPila: string,       // código PILA de AFP (campo 31)
     *   codEpsPila: string,       // código PILA de EPS (campo 33)
     *   codCcfPila: string,       // código PILA de CCF (campo 35), 'CCF68' si sin caja
     *   codArlPila: string,
     *   tarifaAfpDecimal: float,  // 0.16 o 0.0 si no aplica
     *   tarifaEpsStr: string,     // '0.04000' o '0.12500' (para TXT)
     *   tarifaSenaStr: string,
     *   tarifaIcbfStr: string,
     *   tarifaArlDecimal: float,  // para Excel
     *   nivelRiesgo: int,
     *   ibcOtros: int,            // IBC parafiscales (SENA/ICBF)
     *   vSena: int,
     *   vIcbf: int,
     *   sinCaja: bool,            // true si codCcfPila='CCF68' (sin caja propia)
     *   ibcCcf: int,              // 100 si sin caja, ibcProp si tiene caja, 0 si K
     *   vCcf: int,                // 100 si sin caja, calculado si tiene, 0 si K
     *   depCod: string,           // '99' si sin caja o K, real si tiene caja
     *   munCod: string,           // '001' si sin caja o K
     * }
     */
    public static function calcular(object $p): array
    {
        $tipoModalidad = (int)($p->tipo_modalidad_id ?? 0);

        // ── Flags de tipo de cotizante ────────────────────────────────────────
        $esKMatriz       = ($tipoModalidad === self::TIPO_K_MATRIZ);
        $esIndep         = in_array($tipoModalidad, self::TIPOS_INDEPENDIENTE);
        $esTiempoParcial = (bool)($p->es_tiempo_parcial ?? false);

        $esIndependiente = (bool)($p->razonSocial?->es_independiente ?? false);

        // ── Tipo cotizante ───────────────────────────────────────────────────
        if ($esKMatriz) {
            $tipoCotizante = 23;
        } elseif ($esTiempoParcial) {
            $tipoCotizante = 51;
        } elseif ($esIndependiente) {
            $tipoCotizante = 59;
        } elseif ($esIndep) {
            $tipoCotizante = 2;
        } else {
            $tipoCotizante = 1;
        }

        // ── Tipo documento y regla de extranjero PILA ────────────────────────
        // Regla 1: extranjero CON AFP → NO marcar X (cotiza normal, sin excepción pensión)
        // Regla 2: extranjero SIN AFP → marcar X extranjero, subtipo = 0 (no 3 ni 4)
        $docsCol         = ['CC', 'TI', 'NUIP', 'RC', 'SC'];
        $tipoDocNorm     = strtoupper(trim($p->tipo_doc ?? 'CC'));
        $esExtranjeroDoc = !in_array($tipoDocNorm, $docsCol);

        // Normalizar códigos: '0' → null
        $codAfpRaw = (trim((string)($p->cod_afp  ?? '')) === '0') ? null : ($p->cod_afp  ?? null);
        $codEpsRaw = (trim((string)($p->cod_eps  ?? '')) === '0') ? null : ($p->cod_eps  ?? null);
        $codCajRaw = (trim((string)($p->cod_caja ?? '')) === '0') ? null : ($p->cod_caja ?? null);

        // ── Edad y género ───────────────────────────────────────────────────
        $genero = strtoupper(trim($p->genero ?? ''));
        $edad   = isset($p->edad_calculada) ? (int)$p->edad_calculada : null;

        // ── Días e IBC ──────────────────────────────────────────────────────
        // p.num_dias es la fuente de verdad (f.dias_cotizados puede estar mal por migración)
        $ibcFull = (int)($p->salario_basico ?? 0);
        $dias    = (int)($p->num_dias ?? $p->dias_cotizados ?? 30);
        $ibcProp = $dias < 30 ? (int)round($ibcFull * $dias / 30) : $ibcFull;

        // ── Nivel de riesgo ARL ─────────────────────────────────────────────
        $nivel    = max(1, min(5, (int)($p->nivel_riesgo ?? 1)));
        $tasaArl  = self::TARIFAS_ARL[$nivel];

        // ── Planilla K: solo ARL 30 días ────────────────────────────────────
        if ($esKMatriz) {
            $vArl = self::roundPila($ibcFull * $tasaArl);
            return [
                'tipoCotizante'    => 23,
                'subtipoCotizante' => 0,
                'tienePension'     => false,
                'esKMatriz'        => true,
                'esIndependiente'  => false,
                'esExtranjero'     => false,
                'exonerado'        => 'N',
                'ibcFull'          => $ibcFull,
                'ibcProp'          => $ibcFull,
                'ibcAfp'           => 0,
                'ibcEps'           => 0,
                'ibcArl'           => $ibcFull,
                'ibcCcf'           => 0,
                'dias'             => $dias,
                'diasPension'      => 0,
                'diasSalud'        => 0,
                'diasArl'          => 30,
                'diasCcf'          => 0,
                'vAfp'             => 0,
                'vEps'             => 0,
                'vArl'             => $vArl,
                'vCcf'             => 0,
                'codAfpPila'       => '',
                'codEpsPila'       => '',
                'codCcfPila'       => '',
                'codArlPila'       => $p->cod_arl_pila ?? ($p->codigo_arl_pila ?? ''),
                'tarifaAfpDecimal' => 0.0,
                'tarifaEpsStr'     => '0.00000',
                'tarifaSenaStr'    => '0.00000',
                'tarifaIcbfStr'    => '0.00000',
                'tarifaArlStr'     => sprintf('%.5f', $tasaArl),
                'tarifaArlDecimal' => $tasaArl,
                'nivelRiesgo'      => $nivel,
                'ibcOtros'         => 0,
                'vSena'            => 0,
                'vIcbf'            => 0,
                'sinCaja'          => false,
                'esTiempoParcial'  => false,
                'depCod'           => '94',
                'munCod'           => '1',
                'horasLaboradas'   => 30 * 8,   // K: siempre 30 días
            ];
        }

        // ── Tiempo parcial (tipo cotizante 51) ─────────────────────────────
        if ($esTiempoParcial) {
            $diasArlTp  = 30;
            $diasAfpTp  = (int)($p->dias_afp  ?? 30);
            $diasCajaTp = (int)($p->dias_caja ?? 30);

            $smmlv    = (int) ConfiguracionBrynex::salarioMinimo();
            $ibcArlTp = $smmlv;

            // Calcular semanas e IBC por semanas (Decreto 2616 de 2013)
            $semanasAfp  = self::calcularSemanasTp($diasAfpTp);
            $semanasCaja = self::calcularSemanasTp($diasCajaTp);

            $salarioSemanal = $smmlv / 4;

            $ibcAfpTp  = (int)round($salarioSemanal * $semanasAfp);
            $ibcCajaTp = (int)round($salarioSemanal * $semanasCaja);

            $tienePension = !empty($codAfpRaw);

            // Extranjero: mismas reglas que bloque normal
            $esExtranjero = $esExtranjeroDoc && !$tienePension;

            // Subtipo cotizante para tipo 51:
            //   - Extranjero sin AFP → subtipo 0 (igual que bloque normal)
            //   - Sin AFP, no extranjero → subtipo 3 si exento por edad (H≥55/F≥50), 4 si no exento
            //   - Con AFP → subtipo 0
            $subtipoCotizante = 0;
            if (!$tienePension && !$esExtranjero && $edad !== null) {
                $isExento         = ($genero === 'M' && $edad >= self::EDAD_EXENTO_M)
                                 || ($genero === 'F' && $edad >= self::EDAD_EXENTO_F);
                $subtipoCotizante = $isExento ? 3 : 4;
            }

            // Caja
            $codCcfFin = 'CCF68';
            if (!empty($codCajRaw)) {
                $nitCajaLimpio = preg_replace('/[^0-9]/', '', (string)$codCajRaw);
                $cajaObj = \App\Models\Caja::where('nit', $nitCajaLimpio)->orWhere('codigo', $codCajRaw)->first();
                $codCcfFin = ($cajaObj && !empty($cajaObj->codigo)) ? $cajaObj->codigo : $codCajRaw;
            }
            $sinCaja    = ($codCcfFin === 'CCF68');
            $ibcCcfTp   = $sinCaja ? ($esIndependiente ? 0 : 100) : $ibcCajaTp;
            $vCcfTp     = $sinCaja ? ($esIndependiente ? 0 : 100) : self::roundPila($ibcCajaTp * 0.04);

            $vArlTp = self::roundPila($ibcArlTp * $tasaArl);
            $vAfpTp = $tienePension ? self::roundPila($ibcAfpTp * 0.16) : 0;

            $codArlPila = $p->cod_arl_pila ?? $p->codigo_arl_pila ?? '';
            $codAfpPila = '';
            if ($tienePension) {
                $nitAfp     = preg_replace('/[^0-9]/', '', (string)$codAfpRaw);
                $codAfpDb   = $p->cod_afp_pila ?? $p->codigo_afp ?? null;
                $codAfpPila = !empty($codAfpDb) ? $codAfpDb : $nitAfp;
            }

            if ($sinCaja) {
                $depCod = '94'; $munCod = '1';
            } else {
                $depCod = str_pad((string)($p->dep_id ?? $p->cod_departamento ?? ''), 2, '0', STR_PAD_LEFT);
                $munCod = str_pad((string)($p->mun_id ?? $p->cod_municipio    ?? ''), 3, '0', STR_PAD_LEFT);
            }

            return [
                'tipoCotizante'    => 51,
                'subtipoCotizante' => $subtipoCotizante, // 0 con AFP o extranjero; 3 exento edad; 4 sin AFP sin edad exenta
                'tienePension'     => $tienePension,
                'esKMatriz'        => false,
                'esTiempoParcial'  => true,
                'esIndependiente'  => false,
                'esExtranjero'     => $esExtranjero,
                // Tipo 51 NO está exonerado de parafiscales (PILA Res. 2388)
                'exonerado'        => 'N',
                'ibcFull'          => $ibcCajaTp, // Para tiempo parcial, el salario mensual reportado es la base de caja por semanas
                'ibcProp'          => $ibcCajaTp,
                'ibcAfp'           => $tienePension ? $ibcAfpTp : 0,
                'ibcEps'           => 0,
                'ibcArl'           => $ibcArlTp,
                'ibcCcf'           => $ibcCcfTp,
                'dias'             => $dias,
                'diasPension'      => $tienePension ? $diasAfpTp : 0,
                'diasSalud'        => 0,
                'diasArl'          => $diasArlTp,
                'diasCcf'          => $diasCajaTp,
                'vAfp'             => $vAfpTp,
                'vEps'             => 0,
                'vArl'             => $vArlTp,
                'vCcf'             => $vCcfTp,
                'codAfpPila'       => $codAfpPila,
                'codEpsPila'       => '',
                'codCcfPila'       => $codCcfFin,
                'codArlPila'       => (string)$codArlPila,
                'sinCaja'          => $sinCaja,
                'tarifaAfpDecimal' => $tienePension ? 0.16 : 0.0,
                'tarifaEpsStr'     => '0.00000',
                'tarifaSenaStr'    => '0.00000',
                'tarifaIcbfStr'    => '0.00000',
                'tarifaArlStr'     => sprintf('%.5f', $tasaArl),
                'tarifaArlDecimal' => $tasaArl,
                'nivelRiesgo'      => $nivel,
                'ibcOtros'         => 0,
                'vSena'            => 0,
                'vIcbf'            => 0,
                'depCod'           => $depCod,
                'munCod'           => $munCod,
                // Tipo 51: horas = dias_caja (días reales trabajados) × 8, NO num_dias
                'horasLaboradas'   => $diasCajaTp * 8,
            ];
        }

        // ── Lógica normal (dependiente / independiente) ──────────────────────

        // ── tienePension ────────────────────────────────────────────────────
        // La ÚNICA señal es cod_afp: si el plan facturó con AFP → siempre cotiza pensión
        // independientemente de la edad del cliente.
        // Solo cuando cod_afp está vacío (plan sin AFP) se aplica la regla de edad:
        //   subtipo 3 → exento por edad (hombre ≥55 / mujer ≥50)
        //   subtipo 4 → reclamó pensión anticipada (cualquier edad, sin cod_afp)
        $tienePension = !empty($codAfpRaw);

        // ── Extranjero PILA ──────────────────────────────────────────────────
        // Regla 1: extranjero CON AFP → NO marcar X (cotiza pensión normalmente)
        // Regla 2: extranjero SIN AFP → marcar X, subtipo=0 (no aplican reglas de edad)
        $esExtranjero = $esExtranjeroDoc && !$tienePension;

        // ── Subtipo cotizante ─────────────────────────────────────────────────
        $subtipoCotizante = 0;
        if (!$tienePension && !$esExtranjero && $edad !== null) {
            // Solo aplica regla de edad para NO extranjeros sin pensión
            $isExento         = ($genero === 'M' && $edad >= self::EDAD_EXENTO_M)
                             || ($genero === 'F' && $edad >= self::EDAD_EXENTO_F);
            $subtipoCotizante = $isExento ? 3 : 4;
        }

        // ── Exonerado SENA/ICBF ────────────────────────────────────────────────
        // Dependientes → S (empresa paga 4% EPS, exonerado de SENA/ICBF)
        // Independientes/K/TP → N
        $exonerado = (!$esIndep && !$esKMatriz && !$esTiempoParcial && !$esIndependiente) ? 'S' : 'N';

        // ── Código CCF ─────────────────────────────────────────────────────
        $codCcfFin = 'CCF68';
        if (!empty($codCajRaw)) {
            $nitCajaLimpio = preg_replace('/[^0-9]/', '', (string)$codCajRaw);
            $cajaObj = \App\Models\Caja::where('nit', $nitCajaLimpio)->orWhere('codigo', $codCajRaw)->first();
            $codCcfFin = ($cajaObj && !empty($cajaObj->codigo)) ? $cajaObj->codigo : $codCajRaw;
        }

        // ── IBC por subsistema ──────────────────────────────────────────────
        $ibcAfp = $tienePension ? $ibcProp : 0;
        $ibcEps = $ibcProp;
        $ibcArl = $ibcProp;
        $ibcCcf = ($codCcfFin === 'CCF68') ? ($esIndependiente ? 0 : 100) : $ibcProp;

        // ── Cotizaciones ───────────────────────────────────────────────────
        $vAfp = $tienePension ? self::roundPila($ibcProp * 0.16) : 0;
        $vEps = self::roundPila($ibcProp * ($exonerado === 'S' ? 0.04 : 0.125));
        $vArl = self::roundPila($ibcProp * $tasaArl);
        $vCcf = ($codCcfFin === 'CCF68')
            ? ($esIndependiente ? 0 : 100)
            : self::roundPila($ibcProp * 0.04);

        // ── Tarifas EPS/SENA/ICBF ──────────────────────────────────────────
        $tarifaEpsStr  = $exonerado === 'S' ? '0.04000' : '0.12500';
        $tarifaSenaStr = $exonerado === 'S' ? '0.00000' : '0.02000';
        $tarifaIcbfStr = $exonerado === 'S' ? '0.00000' : '0.03000';

        // ── Parafiscales ────────────────────────────────────────────────────
        $ibcOtros = $exonerado === 'S' ? 0 : $ibcProp;
        $vSena    = $exonerado === 'S' ? 0 : self::roundPila($ibcProp * 0.02);
        $vIcbf    = $exonerado === 'S' ? 0 : self::roundPila($ibcProp * 0.03);

        // ── Código AFP PILA ─────────────────────────────────────────────────
        $codAfpPila = '';
        if ($tienePension) {
            $nitAfp     = preg_replace('/[^0-9]/', '', (string)$codAfpRaw);
            $codAfpDb   = $p->cod_afp_pila ?? $p->codigo_afp ?? null; // TXT=cod_afp_pila, Excel=codigo_afp
            $codAfpPila = !empty($codAfpDb) ? $codAfpDb : $nitAfp;
        }

        // ── Código EPS / ARL ────────────────────────────────────────────────
        $codEpsPila = $p->cod_eps_pila ?? $p->codigo_eps ?? ($codEpsRaw ?? '');
        $codArlPila = $p->cod_arl_pila ?? $p->codigo_arl_pila ?? '';

        // ── Departamento / Municipio ─────────────────────────────────────────
        // Sin caja propia (CCF68): departamento 94 por defecto, municipio 1
        $sinCaja = ($codCcfFin === 'CCF68');
        if ($sinCaja) {
            $depCod = '94'; $munCod = '1';
        } else {
            $depCod = str_pad((string)($p->dep_id ?? $p->cod_departamento ?? ''), 2, '0', STR_PAD_LEFT);
            $munCod = str_pad((string)($p->mun_id ?? $p->cod_municipio    ?? ''), 3, '0', STR_PAD_LEFT);
        }

        return [
            'tipoCotizante'    => $tipoCotizante,
            'subtipoCotizante' => $subtipoCotizante,
            'tienePension'     => $tienePension,
            'esKMatriz'        => false,
            'esIndependiente'  => $esIndep,
            'esExtranjero'     => $esExtranjero,
            'exonerado'        => $exonerado,
            'ibcFull'          => $ibcFull,
            'ibcProp'          => $ibcProp,
            'ibcAfp'           => $ibcAfp,
            'ibcEps'           => $ibcEps,
            'ibcArl'           => $ibcArl,
            'ibcCcf'           => $ibcCcf,
            'dias'             => $dias,
            'diasPension'      => $tienePension ? $dias : 0,
            'diasSalud'        => $dias,
            'diasArl'          => $dias,
            'diasCcf'          => $dias,
            'vAfp'             => $vAfp,
            'vEps'             => $vEps,
            'vArl'             => $vArl,
            'vCcf'             => $vCcf,
            'codAfpPila'       => $codAfpPila,
            'codEpsPila'       => (string)$codEpsPila,
            'codCcfPila'       => $codCcfFin,
            'codArlPila'       => (string)$codArlPila,
            'tarifaAfpDecimal' => 0.16,
            'tarifaEpsStr'     => $tarifaEpsStr,
            'tarifaSenaStr'    => $tarifaSenaStr,
            'tarifaIcbfStr'    => $tarifaIcbfStr,
            'tarifaArlStr'     => sprintf('%.5f', $tasaArl),
            'tarifaArlDecimal' => $tasaArl,
            'nivelRiesgo'      => $nivel,
            'ibcOtros'         => $ibcOtros,
            'vSena'            => $vSena,
            'vIcbf'            => $vIcbf,
            'sinCaja'          => $sinCaja,
            'esTiempoParcial'  => false,
            'depCod'           => $depCod,
            'munCod'           => $munCod,
            'horasLaboradas'   => $dias * 8,    // Normal: num_dias × 8
        ];
    }

    /**
     * Calcula la cantidad de semanas a cotizar según los días laborados (Decreto 2616 de 2013).
     */
    private static function calcularSemanasTp(int $dias): int
    {
        if ($dias >= 1 && $dias <= 7) {
            return 1;
        } elseif ($dias >= 8 && $dias <= 14) {
            return 2;
        } elseif ($dias >= 15 && $dias <= 21) {
            return 3;
        } elseif ($dias > 21) {
            return 4;
        }
        return 0;
    }

    /**
     * Redondea al múltiplo de 100 superior (requerimiento PILA Resolution 2388).
     */
    public static function roundPila(float $val): int
    {
        return (int)(ceil($val / 100) * 100);
    }
}
