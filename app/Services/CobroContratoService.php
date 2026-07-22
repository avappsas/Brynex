<?php

namespace App\Services;

use App\Models\ConfiguracionBrynex;
use App\Models\Contrato;
use App\Models\Factura;
use App\Services\MoraClienteService;
use Illuminate\Support\Facades\DB;

/**
 * Calcula cuánto debe pagar un contrato en un mes/año dado, cuando todavía NO ha sido
 * facturado para ese período (afiliación, planilla, IVA y mora estimada incluidos).
 *
 * Extraído de FacturacionController::cotizacionContrato para reutilizarse también desde
 * la tool de IA (consultar_cliente) sin duplicar esta lógica — la misma que alimenta la
 * pantalla /admin/cobros.
 */
class CobroContratoService
{
    /**
     * @return array{
     *   tipo: string, eps: int, arl: int, afp: int, caja: int, ss: int,
     *   admon: int, seguro: int, afiliacion: int, iva: int, mora: int,
     *   total: int, dias: int
     * }
     */
    public static function calcular(Contrato $contrato, int $mes, int $anio): array
    {
        // Detectar tipo
        $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
        $esIndAct        = (int) $contrato->tipo_modalidad_id === 11;
        $esArl           = (int) $contrato->tipo_modalidad_id === 15;
        $esMesIngreso    = false;

        if ($esArl) {
            // Gestión ARL siempre es cobro de afiliación, no planilla
            $esMesIngreso = true;
        } elseif ($contrato->fecha_ingreso) {
            $esMesIngreso = (int) $contrato->fecha_ingreso->month === $mes
                && (int) $contrato->fecha_ingreso->year === $anio;
        }

        $esAfiliacion = false;
        if ($esMesIngreso) {
            if ($esArl) {
                $esAfiliacion = true;
            } elseif (!$esIndependiente) {
                $esAfiliacion = true; // empresa: afiliación pura
            } elseif (!$esIndAct) {
                $esAfiliacion = true; // I Venc: afiliación pura
            }
        }
        $esIndActPrimerMes = $esIndAct && $esMesIngreso;

        if ($esArl && !$esMesIngreso) {
            $diasCotizar = 0;
            $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0, 'ss' => 0];
            $afiliacion  = 0;
            $seguro      = 0;
            $admon       = 0;
            $admonAsesor = 0;
            $iva         = 0;
            $total       = 0;
        } else {
            // Calcular días
            if ($esIndActPrimerMes) {
                $diasCotizar = max(1, 30 - (int) $contrato->fecha_ingreso->day + 1);
            } elseif ($esAfiliacion) {
                $diasCotizar = 0;
            } else {
                $diasCotizar = self::calcularDias($contrato, $mes, $anio);
            }

            // Calcular SS
            if ($esAfiliacion && !$esIndActPrimerMes) {
                $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0, 'ss' => 0];
            } else {
                $cotizacion = $contrato->calcularCotizacion($diasCotizar);
                $calcSS = [
                    'eps'  => (int) ($cotizacion['eps']  ?? 0),
                    'arl'  => (int) ($cotizacion['arl']  ?? 0),
                    'afp'  => (int) ($cotizacion['pen']  ?? 0),
                    'caja' => (int) ($cotizacion['caja'] ?? 0),
                    'ss'   => (int) ($cotizacion['ss']   ?? 0),
                ];
            }

            $afiliacion  = ($esAfiliacion || $esIndActPrimerMes) ? (int) ($contrato->costo_afiliacion ?? 0) : 0;
            $seguro      = (int) ($contrato->seguro ?? 0);
            $admon       = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : (int) ($contrato->administracion ?? 0);
            $admonAsesor = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : (int) ($contrato->admon_asesor ?? 0);

            // IVA
            $iva = 0;
            if (!$esAfiliacion || $esIndActPrimerMes) {
                $clienteIva = DB::table('clientes')->where('cedula', $contrato->cedula)->value('iva');
                if (strtoupper(trim($clienteIva ?? '')) === 'SI') {
                    $cfgIva = ConfiguracionBrynex::porcentajeIva();
                    $iva    = (int) round(($admon + $admonAsesor) * $cfgIva / 100);
                }
            }

