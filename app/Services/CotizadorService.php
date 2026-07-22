<?php

namespace App\Services;

use App\Models\ArlTarifa;
use App\Models\ConfiguracionBrynex;
use App\Models\Contrato;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de cotización de seguridad social (EPS/ARL/pensión/caja + administración + IVA).
 * Extraído de ContratoController::cotizar para reutilizarse también desde la tool de IA
 * (cotizar_plan) sin duplicar la lógica de tiempo parcial, cargo sin-CCF y prorrateo.
 */
class CotizadorService
{
    /**
     * @param array $p Parámetros: tipo_modalidad_id, plan_id, n_arl, salario, ibc,
     *                  administracion, admon_asesor, seguro, dias, cedula, porcentaje_caja
     */
    public static function calcular(array $p, ?int $alidoId): array
    {
        $tipoModalidad = TipoModalidad::find($p['tipo_modalidad_id'] ?? null);
        $planId        = (int) ($p['plan_id'] ?? 0);
        $plan          = PlanContrato::find($planId);
        $nivelArl      = (int) ($p['n_arl'] ?? 1);
        $salario       = (float) ($p['salario'] ?? 0);
        $ibc           = (float) ($p['ibc'] ?? $salario) ?: $salario; // nunca 0
        $admon         = (float) ($p['administracion'] ?? 0);
        $admonAsesor   = (float) ($p['admon_asesor'] ?? 0);
        $seguro        = (float) ($p['seguro'] ?? 0);
        $dias          = max(1, min(30, (int) ($p['dias'] ?? 30))); // entre 1 y 30
        $cedula        = $p['cedula'] ?? null;

        $esIndep = $tipoModalidad && $tipoModalidad->esIndependiente();
        $esTP    = $tipoModalidad && $tipoModalidad->esTiempoParcial();

        // Porcentajes
        $pctEps  = $esIndep ? ConfiguracionBrynex::pctSaludIndependiente()  : ConfiguracionBrynex::pctSaludDependiente();
        $pctPen  = $esIndep ? ConfiguracionBrynex::pctPensionIndependiente() : ConfiguracionBrynex::pctPensionDependiente();
        $pctArl  = ArlTarifa::porcentajePara($nivelArl, $alidoId);

        // Caja: empresa siempre 4%; independiente usa el valor enviado (2% o 0.6%)
        if ($esIndep) {
            $pctCajaReq = (float) ($p['porcentaje_caja'] ?? 0);
            $pctCaja    = $pctCajaReq ?: ConfiguracionBrynex::pctCajaIndependienteAlto();
        } else {
            $pctCaja = ConfiguracionBrynex::pctCajaDependiente(); // empresa: siempre 4%
        }

        // Redondear HACIA ARRIBA al 100 mas cercano (ceil)
        $r = fn($v) => ceil($v / 100) * 100;

        if ($esTP) {
            // ── Tiempo Parcial: IBC diferente por entidad, sin EPS ─────────
            $diasP      = $tipoModalidad->diasPorEntidad();
            $factorMap  = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
            $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
            $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

            $sm = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);

            $ibcArl  = $sm;
            $ibcAfp  = round($sm * $factorAfp);
            $ibcCaja = round($sm * $factorCaja);

            $eps      = 0;
            $arl      = ($plan && $plan->incluye_arl)     ? $r($ibcArl  * $pctArl  / 100) : 0;
            $pen      = ($plan && $plan->incluye_pension)  ? $r($ibcAfp  * $pctPen  / 100) : 0;
            $caja     = ($plan && $plan->incluye_caja)     ? $r($ibcCaja * $pctCaja / 100) : 0;
            $ss       = $eps + $arl + $pen + $caja;
            $epsMes   = 0;
            $arlMes   = $arl;
            $penMes   = $pen;
            $cajaMes  = $caja;
            $diasArl  = $diasP['arl'];
            $diasAfp  = $diasP['afp'];
            $diasCaja = $diasP['caja'];
        } else {
            // ── Normal: calculos por mes completo ──────────────────────
            $epsMes  = ($plan && $plan->incluye_eps)      ? $r($ibc * $pctEps  / 100) : 0;
            $arlMes  = ($plan && $plan->incluye_arl)      ? $r($ibc * $pctArl  / 100) : 0;
            $penMes  = ($plan && $plan->incluye_pension)   ? $r($ibc * $pctPen  / 100) : 0;
            $cajaMes = ($plan && $plan->incluye_caja)     ? $r($ibc * $pctCaja / 100) : 0;

            // ── Cargo sin-CCF: dependiente E (id=0) o Ingreso-Retiro (id=12) sin caja ──
            $tipoModalidadIdInt = (int) ($p['tipo_modalidad_id'] ?? -99);
            if ($cajaMes === 0 && in_array($tipoModalidadIdInt, Contrato::IDS_SIN_CCF)
                && $plan && !$plan->incluye_caja) {
                $cajaMes = Contrato::CARGO_SIN_CCF;
            }

            // Prorratear por dias cotizados (dias/30); admon y seguro siempre completos.
            $rRound = fn($v) => (int)(round($v / 100) * 100);
            $eps  = $dias < 30 ? $r($epsMes       * $dias / 30) : $epsMes;
            $arl  = $dias < 30 ? $rRound($arlMes  * $dias / 30) : $arlMes;
            $pen  = $dias < 30 ? $rRound($penMes  * $dias / 30) : $penMes;
            // Cargo sin-CCF es fijo: NO se prorratea por días
            $caja = ($cajaMes === Contrato::CARGO_SIN_CCF)
                ? $cajaMes
                : ($dias < 30 ? $rRound($cajaMes * $dias / 30) : $cajaMes);
            $ss   = $eps + $arl + $pen + $caja;
            $diasArl  = $dias;
            $diasAfp  = $dias;
            $diasCaja = $dias;
        }

