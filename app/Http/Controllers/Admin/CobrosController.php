<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Contrato, Factura, BitacoraCobro, ConfiguracionBrynex, ArlTarifa, Empresa, BancoCuenta};
use App\Services\MoraClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};


class CobrosController extends Controller
{
    // ─── Porcentajes SS base ─────────────────────────────────────────
    // Dependiente empresa: EPS 12,5% total → empleador paga 8,5%, trabajador 4%
    // Para cobros mostramos la CUOTA TOTAL del empleador (lo que le facturamos)
    // Independiente: cotiza sobre el 40% del IBC → porcentajes fijos BryNex
    // Usamos los valores configurados en ConfiguracionBrynex.

    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $mes      = (int) $request->get('mes',  now()->month);
        $anio     = (int) $request->get('anio', now()->year);

        // ── Pre-carga de configuraciones (1 query total en vez de N×6) ──────
        ConfiguracionBrynex::precargar();

        // ── Pre-carga de tarifas ARL (1 query total en vez de N×2) ──────────
        $arlTarifasRaw = DB::table('arl_tarifas')
            ->where(function ($q) use ($aliadoId) {
                $q->where('aliado_id', $aliadoId)->orWhereNull('aliado_id');
            })
            ->get()
            ->groupBy('nivel');

        // Resuelve pct ARL por nivel sin tocar BD
        $getArlPct = function (int $nivel) use ($arlTarifasRaw, $aliadoId): float {
            $grupo = $arlTarifasRaw->get($nivel);
            if (!$grupo) return 0.0;
            $porAliado = $grupo->firstWhere('aliado_id', $aliadoId);
            if ($porAliado) return (float) $porAliado->porcentaje;
            $global = $grupo->first(fn($t) => $t->aliado_id === null);
            return (float) ($global?->porcentaje ?? 0.0);
        };

        // ── Filtros opcionales ──────────────────────────────────────
        $rsId     = $request->get('razon_social_id');
        $asesorId = $request->get('asesor_id');
        $buscar   = $request->get('buscar');
        $soloInd  = $request->get('tipo', 'individual'); // individual | todos
        $soloPend = $request->get('estado', 'pendiente'); // pendiente | todos
        $sort     = $request->get('sort', 'nombre');
        $dir      = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $afilPlan = $request->get('afil_plan');

        // ── Contratos vigentes del aliado ───────────────────────────
        $q = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente.empresa', 'tipoModalidad', 'razonSocial', 'asesor', 'plan', 'eps', 'arl', 'pension', 'caja']);

        // Filtro: solo individuales (cod_empresa = 1 = Individual)
        if ($soloInd === 'individual') {
            // Subquery nativa: evita cargar cédulas a PHP y enviar un whereIn masivo
            $q->whereIn('cedula', function ($sub) use ($aliadoId) {
                $sub->from('clientes')
                    ->select('cedula')
                    ->where('aliado_id', $aliadoId)
                    ->where(function ($sq) {
                        $sq->where('cod_empresa', 1)
                           ->orWhereNull('cod_empresa');
                    });
            });
        }

        // Filtro: razón social
        if ($rsId) $q->where('razon_social_id', $rsId);

        // Filtro: asesor
        if ($asesorId) $q->where('asesor_id', $asesorId);

        // Filtro: búsqueda nombre/cédula
        if ($buscar) {
            $q->where(function ($sq) use ($buscar) {
                $sq->where('cedula', 'like', "%$buscar%")
                   ->orWhereHas('cliente', fn($cq) => $cq
                       ->where('primer_nombre',   'like', "%$buscar%")
                       ->orWhere('primer_apellido','like', "%$buscar%"));
            });
        }

        // Ordenamiento
        $sortMap = [
            'cedula'   => 'contratos.cedula',
            'ingreso'  => 'contratos.fecha_ingreso',
            'contrato' => 'contratos.id',
        ];
        if (isset($sortMap[$sort])) {
            $q->orderBy($sortMap[$sort], $dir);
        }

        $contratos = $q->get();

