<?php

namespace App\Services;

use App\Models\ArlTarifa;
use App\Models\Cliente;
use App\Models\ConfiguracionBrynex;
use App\Models\Contrato;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use App\Services\UpcAdicionalService;
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

        $upc = null; // solo se llena si la modalidad es UPC

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
        } elseif ((int) ($p['tipo_modalidad_id'] ?? 0) === Contrato::MODALIDAD_UPC) {
            // ── UPC adicional: el valor de EPS no es % de IBC, es una tarifa
            //    fija por edad/sexo/zona del beneficiario (Resolución 2764/2025).
            //    Siempre es solo salud: nunca lleva ARL, pensión ni caja.
            $upc = ['valor' => null, 'zona' => 'normal', 'edad' => null, 'advertencia' => null];
            $cliente = $cedula ? Cliente::where('cedula', $cedula)->first() : null;

            if (!$cliente) {
                $upc['advertencia'] = 'No se encontró el cliente por cédula: no se puede calcular el valor de UPC adicional.';
            } else {
                $upc = UpcAdicionalService::valorParaCliente($cliente);
            }

            $epsMes   = $upc['valor'] ?? 0;
            $arlMes   = 0;
            $penMes   = 0;
            $cajaMes  = 0;
            $eps      = $epsMes; // no se prorratea por días: es un valor fijo mensual
            $arl      = 0;
            $pen      = 0;
            $caja     = 0;
            $ss       = $eps;
            $diasArl  = $dias;
            $diasAfp  = $dias;
            $diasCaja = $dias;
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
            // ceil al centena superior en TODOS los subsistemas: es la regla que
            // aplica el operador PILA (Res. 2388). Con round() la cotización queda
            // $100 por debajo de lo que liquida Enlace en ARL/AFP/CAJA.
            // Fuente única: Contrato::calcularCotizacion().
            $eps  = $dias < 30 ? $r($epsMes  * $dias / 30) : $epsMes;
            $arl  = $dias < 30 ? $r($arlMes  * $dias / 30) : $arlMes;
            $pen  = $dias < 30 ? $r($penMes  * $dias / 30) : $penMes;
            // Cargo sin-CCF es fijo: NO se prorratea por días
            $caja = ($cajaMes === Contrato::CARGO_SIN_CCF)
                ? $cajaMes
                : ($dias < 30 ? $r($cajaMes * $dias / 30) : $cajaMes);
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
            // Modalidad UPC
            'es_upc'            => (int) ($p['tipo_modalidad_id'] ?? 0) === Contrato::MODALIDAD_UPC,
            'upc_zona'          => $upc['zona'] ?? null,
            'upc_edad'          => $upc['edad'] ?? null,
            'upc_advertencia'   => $upc['advertencia'] ?? null,
            // Contexto adicional útil para la IA
            'plan_nombre'          => $plan->nombre ?? null,
            'tipo_modalidad_nombre'=> $tipoModalidad->nombre ?? null,
        ];
    }
}