        // Admon total = administracion + admon_asesor
        $admonTotal = $admon + $admonAsesor;
        $tieneIva   = false;
        if ($cedula) {
            $iva = DB::table('clientes')->where('cedula', (int) $cedula)->value('iva');
            $tieneIva = strtoupper(trim($iva ?? '')) === 'SI';
        }
        $pctIva = $tieneIva ? ConfiguracionBrynex::porcentajeIva() : 0;
        $iva    = $tieneIva ? $r($admonTotal * $pctIva / 100) : 0;
        $total  = $ss + $seguro + $admonTotal + $iva;

        $ibcSugerido = $esIndep ? $r($salario * ConfiguracionBrynex::pctIbcIndependienteSugerido() / 100) : null;

        return [
            'eps'               => $eps,
            'arl'               => $arl,
            'pen'               => $pen,
            'caja'              => $caja,
            'ss'                => $ss,
            'seguro'            => $seguro,
            'admon'             => $admonTotal,
            'admonBase'         => $admon,
            'admonAsesor'       => $admonAsesor,
            'iva'               => $iva,
            'total'             => $total,
            'dias'              => $dias,
            'epsMes'            => $epsMes,
            'arlMes'            => $arlMes,
            'penMes'            => $penMes,
            'cajaMes'           => $cajaMes,
            'ibcSugerido'       => $ibcSugerido,
            'pctEps'            => $pctEps,
            'pctPen'            => $pctPen,
            'pctArl'            => $pctArl,
            'pctCaja'           => $pctCaja,
            // Tiempo Parcial
            'es_tiempo_parcial' => $esTP,
            'dias_arl'          => $esTP ? $diasArl  : null,
            'dias_afp'          => $esTP ? $diasAfp  : null,
            'dias_caja'         => $esTP ? $diasCaja : null,
            // Contexto adicional útil para la IA
            'plan_nombre'          => $plan->nombre ?? null,
            'tipo_modalidad_nombre'=> $tipoModalidad->nombre ?? null,
        ];
    }
}