        // ── Facturas del mes para estos contratos ───────────────────
        // Indexamos por contrato_id (primario) para soportar múltiples contratos
        // vigentes del mismo cliente (caso Ingreso-Retiro con nuevo contrato).
        $contratoIds = $contratos->pluck('id')->toArray();
        $cedulas     = $contratos->pluck('cedula')->toArray();
        $facturasBruto = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $cedulas)
            ->whereNull('deleted_at')
            ->with('plano')
            ->get();

        // Índice principal: contrato_id → factura (1 factura por contrato)
        $facturasPorContrato = $facturasBruto
            ->filter(fn($f) => !empty($f->contrato_id))
            ->keyBy(fn($f) => (int) $f->contrato_id);

        // Índice secundario: cedula → factura (para facturas sin contrato_id)
        $facturasPorCedula = $facturasBruto
            ->filter(fn($f) => empty($f->contrato_id))
            ->keyBy(fn($f) => (string) $f->cedula);

        // ── Última llamada de cobro por contrato ────────────────────
        $contratoIds  = $contratos->pluck('id')->toArray();
        $ultimasLlamadas = BitacoraCobro::where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $contratoIds)
            ->orderByDesc('fecha_llamada')
            ->get()
            ->groupBy('contrato_id')
            ->map(fn($g) => $g->first()); // solo la más reciente por contrato

        // ── Préstamos pendientes — 1 query para badge ligero ───────────
        $cedulasConPrestamo = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->where('estado', 'prestamo')
            ->whereNull('deleted_at')
            ->pluck('cedula')
            ->flip(); // convierte a [cedula => index] para búsqueda O(1)

        // ── Pre-calcular SS por contrato (necesario antes del lote de mora) ──
        $r100 = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100);

        // ── Lookup de ARL por nit (para fallback desde Razón Social) ──────
        // RazonSocial guarda el nit de la ARL en arl_nit; el contrato puede
        // tener arl_id nulo si hereda la ARL de su RS.
        $arlNitsFallback = $contratos
            ->filter(fn($c) => !$c->arl_id && $c->razonSocial?->arl_nit)
            ->map(fn($c) => $c->razonSocial->arl_nit)
            ->unique()->values()->toArray();
        $arlPorNit = \App\Models\Arl::whereIn('nit', $arlNitsFallback)
            ->get()->keyBy('nit');

        // Primera pasada: calcular vSS y flags por contrato sin tocar BD
        $vSsPorContrato = [];
        $flagsPorContrato = []; // [esAfil, esIndActPrimerMes, totalEstimado, ...]

        foreach ($contratos as $c) {
            $esAfil = false;
            $esIndActPrimerMes = false;
            if ($c->fecha_ingreso) {
                $fIng     = $c->fecha_ingreso;
                $esIndAct = (int)($c->tipo_modalidad_id) === 11;
                if ((int)$fIng->month === $mes && (int)$fIng->year === $anio) {
                    $esIndActPrimerMes = $esIndAct;
                    $esAfil            = !$esIndAct;
                }
            }

            $esIndep = $c->tipoModalidad?->esIndependiente() ?? false;
            $ibc     = (float)($c->salario ?? 0);
            $plan    = $c->plan;

            if ($esIndActPrimerMes) {
                $diasAct = max(1, 30 - (int)$c->fecha_ingreso->day + 1);
                $pctEps = ConfiguracionBrynex::pctSaludIndependiente();
                $pctPen = ConfiguracionBrynex::pctPensionIndependiente();
                $pctCaj = (float)($c->porcentaje_caja ?? ConfiguracionBrynex::pctCajaIndependienteAlto());
                $pctArl = $getArlPct((int)($c->n_arl ?? 1));
                $vEps  = ($plan?->incluye_eps)    ? $r100($ibc * $pctEps / 100 * $diasAct / 30) : 0;
                $vArl  = ($plan?->incluye_arl)    ? $r100($ibc * $pctArl / 100 * $diasAct / 30) : 0;
                $vPen  = ($plan?->incluye_pension) ? $r100($ibc * $pctPen / 100 * $diasAct / 30) : 0;
                $vCaja = ($plan?->incluye_caja)   ? $r100($ibc * $pctCaj / 100 * $diasAct / 30) : 0;
                $vSS   = $vEps + $vArl + $vPen + $vCaja;
                $totalEstimado = $vSS + (int)($c->administracion ?? 0) + (int)($c->seguro ?? 0) + (int)($c->costo_afiliacion ?? 0);
            } elseif ($esAfil) {
                $vEps = $vArl = $vPen = $vCaja = $vSS = 0;
                $totalEstimado = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
            } else {
                if ($esIndep) {
                    $pctEps = ConfiguracionBrynex::pctSaludIndependiente();
                    $pctPen = ConfiguracionBrynex::pctPensionIndependiente();
                    $pctCaj = (float)($c->porcentaje_caja ?? ConfiguracionBrynex::pctCajaIndependienteAlto());
                } else {
                    $pctEps = ConfiguracionBrynex::pctSaludDependiente();
                    $pctPen = ConfiguracionBrynex::pctPensionDependiente();
                    $pctCaj = ConfiguracionBrynex::pctCajaDependiente();
                }
                $pctArl = $getArlPct((int)($c->n_arl ?? 1));
                $vEps  = ($plan?->incluye_eps)    ? $r100($ibc * $pctEps / 100) : 0;
                $vArl  = ($plan?->incluye_arl)    ? $r100($ibc * $pctArl / 100) : 0;
                $vPen  = ($plan?->incluye_pension) ? $r100($ibc * $pctPen / 100) : 0;
                $vCaja = ($plan?->incluye_caja)   ? $r100($ibc * $pctCaj / 100) : 0;
                if ($vCaja === 0 && $c->aplicaCargoSinCcf()) {
                    $vCaja = \App\Models\Contrato::CARGO_SIN_CCF;
                }
                $vSS   = $vEps + $vArl + $vPen + $vCaja;
                $totalEstimado = $vSS + (int)($c->administracion ?? 0) + (int)($c->seguro ?? 0);
            }

            $vSsPorContrato[$c->id]   = $vSS;
            $flagsPorContrato[$c->id] = compact(
                'esAfil', 'esIndActPrimerMes', 'esIndep',
                'vEps', 'vArl', 'vPen', 'vCaja', 'vSS', 'totalEstimado'
            );
        }

        // ── Calcular mora en lote — 1 query BD para toda la lista ───────
        // Agrupa por RS para reutilizar fecha_vence: si hay dia_habil_global
        // todos comparten la misma fecha → O(1) en vez de O(N) queries.
        $moraLoteInput = [];
        foreach ($contratos as $c) {
            $rsObj  = $c->razonSocial;
            $rsNit  = $rsObj ? (int)($rsObj->nit ?: $rsObj->id) : 0;
            $rsDiaH = $rsObj ? ($rsObj->dia_habil ?? null) : null;
            $vSS    = $vSsPorContrato[$c->id] ?? 0;
            $moraLoteInput[] = [
                '_contrato_id' => $c->id,
                'rs_nit'       => $rsNit,
                'rs_dia_habil' => $rsDiaH,
                'total_ss'     => $vSS,
                'mes'          => $mes,
                'anio'         => $anio,
            ];
        }

        $moraLoteOutput = MoraClienteService::calcularLote($aliadoId, $moraLoteInput);

        // Indexar resultado de mora por contrato_id para O(1) en el map()
        $moraPorContrato = [];
        $morasDiasPorContrato = [];
        foreach ($moraLoteOutput as $fila) {
            $moraPorContrato[$fila['_contrato_id']]     = (int)($fila['mora']      ?? 0);
            $morasDiasPorContrato[$fila['_contrato_id']] = (int)($fila['dias_mora'] ?? 0);
        }

        // ── Segunda pasada: enriquecer modelos con datos calculados ─────
        $contratos = $contratos->map(function ($c) use (
            $mes, $anio, $facturasPorContrato, $facturasPorCedula, $ultimasLlamadas, $flagsPorContrato,
            $moraPorContrato, $cedulasConPrestamo, $morasDiasPorContrato, $arlPorNit
        ) {
            // Buscar factura: primero por contrato_id, fallback por cédula
            $fact = $facturasPorContrato->get((int) $c->id)
                 ?? $facturasPorCedula->get((string) $c->cedula);
            $flags  = $flagsPorContrato[$c->id];

            $esAfil            = $flags['esAfil'];
            $esIndActPrimerMes = $flags['esIndActPrimerMes'];
            $vEps              = $flags['vEps'];
            $vArl              = $flags['vArl'];
            $vPen              = $flags['vPen'];
            $vCaja             = $flags['vCaja'];
            $vSS               = $flags['vSS'];
            $totalEstimado     = $flags['totalEstimado'];

            // ── Datos de la factura (solo estado y número) ───────
            // 'factura emitida' = cualquier factura que no sea pre_factura ni esté anulada.
            // Si existe una factura con estado pagada, abono o prestamo → ya fue facturada.
            // prestamo = ya se facturó en modalidad préstamo → NO debe aparecer en cobros.
            $facturaEmitida  = $fact && !in_array($fact->estado, ['pre_factura', 'anulada']);
            $facturaPagada   = $fact && in_array($fact->estado, ['pagada', 'abono', 'prestamo']);
            $facturaEstado   = $fact?->estado;
            $facturaNumero   = $fact?->numero_factura;
            $facturaId       = $fact?->id;
            $facturaNPlano   = $fact?->plano?->numero_planilla ?? $fact?->n_plano;
            $facturaSaldoPend= 0; // saldo_pendiente eliminado — derivar de saldo_proximo si se necesita

            // ── Semáforo ─────────────────────────────────────────
            $ultimaLlamada = $ultimasLlamadas->get($c->id);
            $diasSinLlamar = $ultimaLlamada
                ? (int)$ultimaLlamada->fecha_llamada->diffInDays(now())
                : null;

            $semaforo = match(true) {
                $diasSinLlamar === null => 'gris',   // nunca llamado
                $diasSinLlamar < 3      => 'verde',
                $diasSinLlamar <= 7     => 'amarillo',
                default                 => 'rojo',
            };

            // ── Mora estimada (ya calculada en lote, O(1) aquí) ──────────
            $moraEst = $moraPorContrato[$c->id] ?? 0;

            $c->es_afil             = $esAfil;
            $c->es_ind_act_primer_mes = $esIndActPrimerMes; // I ACT: afiliación + planilla
            $c->v_eps            = $vEps;
            $c->v_arl            = $vArl;
            $c->v_pen            = $vPen;
            $c->v_caja           = $vCaja;
            $c->v_ss             = $vSS ?? 0;
            $c->total_estimado   = $totalEstimado;
            $c->mora_estimada    = $moraEst;
            $c->mora_dias        = $morasDiasPorContrato[$c->id] ?? 0;
            $c->fact_emitida     = $facturaEmitida;
            $c->fact_pagada      = $facturaPagada;
            $c->fact_estado      = $facturaEstado;
            $c->fact_numero      = $facturaNumero;
            $c->fact_id          = $facturaId;
            $c->fact_n_plano     = $facturaNPlano;
            $c->fact_saldo_pend  = $facturaSaldoPend;
            // ── Entidades para cuenta de cobro individual ─────────────
            // ARL: campo real es 'nombre_arl'.
            // Si el contrato tiene arl_id propio → usar esa relación.
            // Si no, caer al lookup por arl_nit de la Razón Social.
            $arlObj = $c->arl
                ?? ($c->razonSocial?->arl_nit ? ($arlPorNit->get($c->razonSocial->arl_nit) ?? null) : null);
            $c->eps_nombre      = $c->eps?->nombre         ?? 'Ninguna';
            $c->arl_nombre      = $arlObj?->nombre_arl     ?? $arlObj?->razon_social ?? 'Ninguna';
            // AFP/Pension: usar razon_social (limpiando prefijo "25-14_" y NIT final " - 900xxx")
            $pensionRaw = $c->pension?->razon_social ?? null;
            if ($pensionRaw) {
                $pensionRaw = preg_replace('/^\d+[-\d]*_/u', '', $pensionRaw);      // quita "25-14_"
                $pensionRaw = preg_replace('/\s*-\s*\d{6,}\s*$/u', '', $pensionRaw); // quita " - NIT"
                $pensionRaw = trim($pensionRaw);
            }
            $c->afp_nombre = $pensionRaw ?: 'Ninguna';
            $c->caja_nombre     = $c->caja?->nombre        ?? 'Ninguna';
            $c->plan_nombre     = $c->plan?->nombre        ?? 'Estándar';
            $c->tipo_mod_nombre = $c->tipoModalidad?->nombre ?? '—';
            $c->dias_cotizados  = $esAfil ? 0 : ($esIndActPrimerMes
                ? max(1, 30 - (int)($c->fecha_ingreso?->day ?? 1) + 1)
                : ($c->fecha_ingreso && (int)$c->fecha_ingreso->month === ($mes === 1 ? 12 : $mes - 1) && (int)$c->fecha_ingreso->year === ($mes === 1 ? $anio - 1 : $anio)
                    ? max(1, 30 - (int)$c->fecha_ingreso->day + 1)
                    : 30));
            $c->ultima_llamada   = $ultimaLlamada;
            $c->dias_sin_llamar  = $diasSinLlamar;
            $c->semaforo         = $semaforo;
            // Badge ligero: solo true/false usando el Set pre-cargado (O(1))
            $c->tiene_prestamo   = isset($cedulasConPrestamo[$c->cedula]);
            // Empresa vinculada: cod_empresa > 1 y existe en tabla empresas
            $empresa = $c->cliente?->empresa;
            $c->es_empresa    = $empresa && $empresa->id != 1;
            $c->nombre_empresa = $c->es_empresa ? $empresa->empresa : null;

            // ── Detección Plan Ingreso-Retiro: planilla > 5 días ──────────
            // Si tipo_modalidad=12 y no es mes de afiliación y diasCotizar>5
            // → el sistema debe advertir que hay que rotar Razón Social.
            $esIngresoRetiro  = (int)($c->tipo_modalidad_id) === 12;
            $diasCotizEstim   = 30; // default: mes completo
            if ($esIngresoRetiro && !$esAfil && !$esIndActPrimerMes && $c->fecha_ingreso) {
                $fIng2        = $c->fecha_ingreso;
                $mesAnt       = $mes === 1 ? 12 : $mes - 1;
                $anioAnt      = $mes === 1 ? $anio - 1 : $anio;
                // Si ingresó el mes anterior → días = activos de ese mes
                if ((int)$fIng2->month === $mesAnt && (int)$fIng2->year === $anioAnt) {
                    $diasCotizEstim = max(1, 30 - $fIng2->day + 1);
                }
                // Si ingresó antes del mes anterior → 30 días (mes completo)
            }
            $c->es_ir_alerta     = $esIngresoRetiro && !$esAfil && !$esIndActPrimerMes && ($diasCotizEstim > 5);
            $c->dias_cotiz_estim = $diasCotizEstim;

            // ── Cuando debe rotar RS: el cobro es AFILIACIÓN del nuevo contrato ──
            // No se factura la planilla larga → se muestra costo_afiliacion en su lugar.
            if ($c->es_ir_alerta) {
                $c->total_estimado = (int)($c->costo_afiliacion ?? 0);
                $c->v_ss           = 0;
                $c->mora_estimada  = 0;
                $c->es_afil        = true; // mostrar badge AFIL en la columna
            }

            return $c;
        });

        // ── Ordenamiento en colección (campos calculados en PHP) ────
        if ($sort === 'n_planilla') {
            $contratos = $dir === 'asc'
                ? $contratos->sortBy('fact_n_plano', SORT_NATURAL)
                : $contratos->sortByDesc('fact_n_plano', SORT_NATURAL);
            $contratos = $contratos->values();
        }

        // ── Extraer opciones únicas de Empresa/Cliente para el filtro ──
        $opcionesEmpresaCliente = $contratos->map(function ($c) {
            return $c->es_empresa ? $c->nombre_empresa : 'Individual';
        })->unique()->filter()->values()->toArray();

        // ── Filtro estado de pago ───────────────────────────────────
        if ($soloPend === 'pendiente') {
            $contratos = $contratos->filter(function ($c) {
                // Pendiente = sin factura emitida, o con factura en borrador (pre_factura)
                // o con pago parcial (abono).
                // EXCLUIR: 'prestamo', 'pagada' → ya fueron facturados en este período.
                if ($c->fact_emitida) {
                    // Solo re-incluir si está en pre_factura (borrador) o abono (pago parcial)
                    return in_array($c->fact_estado, ['pre_factura', 'abono']);
                }
                return true; // sin factura → pendiente
            })->values();
        }

        // ── Filtro AFIL/PLAN ────────────────────────────────────────
        if ($afilPlan && $afilPlan !== 'todos') {
            $contratos = $contratos->filter(function ($c) use ($afilPlan) {
                if ($afilPlan === 'afil') {
                    return (bool)($c->es_afil ?? false);
                } elseif ($afilPlan === 'plan') {
                    return !($c->es_afil ?? false);
                }
                return true;
            })->values();
        }

        // ── Filtro Empresa/Cliente ──────────────────────────────────
        $empresaCliente = $request->get('empresa_cliente');
        if ($empresaCliente && $empresaCliente !== 'todos') {
            $contratos = $contratos->filter(function ($c) use ($empresaCliente) {
                if ($empresaCliente === 'Individual') {
                    return !$c->es_empresa;
                } else {
                    return $c->es_empresa && $c->nombre_empresa === $empresaCliente;
                }
            })->values();
        }

        // ── Cards de resumen ────────────────────────────────────────
        $totalAdmon    = $contratos->sum(fn($c) => (int)($c->administracion ?? 0));
        $totalPendientes = $contratos->count();
        $sinLlamar     = $contratos->where('semaforo', 'gris')->count()
                       + $contratos->where('semaforo', 'rojo')->count();
        $prometieronPago = $contratos
            ->filter(fn($c) => $c->ultima_llamada?->resultado === 'promesa_pago')
            ->count();
        $totalSS         = $contratos->sum('v_ss');

        // ── Datos para filtros ──────────────────────────────────────
        $razonesDisponibles = DB::table('razones_sociales')
            ->where('aliado_id', $aliadoId)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $asesoresDisponibles = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $bancos       = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->orderBy('nombre')->get();
        $cuentasCobro = BancoCuenta::paraCobro($aliadoId);

        return view('admin.cobros.index', compact(
            'contratos', 'mes', 'anio',
            'totalAdmon', 'totalPendientes', 'sinLlamar', 'prometieronPago', 'totalSS',
            'razonesDisponibles', 'asesoresDisponibles',
            'rsId', 'asesorId', 'buscar', 'soloInd', 'soloPend', 'sort', 'dir',
            'bancos', 'cuentasCobro', 'afilPlan', 'empresaCliente', 'opcionesEmpresaCliente'
        ));
    }

    // ─── Registrar llamada ───────────────────────────────────────────
    public function registrarLlamada(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'resultado'    => 'required|in:no_contesta,promesa_pago,pagado,numero_errado,otro',
            'observacion'  => 'nullable|string|max:1000',
            'factura_id'   => 'nullable|integer',
        ]);

        // Verificar que el contrato pertenece al aliado
        $contrato = Contrato::where('aliado_id', $aliadoId)->findOrFail($contratoId);

        $llamada = BitacoraCobro::create([
            'aliado_id'    => $aliadoId,
            'contrato_id'  => $contratoId,
            'factura_id'   => $validated['factura_id'] ?? null,
            'usuario_id'   => Auth::id(),
            'fecha_llamada'=> now(),
            'resultado'    => $validated['resultado'],
            'observacion'  => $validated['observacion'] ?? null,
        ]);

        return response()->json([
            'ok'           => true,
            'llamada_id'   => $llamada->id,
            'resultado'    => $llamada->resultado,
            'etiqueta'     => $llamada->etiqueta_resultado,
            'fecha'        => $llamada->fecha_llamada->format('d/m/Y H:i'),
            'usuario'      => Auth::user()->nombre ?? Auth::user()->name,
            'semaforo'     => 'verde', // acaba de llamar
            'dias'         => 0,
        ]);
    }

    // ─── Historial de llamadas ───────────────────────────────────────
    public function historialLlamadas(int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');

        // Verificar que el contrato pertenece al aliado
        Contrato::where('aliado_id', $aliadoId)->findOrFail($contratoId);

        $llamadas = BitacoraCobro::where('contrato_id', $contratoId)
            ->where('aliado_id', $aliadoId)
            ->with('usuario')
            ->orderByDesc('fecha_llamada')
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'fecha'       => $l->fecha_llamada->format('d/m/Y H:i'),
                'resultado'   => $l->resultado,
                'etiqueta'    => $l->etiqueta_resultado,
                'observacion' => $l->observacion,
                'usuario'     => $l->usuario?->nombre ?? $l->usuario?->name ?? '—',
                'dias'        => $l->dias,
            ]);

        return response()->json(['ok' => true, 'llamadas' => $llamadas]);
    }

    // ─── Vista EMPRESAS ──────────────────────────────────────────────
    public function empresas(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $user     = Auth::user();
        $mes      = (int) $request->get('mes',  now()->month);
        $anio     = (int) $request->get('anio', now()->year);
        $buscar         = $request->get('buscar');
        $sort           = $request->get('sort', 'empresa');
        $dir            = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $semaforoFiltro = $request->get('semaforo');
        $soloPlant      = $request->get('solo_plant');
        $soloPend       = $request->get('solo_pend');

        // ── Empresas: descubrir vía cod_empresa de los clientes del aliado ──
        // Más robusto que filtrar por aliado_id en empresas (puede estar mal tras migraciones)
        $empresaIdsClientes = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', '>', 1)
            ->whereNotNull('cod_empresa')
            ->pluck('cod_empresa')
            ->unique()
            ->toArray();

        $q = Empresa::whereIn('id', $empresaIdsClientes)
            ->where('id', '!=', 1);

        $esAdmin = $user->hasRole('admin') || $user->hasRole('superadmin') || $user->hasRole('usuario');
        if (!$esAdmin) {
            $q->where('encargado_id', $user->id);
        }

        if ($buscar) {
            $q->where('empresa', 'like', "%$buscar%");
        }

        $encargadoFiltro = $request->get('encargado_id');
        if ($encargadoFiltro && $esAdmin) {
            $q->where('encargado_id', $encargadoFiltro);
        }

        if (in_array($sort, ['empresa', 'contacto'])) {
            $q->orderBy($sort, $dir);
        }

        $empresas   = $q->get();
        $empresaIds = $empresas->pluck('id')->toArray();

        // ── Para cada empresa: obtener cédulas de sus clientes ──────
        $finMes = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth();

        // Subquery de cédulas por empresa (evita el límite 2100 params de SQL Server)
        $cedulasSubquery = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->whereIn('cod_empresa', $empresaIds)
            ->select('cedula', 'cod_empresa');

        // clientesPorEmpresa: agrupado en PHP (solo IDs de empresa, pocos)
        $clientesPorEmpresa = (clone $cedulasSubquery)->get()->groupBy('cod_empresa');

        // Contratos vigentes/activos usando subquery nativo
        $contratosActivos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('cedula', function ($sub) use ($aliadoId, $empresaIds) {
                $sub->from('clientes')
                    ->select('cedula')
                    ->where('aliado_id', $aliadoId)
                    ->whereIn('cod_empresa', $empresaIds);
            })
            ->whereIn('estado', ['vigente', 'activo'])  // igual que index()
            ->with('tipoModalidad')
            ->get();

        // Cédulas reales de contratos activos (ya filtradas, número manejable por empresa)
        $cedulasActivas = $contratosActivos->pluck('cedula')->unique()->values()->toArray();

        // Facturas del mes — subquery si hay muchas cédulas, array si son pocas
        $factQuery = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereNull('deleted_at');

        if (count($cedulasActivas) > 500) {
            $factQuery->whereIn('cedula', function ($sub) use ($aliadoId, $empresaIds) {
                $sub->from('clientes')->select('cedula')
                    ->where('aliado_id', $aliadoId)
                    ->whereIn('cod_empresa', $empresaIds);
            });
        } else {
            $factQuery->whereIn('cedula', $cedulasActivas);
        }

        $facturasMes = $factQuery->get()->keyBy(fn($f) => (string) $f->cedula);  // cast string: evita mismatch SQL Server

        // Última llamada por empresa (agrupado por empresa_id)
        $ultimasLlamadasEmp = BitacoraCobro::where('aliado_id', $aliadoId)
            ->whereIn('empresa_id', $empresaIds)
            ->whereNotNull('empresa_id')
            ->orderByDesc('fecha_llamada')
            ->get()
            ->groupBy('empresa_id')
            ->map(fn($g) => $g->first());

        // ── Mora en lote para contratos de empresas (1 query BD) ────────
        // Pre-calcular SS estimado + mora para todos los contratos de empresa
        // antes del map(), evitando N×2 queries dentro del foreach por empresa.
        $cedulasPagadasEmp = $facturasMes
            ->filter(fn($f) => in_array($f->estado, ['pagada', 'abono', 'prestamo']))
            ->keys()
            ->flip()
            ->all(); // Set O(1) de cédulas pagadas

        $moraEmpLoteInput = [];
        foreach ($contratosActivos as $c) {
            if (isset($cedulasPagadasEmp[(string)$c->cedula])) continue; // ya pagó
            $rsObj   = $c->razonSocial;
            $rsNit   = $rsObj ? (int)($rsObj->nit ?: $rsObj->id) : 0;
            $rsDiaH  = $rsObj ? ($rsObj->dia_habil ?? null) : null;
            $vSsCont = (float)($c->salario ?? 0) * 0.285; // estimación ~28.5%
            if ($rsNit && $vSsCont > 0) {
                $moraEmpLoteInput[] = [
                    '_contrato_id' => $c->id,
                    'rs_nit'       => $rsNit,
                    'rs_dia_habil' => $rsDiaH,
                    'total_ss'     => $vSsCont,
                    'mes'          => $mes,
                    'anio'         => $anio,
                ];
            }
        }

        $moraEmpLoteOutput = MoraClienteService::calcularLote($aliadoId, $moraEmpLoteInput);
        $moraEmpPorContrato = [];
        foreach ($moraEmpLoteOutput as $fila) {
            $moraEmpPorContrato[$fila['_contrato_id']] = (int)($fila['mora'] ?? 0);
        }

        // ── Procesar cada empresa ────────────────────────────────────
        $empresas = $empresas->map(function ($emp) use (
            $mes, $anio, $clientesPorEmpresa, $contratosActivos,
            $facturasMes, $ultimasLlamadasEmp, $cedulasPagadasEmp, $moraEmpPorContrato
        ) {
            $cedulas  = $clientesPorEmpresa->get($emp->id)?->pluck('cedula')->toArray() ?? [];
            $contrEmp = $contratosActivos->whereIn('cedula', $cedulas);

            $cant = $contrEmp->count();

            // Calcular AFIL/PLAN para cada contrato de esta empresa
            $pagados = 0; $afil_pend = 0; $indep_pend = 0; $plan_pend = 0; $admon_pend = 0;

            foreach ($contrEmp as $c) {
                $fact = $facturasMes->get((string) $c->cedula);  // cast string: evita mismatch SQL Server
                $pagada = $fact && in_array($fact->estado, ['pagada', 'abono', 'prestamo']);

                // ¿Es afiliación?
                $esAfil = false;
                if ($c->fecha_ingreso) {
                    $fIng    = $c->fecha_ingreso;
                    $esIndep = $c->tipoModalidad?->esIndependiente() ?? false;
                    if ((int)$fIng->month === $mes && (int)$fIng->year === $anio) {
                        if (!$esIndep || !($c->cobrar_planilla_primer_mes ?? false)) {
                            $esAfil = true;
                        }
                    }
                }

                $esIndep = $c->tipoModalidad?->esIndependiente() ?? false;

                if ($pagada) {
                    $pagados++;
                } elseif ($esAfil) {
                    $afil_pend++;
                    $admon_pend += (int)($c->administracion ?? 0);
                } elseif ($esIndep) {
                    $indep_pend++;
                    $admon_pend += (int)($c->administracion ?? 0);
                } else {
                    $plan_pend++;
                    $admon_pend += (int)($c->administracion ?? 0);
                }
            }

            $totalPend = $afil_pend + $indep_pend + $plan_pend;

            // Semáforo por empresa
            $ultimaLlamada = $ultimasLlamadasEmp->get($emp->id);
            $diasSinLlamar = $ultimaLlamada
                ? (int)$ultimaLlamada->fecha_llamada->diffInDays(now())
                : null;

            $semaforo = match(true) {
                $diasSinLlamar === null => 'gris',
                $diasSinLlamar < 3      => 'verde',
                $diasSinLlamar <= 7     => 'amarillo',
                default                 => 'rojo',
            };

            // ── Mora estimada empresa — suma desde el lote pre-calculado (O(1) por contrato)
            $moraEmpEst = 0;
            foreach ($contrEmp->all() as $cMora) {
                if (!isset($cedulasPagadasEmp[(string)$cMora->cedula])) {
                    $moraEmpEst += $moraEmpPorContrato[$cMora->id] ?? 0;
                }
            }

            $emp->cant          = $cant;
            $emp->pagados       = $pagados;
            $emp->afil_pend     = $afil_pend;
            $emp->indep_pend    = $indep_pend;
            $emp->plan_pend     = $plan_pend;
            $emp->total_pend    = $totalPend;
            $emp->admon_pend    = $admon_pend;
            $emp->mora_estimada = $moraEmpEst;
            $emp->ultima_llamada = $ultimaLlamada;
            $emp->dias_sin_llamar = $diasSinLlamar;
            $emp->semaforo      = $semaforo;

            return $emp;
        })->filter(fn($emp) => $emp->cant > 0)
          ->when($semaforoFiltro, fn($col) => $col->where('semaforo', $semaforoFiltro))
          ->when($soloPlant,      fn($col) => $col->where('plan_pend', '>', 0))
          ->when($soloPend,       fn($col) => $col->where('total_pend', '>', 0))
          ->values();

        // ── Usuarios para selector de encargado ─────────────────────
        $usuariosDisponibles = \App\Models\User::where('aliado_id', $aliadoId)
            ->orWhere(function ($q) use ($aliadoId) {
                $q->where('es_brynex', false)->whereHas('aliados', fn($s) => $s->where('aliados.id', $aliadoId));
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre']);


        // Cards resumen
        $totalEmpresas   = $empresas->count();
        $totalContratos  = $empresas->sum('cant');
        $totalPagados    = $empresas->sum('pagados');
        $totalPendientes = $empresas->sum('total_pend');

        return view('admin.cobros.empresas', compact(
            'empresas', 'mes', 'anio', 'buscar', 'sort', 'dir',
            'totalEmpresas', 'totalContratos', 'totalPagados', 'totalPendientes',
            'usuariosDisponibles', 'encargadoFiltro', 'esAdmin', 'semaforoFiltro'
        ));
    }

    // ─── Registrar llamada a empresa ────────────────────────────────
    public function registrarLlamadaEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'resultado'   => 'required|in:no_contesta,promesa_pago,pagado,numero_errado,otro',
            'observacion' => 'nullable|string|max:1000',
        ]);

        Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $llamada = BitacoraCobro::create([
            'aliado_id'      => $aliadoId,
            'contrato_id'    => 0, // no aplica para empresa
            'empresa_id'     => $empresaId,
            'usuario_id'     => Auth::id(),
            'fecha_llamada'  => now(),
            'resultado'      => $validated['resultado'],
            'observacion'    => $validated['observacion'] ?? null,
        ]);

        return response()->json([
            'ok'       => true,
            'llamada_id' => $llamada->id,
            'etiqueta' => $llamada->etiqueta_resultado,
            'fecha'    => $llamada->fecha_llamada->format('d/m/Y H:i'),
            'usuario'  => Auth::user()->nombre ?? Auth::user()->name,
            'semaforo' => 'verde',
        ]);
    }

    // ─── Historial de llamadas a empresa ────────────────────────────
    public function historialEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $llamadas = BitacoraCobro::where('razon_social_id', $empresaId)
            ->where('aliado_id', $aliadoId)
            ->with('usuario')
            ->orderByDesc('fecha_llamada')
            ->get()
            ->map(fn($l) => [
                'fecha'      => $l->fecha_llamada->format('d/m/Y H:i'),
                'resultado'  => $l->resultado,
                'etiqueta'   => $l->etiqueta_resultado,
                'observacion'=> $l->observacion,
                'usuario'    => $l->usuario?->nombre ?? $l->usuario?->name ?? '—',
            ]);

        return response()->json(['ok' => true, 'llamadas' => $llamadas]);
    }

    // ─── Asignar encargado a empresa ────────────────────────────────
    public function asignarEncargado(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $validated = $request->validate([
            'encargado_id' => 'nullable|integer',
        ]);

        $empresa->update(['encargado_id' => $validated['encargado_id'] ?: null]);

        return response()->json(['ok' => true]);
    }
}