            $total = $calcSS['ss'] + $admon + $admonAsesor + $seguro + $afiliacion + $iva;
        }

        // Mora estimada — las afiliaciones nunca generan mora (no hay pago de planilla)
        $mora = 0;
        if (!$esAfiliacion) {
            try {
                $rs      = $contrato->razonSocial;
                $esIndep = $contrato->esIndependiente() || ($rs && $rs->es_independiente);
                $rsNit   = $esIndep ? (int) $contrato->cedula : ($rs ? (int) ($rs->nit ?: $rs->id) : 0);
                $rsDia   = $esIndep ? null : ($rs ? ($rs->dia_habil ?? null) : null);
                if ($rsNit && $calcSS['ss'] > 0) {
                    $moraInfo = MoraClienteService::calcular($contrato->aliado_id, $rsNit, $rsDia, $calcSS['ss'], $mes, $anio);
                    $mora = (int) ($moraInfo['mora'] ?? 0);
                }
            } catch (\Throwable) {
                // Mora es solo estimada; si falla, se omite sin bloquear el cálculo principal.
            }
        }

        return [
            'tipo'       => $esAfiliacion ? 'afiliacion' : 'planilla',
            'eps'        => $calcSS['eps'],
            'arl'        => $calcSS['arl'],
            'afp'        => $calcSS['afp'],
            'caja'       => $calcSS['caja'],
            'ss'         => $calcSS['ss'],
            'admon'      => $admon + $admonAsesor,
            'seguro'     => $seguro,
            'afiliacion' => $afiliacion,
            'iva'        => $iva,
            'mora'       => $mora,
            'total'      => $total + $mora,
            'dias'       => $diasCotizar,
        ];
    }

    /** @return bool Si el contrato ya tiene una factura (no anulada) para ese mes/año. */
    public static function yaFacturado(Contrato $contrato, int $mes, int $anio): bool
    {
        return Factura::where('aliado_id', $contrato->aliado_id)
            ->where('cedula', $contrato->cedula)
            ->where('razon_social_id', $contrato->razon_social_id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereNotIn('estado', ['anulada'])
            ->exists();
    }

    public static function calcularDias(Contrato $contrato, int $mes, int $anio): int
    {
        // ── GESTIÓN ARL (id=15): SIEMPRE días=0, nunca planilla ─────────────
        if ((int) $contrato->tipo_modalidad_id === 15) {
            return 0;
        }

        // Tiempo Parcial: se devuelve 30 porque ARL cotiza mensual completo.
        $mod = $contrato->tipoModalidad;
        if ($mod && $mod->esTiempoParcial()) {
            return 30;
        }

        if (!$contrato->fecha_ingreso) return 30;
        $fIng        = $contrato->fecha_ingreso;
        $mesIngreso  = (int) $fIng->month;
        $anioIngreso = (int) $fIng->year;

        // ① Mes de ingreso → afiliación, no hay días de planilla
        if ($mesIngreso === $mes && $anioIngreso === $anio) {
            return 0;
        }

        // ② Mes siguiente al ingreso → primera planilla: días activos del mes de ingreso
        $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior = $mes === 1 ? $anio - 1 : $anio;
        if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
            // EXCEPCIÓN 1: Independiente Activo (11) ya cobró su planilla en el mes de ingreso.
            if ((int) $contrato->tipo_modalidad_id === 11) {
                return 30;
            }
            // EXCEPCIÓN 2: Si ya existe una factura de tipo 'planilla' pagada para el mes de ingreso.
            $existePlanillaIngreso = Factura::where('aliado_id', $contrato->aliado_id)
                ->where('contrato_id', $contrato->id)
                ->where('mes', $mesIngreso)
                ->where('anio', $anioIngreso)
                ->where('tipo', 'planilla')
                ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
                ->exists();
            if ($existePlanillaIngreso) {
                return 30;
            }

            return max(1, 30 - $fIng->day + 1);
        }

        // ③ Mes normal
        return 30;
    }
}
