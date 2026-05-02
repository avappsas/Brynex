<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Factura, Abono, Plano, Contrato, Empresa, RazonSocial, User, BancoCuenta};
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class FacturacionController extends Controller
{
    // ─── Listado de empresas ─────────────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // Subquery corroborada como objeto Eloquent/Builder válido para selectSub
        $subContratos = DB::table('contratos as c')
            ->join('clientes as cl', 'cl.cedula', '=', 'c.cedula')
            ->where('c.aliado_id', $aliadoId)
            ->whereIn('c.estado', ['vigente', 'activo'])
            ->whereColumn('cl.cod_empresa', 'empresas.id')
            ->selectRaw('COUNT(DISTINCT c.id)');

        $empresas = Empresa::where('aliado_id', $aliadoId)
            ->where('id', '>', 1)
            ->select(['id','empresa','nit','contacto','telefono','celular','iva'])
            ->selectSub($subContratos, 'contratos_activos_count')
            ->get()
            ->sortBy([
                // Las que tienen contratos activos van primero
                fn ($a, $b) => ($b->contratos_activos_count > 0) <=> ($a->contratos_activos_count > 0),
                // Dentro de cada grupo, A-Z
                fn ($a, $b) => strcmp($a->empresa, $b->empresa),
            ])
            ->values();

        return view('admin.facturacion.index', compact('empresas'));
    }

    // ─── Vista empresa (tabla de trabajadores) ───────────────────────
    public function empresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        // Traer todos los contratos vigentes cuyos clientes pertenecen a esta empresa
        // La relación es: contratos.cedula → clientes.cedula → clientes.cod_empresa
        $cedulasEmpresa = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', $empresaId)
            ->pluck('cedula');

        // Retirados visibles en este período:
        //  - fecha_retiro en el mes ANTERIOR (retiro se reporta en planilla del mes siguiente)
        //  - fecha_retiro en el mes ACTUAL   (retiro en el mismo mes que se consulta)
        $mesAnterior  = $mes  === 1 ? 12 : $mes  - 1;
        $anioAnterior = $mes  === 1 ? $anio - 1 : $anio;

        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulasEmpresa)
            ->where(function ($q) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                $q->whereIn('estado', ['vigente', 'activo'])
                  ->orWhere(function ($q2) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                      $q2->where('estado', 'retirado')
                         ->where(function ($q3) use ($mes, $anio, $mesAnterior, $anioAnterior) {
                             // Retirado en el mes actual
                             $q3->where(function ($qa) use ($mes, $anio) {
                                     $qa->whereMonth('fecha_retiro', $mes)
                                        ->whereYear('fecha_retiro', $anio);
                                 })
                                // O retirado el mes anterior
                                ->orWhere(function ($qb) use ($mesAnterior, $anioAnterior) {
                                     $qb->whereMonth('fecha_retiro', $mesAnterior)
                                        ->whereYear('fecha_retiro', $anioAnterior);
                                 });
                         });
                  });
            })
            ->with(['cliente', 'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja', 'asesor'])
            ->orderBy('cedula')
            ->get();

        // Facturas ya generadas para este periodo (solo planilla/afiliacion, no otro_ingreso)
        // ⚠️ Se indexa por CONTRATO_ID (no por cédula) para evitar cruzar pagos
        //    entre contratos distintos del mismo trabajador (ej: BRYGAR vs Independiente)
        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->whereNotNull('contrato_id')
            ->get()
            ->keyBy('contrato_id');  // ← clave: por contrato, no por cédula

        // Calcular días cotizados para cada contrato según fecha_ingreso
        $hoy       = now();
        // Mes/año actuales reales (hoy)
        $mesHoy  = (int) $hoy->month;
        $anioHoy = (int) $hoy->year;

        $contratos = $contratos->map(function ($c) use ($mes, $anio, $hoy, $facturasExistentes, $aliadoId) {
            $diasCotizar = 30;
            $esIndActPrimerMes = false; // I Act (id=11) en su mes de ingreso → afiliación + planilla juntas
            if ($c->fecha_ingreso) {
                $fIng = $c->fecha_ingreso;
                $mesIngreso  = (int)$fIng->month;
                $anioIngreso = (int)$fIng->year;
                // I Act = tipo_modalidad_id 11 (cobra afiliación + planilla el mismo mes)
                // I Venc = tipo_modalidad_id 10 (solo afiliación el primer mes)
                $esIndAct = (int)($c->tipo_modalidad_id) === 11;

                if ($mesIngreso === $mes && $anioIngreso === $anio) {
                    if ($esIndAct) {
                        // I Act: primer mes cobra afiliación + planilla juntas
                        // → días = días activos del mes de ingreso
                        $esIndActPrimerMes = true;
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    } else {
                        // I Venc, empresa, dependiente: solo afiliación, días = 0
                        $diasCotizar = 0;
                    }
                } else {
                    // Calcular el mes anterior al período actual
                    $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
                    $anioAnterior = $mes === 1 ? $anio - 1 : $anio;

                    if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
                        // Primera planilla: cubrir los días activos del mes de ingreso
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    }
                    // else: mes normal → 30 días
                }
            }
            $c->dias_cotizar          = $diasCotizar;
            $c->es_ind_act_primer_mes = $esIndActPrimerMes; // flag para la vista y cobros
            $c->factura_exist = $facturasExistentes->get($c->id);

            // 1a) Saldo para FACTURAR: suma hasta antes del período visualizado
            //     (se usa en el modal de facturación para aplicar al nuevo cobro)
            $saldoPrevFac = Factura::saldoClienteMesPrevio($aliadoId, $c->cedula, $mes, $anio, $c->id);
            $c->saldo_a_favor_facturar   = (int)($saldoPrevFac['a_favor']   ?? 0);
            $c->saldo_pendiente_facturar = (int)($saldoPrevFac['pendiente'] ?? 0);

            // 1b) Saldo REAL (para mostrar en pantalla): suma TODOS los saldo_proximo
            //     sin límite de fecha — incluye meses futuros ya registrados en BD.
            //     Si mayo ya consumió el saldo de abril, aquí aparecerá en 0.
            $sumaTotalSaldos = (int) Factura::where('aliado_id', $aliadoId)
                ->where('cedula', $c->cedula)
                ->where('contrato_id', $c->id)
                ->whereNotNull('saldo_proximo')
                ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
                ->sum('saldo_proximo');
            $c->saldo_a_favor   = $sumaTotalSaldos > 0 ? $sumaTotalSaldos : 0;
            $c->saldo_pendiente = $sumaTotalSaldos < 0 ? abs($sumaTotalSaldos) : 0;

            // 2) Saldo generado ESTE periodo (saldo_proximo de la factura actual)
            $sp = $c->factura_exist ? (int)($c->factura_exist->saldo_proximo ?? 0) : 0;
            $c->saldo_proximo_favor    = $sp > 0 ? $sp : 0;   // sobró → va al siguiente mes
            $c->saldo_proximo_pendiente = $sp < 0 ? abs($sp) : 0; // quedó debiendo

            return $c;
        });

        // Cuentas bancarias + asesores
        $bancos   = BancoCuenta::activas($aliadoId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // N_PLANO actual por razón social
        $planosActuales = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->where('mes_plano', $mes)->where('anio_plano', $anio)
            ->select('razon_social', DB::raw('MAX(n_plano) as n_plano_max'))
            ->groupBy('razon_social')
            ->get()->keyBy('razon_social');

        // ─── Saldo neto de la EMPRESA por empresa_id ─────────────────
        // Sin límite de fecha: suma TODOS los saldo_proximo (incluyendo meses futuros ya registrados).
        // Así si mayo ya consumió el saldo de abril, la empresa también lo refleja.
        $saldoNetoEmpresa = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('saldo_proximo')
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNull('deleted_at')
            ->sum('saldo_proximo');

        $saldoEmpresaFavor    = $saldoNetoEmpresa > 0 ? (int)$saldoNetoEmpresa : 0;
        $saldoEmpresaPendiente = $saldoNetoEmpresa < 0 ? (int)abs($saldoNetoEmpresa) : 0;

        return view('admin.facturacion.empresa', compact(
            'empresa', 'contratos', 'facturasExistentes',
            'mes', 'anio', 'bancos', 'planosActuales', 'asesores',
            'saldoEmpresaFavor', 'saldoEmpresaPendiente'
        ));
    }

    // ─── Facturar (crear factura) ────────────────────────────────────
    public function facturar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'contratos'            => 'required|array|min:1',
            'contratos.*'          => 'exists:contratos,id',
            'tipo'                 => 'required|in:afiliacion,planilla',
            'mes'                  => 'required|integer|min:1|max:12',
            'anio'                 => 'required|integer|min:2000|max:2100',
            'forma_pago'           => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'               => 'required|in:pre_factura,pagada,prestamo',
            'es_prestamo'          => 'boolean',
            // Array dinámico de consignaciones bancarias
            'consignaciones'             => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'valor_efectivo'       => 'nullable|numeric|min:0',
            'valor_prestamo'       => 'nullable|numeric|min:0',
            'otros'                => 'nullable|numeric|min:0',
            'otros_admon'          => 'nullable|numeric|min:0',
            'mensajeria'           => 'nullable|numeric|min:0',
            'np'                   => 'nullable|integer',
            'n_plano'              => 'nullable|integer',
            'empresa_id'           => 'nullable|integer',
            'aplicar_saldo'        => 'boolean',
            'observacion'          => 'nullable|string|max:500',
            // SS editables desde la UI (override manual)
            'v_eps_manual'         => 'nullable|integer|min:0',
            'v_arl_manual'         => 'nullable|integer|min:0',
            'v_afp_manual'         => 'nullable|integer|min:0',
            'v_caja_manual'        => 'nullable|integer|min:0',
            // Distribución de afiliación (override manual desde la UI)
            'dist_asesor'          => 'nullable|integer|min:0',
            'dist_retiro'          => 'nullable|integer|min:0',
            'dist_encargado'       => 'nullable|integer|min:0',
            'dist_admon'           => 'nullable|integer|min:0',
            // Retiro en el período
            'es_retiro'            => 'boolean',
            'fecha_retiro'         => 'nullable|date',
            'dias_retiro'          => 'nullable|integer|min:1|max:30',
        ]);

        $np = $validated['np'] ?? null;
        // Si es pago masivo y no tiene NP, generar uno nuevo.
        // El NP es por empresa + mes + año: cada empresa tiene su propio contador
        // que se reinicia cada mes. Primer pago mayo=1, segundo=2, junio vuelve a 1.
        if (!$np && count($validated['contratos']) > 1) {
            $mes        = (int)($validated['mes']        ?? now()->month);
            $anio       = (int)($validated['anio']       ?? now()->year);
            $empresaIdNp = $validated['empresa_id'] ?? null;
            $qNp = DB::table('facturas')
                ->where('aliado_id', $aliadoId)
                ->where('mes',  $mes)
                ->where('anio', $anio)
                ->whereNull('deleted_at');
            // Si viene empresa_id, restringir el contador a esa empresa
            if ($empresaIdNp) {
                $qNp->where('empresa_id', $empresaIdNp);
            }
            $np = ($qNp->max('np') ?? 0) + 1;
        }

        $mes  = (int)$validated['mes'];
        $anio = (int)$validated['anio'];

        // ─── Validar orden secuencial de facturación ────────────────────────
        // Solo para facturas individuales (single contrato); en masivo se omite
        // la validación por desempeño, ya que empresa gestiona su propio orden.
        foreach ($validated['contratos'] as $cId) {
            $cChk = Contrato::where('aliado_id', $aliadoId)->find($cId);
            if ($cChk) {
                $gap = $this->verificarOrdenFacturacion($aliadoId, $cChk, $mes, $anio);
                if ($gap) {
                    return response()->json([
                        'error'       => true,
                        'mensaje'     => $gap['mensaje'],
                        'mes_gap'     => $gap['mes'],
                        'anio_gap'    => $gap['anio'],
                    ], 422);
                }
            }
        }

        // ─── Pre-calcular n_plano compartido por razón social ──────────────
        // Todos los contratos de la misma RS en este lote deben tener el MISMO n_plano.
        // Se calcula UNA VEZ por RS antes de entrar al foreach.
        $nPlanosPorRS = [];

        // ─── Pre-calcular totales del pago ─────────────────────────────────
        // valor_consignado = suma de TODAS las consignaciones del array
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalPagoConsig    = array_sum(array_column($consignacionesData, 'valor'));
        $totalPagoEfectivo  = (int)($validated['valor_efectivo']  ?? 0);
        $totalPagoPrestamo  = (int)($validated['valor_prestamo']  ?? 0);

        // IVA configurado (se aplica a clientes con IVA=SI, igual que en la factura)
        $cfgIvaPct = \App\Models\ConfiguracionBrynex::porcentajeIva(); // ej: 19

        // Calcular COSTO BRUTO ESTIMADO de cada contrato para proporcionar el pago.
        // Para planilla: SS(30 días) + admon + seguro — no basta con solo admon.
        // Para afiliación: costo_afiliacion + seguro.
        $totalesPorContrato = [];
        foreach ($validated['contratos'] as $cId) {
            $c = Contrato::where('aliado_id', $aliadoId)
                ->with(['eps', 'arl', 'pension', 'caja', 'tipoModalidad'])
                ->find($cId);
            if (!$c) { $totalesPorContrato[$cId] = 1; continue; }
            // Detectar si es mes de afiliación
            $esMesIng = $c->fecha_ingreso
                && (int)$c->fecha_ingreso->month === $mes
                && (int)$c->fecha_ingreso->year  === $anio;
            $esAfil = $esMesIng && (!($c->tipoModalidad?->esIndependiente() ?? false)
                        || !($c->cobra_planilla_primer_mes ?? false));
            if ($esAfil) {
                $totalesPorContrato[$cId] = (int)($c->costo_afiliacion ?? 0) + (int)($c->seguro ?? 0);
            } else {
                // Planilla: usar calcularCotizacion() del MODELO — mismo método que la UI y
                // que facturar() usa para SS. Garantiza granTotal = sum(reales) exacto.
                $diasEst = $this->calcularDias($c, $mes, $anio);
                $cotiz   = $c->calcularCotizacion($diasEst);

                // Afiliación I ACT primer mes: se cobra junto con planilla (igual que facturar())
                $esIndActEst = (int)($c->tipo_modalidad_id) === 11;
                $afilEst = ($esMesIng && $esIndActEst)
                    ? (int)($c->costo_afiliacion ?? 0)
                    : 0;

                // calcularCotizacion devuelve ss+admon+seguro+iva (sin admon_asesor)
                // facturar() suma: ss + admon + admin_asesor + seguro + afil + iva
                $totalesPorContrato[$cId] = $cotiz['ss']
                    + $cotiz['admon']
                    + (int)($c->admon_asesor ?? 0)
                    + $cotiz['seguro']
                    + $cotiz['iva']
                    + $afilEst;
            }
            // Mínimo 1 para evitar peso cero en contratos sin costo
            if ($totalesPorContrato[$cId] <= 0) $totalesPorContrato[$cId] = 1;
        }
        $granTotal = array_sum($totalesPorContrato) ?: 1; // evitar división por 0

        // ─── numero_factura compartido para el lote masivo ─────────────────
        // En modo masivo todos los contratos comparten el MISMO número de recibo.
        // En modo individual se genera uno por llamada, pero aquí pre-calculamos uno solo.
        $esMasivo = count($validated['contratos']) > 1;
        $batchNumeroFactura = Factura::siguienteNumero($aliadoId); // único para el lote

        $facturasCreadas  = [];
        $omitidos         = [];  // contratos ya facturados para ese período
        // Acumuladores para ajuste de redondeo post-loop en la última factura del batch.
        // Garantiza sum(ef_i) = ef_total exactamente, eliminando residuos de redondeo.
$efAcum = $csAcum = $prAcum = $sfAcum = 0;
        // Saldo empresa a aplicar como credito en este batch (distribuido igualmente)
        $empresaId = $validated['empresa_id'] ?? null;
        $saldoEmpresaAplicar = 0;
        $contratosPendientes = count(array_filter($validated['contratos']));
        if ($empresaId && $esMasivo) {
            // ── Saldo neto REAL de la empresa (sin filtro de fecha) ──────────
            // Se debe sumar TODOS los saldo_proximo de empresa_id, incluyendo
            // los del mes actual ya facturados. Razón: al facturar un lote
            // parcial (ej. 9 de 14 contratos ya facturados en Mayo con sp=-87500),
            // esos negativos deben compensar el saldo positivo de Abril antes de
            // determinar si queda algún crédito real. Sin este criterio, el sistema
            // ve el saldo bruto de Abril (+700k) sin ver los -700k de Mayo ya
            // registrados, y aplica un descuento fantasma.
            $histSaldo = Factura::where('aliado_id', $aliadoId)
                ->where('empresa_id', $empresaId)
                ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
                ->whereNull('deleted_at')
                ->sum('saldo_proximo');
            // Solo aplicar como crédito si el neto es estrictamente positivo
            $saldoEmpresaAplicar = max(0, (int)$histSaldo);
        }

        DB::transaction(function () use (
            $validated, $aliadoId, $np, $mes, $anio,
            $esMasivo,
            &$facturasCreadas, &$omitidos, &$nPlanosPorRS,
            $totalPagoConsig, $totalPagoEfectivo, $totalPagoPrestamo,
            $consignacionesData, $totalesPorContrato, $granTotal, $batchNumeroFactura,
            &$efAcum, &$csAcum, &$prAcum, &$sfAcum,
            $saldoEmpresaAplicar, &$contratosPendientes
        ) {
            foreach ($validated['contratos'] as $contratoId) {
                $contrato = Contrato::where('aliado_id', $aliadoId)
                    ->with(['eps','arl','pension','caja','asesor','tipoModalidad'])
                    ->findOrFail($contratoId);


                // ─── Validación anti-duplicado ─────────────────────────────
                // Solo bloquea si ya existe factura para la MISMA cedula+mes+año+razon_social.
                // Permite facturar al mismo cliente desde distinta Razón Social.
                $facturasDup = Factura::where('aliado_id', $aliadoId)
                    ->where('cedula', $contrato->cedula)
                    ->where('mes', $mes)
                    ->where('anio', $anio)
                    ->whereNotIn('estado', ['anulada'])
                    ->get(['id', 'razon_social_id', 'estado']);

                $yaExiste = $facturasDup->contains(function ($f) use ($contrato) {
                    // Comparar como enteros para evitar fallos int vs string
                    return (int)$f->razon_social_id === (int)$contrato->razon_social_id;
                });

                if ($yaExiste) {
                    $rsNombre = $contrato->razonSocial?->razon_social ?? 'Individual';
                    $omitidos[] = [
                        'cedula'  => $contrato->cedula,
                        'nombre'  => $contrato->cliente?->primer_nombre . ' ' . $contrato->cliente?->primer_apellido,
                        'motivo'  => "Ya existe una factura para {$mes}/{$anio} bajo '{$rsNombre}'",
                    ];
                    $contratosPendientes--; // descontar aunque se omita
                    continue; // saltar este contrato
                }


                $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
                // I Act (id=11): cobra afiliación + planilla el mismo mes de ingreso
                // I Venc (id=10): solo afiliación el primer mes
                $esIndAct = (int)($contrato->tipo_modalidad_id) === 11;
                $tipoForzado = $validated['tipo'];

                if ($contrato->fecha_ingreso) {
                    $mesIngreso  = (int)$contrato->fecha_ingreso->month;
                    $anioIngreso = (int)$contrato->fecha_ingreso->year;
                    $esMesIngreso = ($mes === $mesIngreso && $anio === $anioIngreso);

                    if ($esMesIngreso) {
                        if (!$esIndependiente) {
                            // Empresa / dependiente: siempre afiliación en el mes de ingreso
                            $tipoForzado = 'afiliacion';
                        } elseif (!$esIndAct) {
                            // I Venc: solo afiliación el primer mes
                            $tipoForzado = 'afiliacion';
                        }
                        // I Act: tipo=planilla (afiliación se suma al total, ver abajo)
                    }
                }

                // ─── Detectar I Act primer mes ─────────────────────────────
                // I Act (id=11) en mes de ingreso: paga afiliación + planilla juntas
                $esIndActPrimerMes = $esIndAct && isset($esMesIngreso) && $esMesIngreso;

                // ─── Tipo, días y SS ───────────────────────────────────────
                $esAfiliacion = $tipoForzado === 'afiliacion';
                // Para I ACT primer mes: días = activos del mes de ingreso (no 0)
                if ($esIndActPrimerMes) {
                    $diasCotizar = max(1, 30 - (int)$contrato->fecha_ingreso->day + 1);
                } elseif ($esAfiliacion) {
                    $diasCotizar = 0;
                } else {
                    $diasCotizar = $this->calcularDias($contrato, $mes, $anio);
                }

                // SS = 0 en afiliación pura (I VENC, empresa);
                // Para I ACT primer mes se calcula con días reales del mes de ingreso.
                // Si hay retiro, sobreescribir los días con los del retiro.
                $esRetiro    = !empty($validated['es_retiro']);
                $fechaRetiro = $esRetiro ? ($validated['fecha_retiro'] ?? null) : null;
                $diasRetiro  = $esRetiro ? (int)($validated['dias_retiro'] ?? $diasCotizar) : null;

                if ($esRetiro && $diasRetiro !== null && !$esAfiliacion) {
                    // Retiro: usar los días proporcionales indicados por el usuario
                    $diasCotizar = $diasRetiro;
                }

                // ── Fuente de verdad: calcularCotizacion() del modelo ──────────────────────
                // Usar el mismo método que la UI para que total facturado = estimación exacta.
                if ($esAfiliacion && !$esIndActPrimerMes) {
                    $calcSS = ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0];
                } else {
                    $cotizacion = $contrato->calcularCotizacion($diasCotizar);
                    $calcSS = [
                        'eps'  => (int)($cotizacion['eps']  ?? 0),
                        'arl'  => (int)($cotizacion['arl']  ?? 0),
                        'afp'  => (int)($cotizacion['pen']  ?? 0),
                        'caja' => (int)($cotizacion['caja'] ?? 0),
                    ];
                }

                // Override manual de SS desde la UI (solo en modo individual — 1 contrato).
                // En modo masivo el modal muestra TOTALES del lote, no valores individuales.
                // Si se aplicaran aquí, cada factura individual recibiría el total de TODOS,
                // multiplicando el SS por la cantidad de personas. ← bug original.
                $esModoIndividual = count($validated['contratos']) === 1;
                if (!$esAfiliacion && $esModoIndividual) {
                    if (isset($validated['v_eps_manual']))  $calcSS['eps']  = intval($validated['v_eps_manual']);
                    if (isset($validated['v_arl_manual']))  $calcSS['arl']  = intval($validated['v_arl_manual']);
                    if (isset($validated['v_afp_manual']))  $calcSS['afp']  = intval($validated['v_afp_manual']);
                    if (isset($validated['v_caja_manual'])) $calcSS['caja'] = intval($validated['v_caja_manual']);
                }

                // Saldo previo del cliente (auto desde BD)
                $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mes, $anio);

                // Afiliación:
                // • I ACT primer mes: se incluye SIEMPRE junto con SS (pago conjunto)
                // • Afiliación pura (I VENC, empresa): total = costo_afiliacion + seguro
                // • Planilla normal: no se incluye afiliación
                $afiliacion = ($esAfiliacion || $esIndActPrimerMes)
                    ? (int)($contrato->costo_afiliacion ?? 0)
                    : 0;
                $seguro     = (int)($contrato->seguro ?? 0);

                // Admon:
                // • Afiliación pura (I VENC, empresa): sin admon mensual
                // • I ACT primer mes y planilla normal: con admon completa
                $admon       = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : intval($contrato->administracion ?? 0);
                $adminAsesor = ($esAfiliacion && !$esIndActPrimerMes) ? 0 : intval($contrato->admon_asesor   ?? 0);
                $otrosAdmon  = intval($validated['otros_admon'] ?? 0);

                $totalSS  = $calcSS['eps'] + $calcSS['arl'] + $calcSS['afp'] + $calcSS['caja'];
                $ivaBase  = $admon + $adminAsesor;
                $iva      = 0;

                // IVA aplica en planilla (sobre admon) — también para I ACT primer mes
                // Usar round() igual que calcularCotizacion() del modelo (no ceil)
                if (!$esAfiliacion || $esIndActPrimerMes) {
                    $clienteIva = DB::table('clientes')->where('cedula', $contrato->cedula)->value('iva');
                    if (strtoupper(trim($clienteIva ?? '')) === 'SI') {
                        $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
                        $iva    = (int) round($ivaBase * $cfgIva / 100);
                    }
                }

                // total = BRUTO (SS + admon + seguro + IVA + afiliacion + otros).
                // El anticipo (saldo_a_favor) y la deuda previa (saldo_pendiente) se guardan
                // en columnas separadas y el sistema acumulativo de saldo_proximo los maneja.
                $total = $totalSS + $admon + $adminAsesor + $otrosAdmon + $seguro + $afiliacion + $iva;

                // ─── Calcular distribución de afiliación ───────────────────
                $distAdmon = $distAsesor = $distRetiro = $distUtilidad = 0;
                if ($esAfiliacion && $afiliacion > 0) {
                    // Si el frontend envió valores manuales, usarlos
                    $hasManual = isset($validated['dist_asesor']) || isset($validated['dist_retiro'])
                              || isset($validated['dist_encargado']) || isset($validated['dist_admon']);

                    if ($hasManual) {
                        $distAsesor   = (int)($validated['dist_asesor']    ?? 0);
                        $distRetiro   = (int)($validated['dist_retiro']    ?? 0);
                        // dist_encargado se suma al dist_admon (internamente no hay campo separado en la tabla)
                        $distEncargado = (int)($validated['dist_encargado'] ?? 0);
                        $distAdmonRaw  = (int)($validated['dist_admon']     ?? 0);
                        // dist_admon en la tabla = empresa admon puro
                        $distAdmon    = $distAdmonRaw;
                        // Recalcular utilidad = total - todos los demás
                        $distUtilidad = max(0, $afiliacion - $distAsesor - $distRetiro - $distEncargado - $distAdmon);
                    } else {
                        $cfg = \App\Models\ConfiguracionAliado::paraAliado($aliadoId, $contrato->plan_id);
                        if ($cfg) {
                            $dist         = $cfg->calcularDistribucion($afiliacion, $contrato->asesor ?? null);
                            $distAdmon    = $dist['admon'];
                            $distAsesor   = $dist['asesor'];
                            $distRetiro   = $dist['retiro'];
                            $distUtilidad = $dist['utilidad'];
                        }
                    }
                }

                // ─── n_plano compartido por RS en este lote ────────────────
                // Todos los contratos de la misma RS deben tener el mismo n_plano.
                $rsId = $contrato->razon_social_id;
                if ($rsId && !isset($nPlanosPorRS[$rsId])) {
                    $nPlanosPorRS[$rsId] = static::_nPlanoParaRS($aliadoId, $rsId, $mes, $anio);
                }
                $nPlanoFactura = $rsId ? ($nPlanosPorRS[$rsId] ?? null) : null;

                // --- Distribucion IGUAL entre contratos del batch ---
                // Ef, consignacion y saldo a favor en PARTES IGUALES.
                // El ultimo contrato recibe el residuo (floor) para que sumen exacto.
                $nContratos = max(1, count($validated['contratos']));
                $contratosPendientes--;
                $esUltimoNoOmitido = ($contratosPendientes === 0);
                $vSaldoFavor = 0; // Inicializar siempre (evita Undefined variable)

                if ($esUltimoNoOmitido) {
                    // Ultimo: residuo exacto
                    $vConsig     = $totalPagoConsig     - $csAcum;
                    $vEfectivo   = $totalPagoEfectivo   - $efAcum;
                    $vPrestamo   = $totalPagoPrestamo   - $prAcum;
                    $vSaldoFavor = $saldoEmpresaAplicar - $sfAcum;
                } else {
                    $vConsig     = (int) floor($totalPagoConsig     / $nContratos);
                    $vEfectivo   = (int) floor($totalPagoEfectivo   / $nContratos);
                    $vPrestamo   = (int) floor($totalPagoPrestamo   / $nContratos);
                    $vSaldoFavor = (int) floor($saldoEmpresaAplicar / $nContratos);
                    $csAcum  += $vConsig;
                    $efAcum  += $vEfectivo;
                    $prAcum  += $vPrestamo;
                    $sfAcum  += $vSaldoFavor;
                }

                $factura = Factura::create([
                    'aliado_id'        => $aliadoId,
                    'numero_factura'   => $batchNumeroFactura,
                    'tipo'             => $tipoForzado,
                    'cedula'           => $contrato->cedula,
                    'contrato_id'      => $contrato->id,
                    'mes'              => $mes,
                    'anio'             => $anio,
                    'fecha_pago'       => now()->toDateString(),
                    'estado'           => $validated['estado'],
                    'es_prestamo'      => $validated['estado'] === 'prestamo',
                    'forma_pago'       => $validated['forma_pago'],
                    'valor_consignado' => $vConsig,
                    'valor_efectivo'   => $vEfectivo,
                    'valor_prestamo'   => $vPrestamo,
                    'otros'            => (int)($validated['otros']      ?? 0),
                    'otros_admon'      => $otrosAdmon,
                    'mensajeria'       => (int)($validated['mensajeria'] ?? 0),
                    'dias_cotizados'   => $diasCotizar,
                    'v_eps'            => $calcSS['eps'],
                    'v_arl'            => $calcSS['arl'],
                    'v_afp'            => $calcSS['afp'],
                    'v_caja'           => $calcSS['caja'],
                    'total_ss'         => $totalSS,
                    'admon'            => $admon,
                    'admin_asesor'     => $adminAsesor,
                    'seguro'           => $seguro,
                    'afiliacion'       => $afiliacion,
                    'iva'              => $iva,
                    'total'            => max(0, $total),
                    'dist_admon'       => $distAdmon,
                    'dist_asesor'      => $distAsesor,
                    'dist_retiro'      => $distRetiro,
                    'dist_utilidad'    => $distUtilidad,
                    'np'               => $np,
                    'n_plano'          => $nPlanoFactura,
                    'empresa_id'       => $validated['empresa_id'] ?? null,
                    'razon_social_id'  => $contrato->razon_social_id,
                    'usuario_id'       => Auth::id(),
                    'observacion'      => $validated['observacion'] ?? null,
                ]);

                // ─── Guardar consignaciones bancarias ──────────────────────
                // Las consignaciones representan comprobantes bancarios reales.
                // Se guardan UNA SOLA VEZ (en la primera factura del lote) con
                // el valor real. Las demás facturas del NP solo registran
                // valor_consignado proporcional en su campo, sin fila en consignaciones.
                if (empty($facturasCreadas)) {
                    // Esta es la PRIMERA factura del lote → guardar consignaciones reales
                    foreach ($consignacionesData as $cs) {
                        $valorCs = (int)$cs['valor'];
                        if ($valorCs <= 0) continue;
                        \App\Models\Consignacion::create([
                            'aliado_id'       => $aliadoId,
                            'factura_id'      => $factura->id,
                            'banco_cuenta_id' => (int) $cs['banco_cuenta_id'],
                            'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                            'valor'           => $valorCs,
                            'referencia'      => $cs['referencia'] ?? null,
                            'confirmado'      => false,
                            'usuario_id'      => Auth::id(),
                        ]);
                    }
                }
                // Las facturas 2..N del lote NO crean filas en consignaciones;
                // su valor_consignado proporcional ya quedó en el campo de la factura.

                // ─── Calcular saldo_proximo ────────────────────────────────
                // saldo_proximo = valor_efectivo + valor_consignado - total_bruto
                //
                // El anticipo (saldo_a_favor) ya está acumulado en el historial de
                // facturas anteriores como saldo_proximo positivo.  Sumarlo aquí
                // generaría doble conteo → se usa SIEMPRE la misma fórmula base.
                //
                // Con SUM acumulativo (SUM saldo_proximo de empresa):
                //   Mes anterior pagó de más → sp = +X
                //   Este mes aplica anticipo, paga solo diferencia → sp = ef+cs-total
                //   Acumulado = +X + (ef+cs-total) → refleja correctamente el neto.
                $pagadoReal = (int)$factura->valor_consignado + (int)$factura->valor_efectivo;
                if ($factura->es_prestamo) {
                    // Préstamo: debe el bruto completo al mes siguiente
                    $saldoProximo = -(int)$factura->total;
                } else {
                    // ─── Saldo proximo según tipo de pago ─────────────────
                    if ($esMasivo && $saldoEmpresaAplicar > 0) {
                        // Batch empresa con credito: fijar saldo_proximo = -(credito/n)
                        // Garantiza: SUM(saldo_proximo) = -saldo_empresa exacto → empresa = 0
                        // La diferencia de redondeo de ceil() en SS queda absorbida en la
                        // distribución del efectivo, no en el balance contable de la empresa.
                        $saldoProximo = -$vSaldoFavor;
                    } else {
                        // Pago normal sin credito empresa: pagado - bruto
                        $saldoProximo = $pagadoReal - (int)$factura->total;
                    }
                }
                $factura->update(['saldo_proximo' => $saldoProximo]);

                // Si está pagada o en préstamo, generar plano
                if (in_array($factura->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
                    $factura->load('contrato.eps', 'contrato.arl', 'contrato.pension', 'contrato.caja', 'contrato.razonSocial');
                    Plano::generarDesdeContrato($contrato, $factura, $fechaRetiro ?? null);
                }

                $facturasCreadas[] = $factura->id;
            }
        });


        $primera = count($facturasCreadas) === 1 ? $facturasCreadas[0] : null;

        // Si no se creó ninguna (todos eran duplicados) → error
        if (empty($facturasCreadas) && !empty($omitidos)) {
            $nombres = collect($omitidos)->pluck('nombre')->join(', ');
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Ya existe factura para este período: ' . $nombres . '. Anule la factura existente antes de refacturar.',
                'omitidos'=> $omitidos,
            ], 422);
        }

        // Éxito con posibles omitidos parciales
        $msgOmit = !empty($omitidos)
            ? ' | ' . count($omitidos) . ' omitido(s) por duplicado.'
            : '';

        return response()->json([
            'ok'              => true,
            'mensaje'         => count($facturasCreadas) . ' factura(s) generada(s) correctamente.' . $msgOmit,
            'facturas'        => $facturasCreadas,
            'omitidos'        => $omitidos,
            'recibo_url'      => $primera ? route('admin.facturacion.recibo', $primera) : null,
            // IDs de consignaciones creadas (solo las de la primera factura del batch)
            // El JS los usa para subir las imágenes de soporte después de crear la factura.
            'consignacion_ids' => \App\Models\Consignacion::whereIn('factura_id', $facturasCreadas)
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all(),
        ]);
    }

    // ─── Registrar abono ─────────────────────────────────────────────
    public function abonar(Request $request, int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)->findOrFail($facturaId);

        $validated = $request->validate([
            'valor'            => 'required|numeric|min:1',
            'forma_pago'       => 'required|in:efectivo,consignacion,mixto',
            'valor_efectivo'   => 'nullable|numeric|min:0',
            'valor_consignado' => 'nullable|numeric|min:0',
            'banco_cuenta_id'  => 'nullable|integer',
            'observacion'      => 'nullable|string|max:300',
        ]);

        $abono = DB::transaction(function () use ($factura, $validated) {
            $ab = Abono::create([
                ...$validated,
                'factura_id' => $factura->id,
                'fecha'      => now()->toDateString(),
                'usuario_id' => Auth::id(),
            ]);

            // ¿El total abonado cubre el total?
            $factura->refresh();
            if ($factura->estaCompletamentePagada()) {
                $factura->update(['estado' => Factura::ESTADO_PAGADA]);
                // Generar plano si no existe
                if (!$factura->plano) {
                    $c = $factura->contrato()->with('eps','arl','pension','caja','tipoModalidad')->first();
                    if ($c) Plano::generarDesdeContrato($c, $factura);
                }
            } else {
                $factura->update(['estado' => Factura::ESTADO_ABONO]);
            }

            return $ab;
        });

        return response()->json([
            'ok'             => true,
            'abono_id'       => $abono->id,
            'total_abonado'  => $factura->total_abonado,
            'saldo_restante' => $factura->saldo_restante,
            'estado'         => $factura->estado,
            'recibo_url'     => route('admin.facturacion.recibo-abono', $abono->id),
        ]);
    }

    // ─── Recibo de factura ───────────────────────────────────────────
    public function recibo(int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente','contrato.eps','contrato.arl',
                    'contrato.pension','contrato.caja','contrato.razonSocial',
                    'razonSocial','usuario','abonos',
                    'consignaciones.bancoCuenta'])   // ← todas las cuentas consignadas
            ->findOrFail($facturaId);

        // Grupo NP: todas las facturas del mismo NP/mes/año/empresa
        // IMPORTANTE: filtrar también por empresa_id para no mezclar trabajadores
        // de diferentes empresas que coincidan en NP+mes+año dentro del mismo aliado.
        $grupoNp = null;
        if ($factura->np) {
            $qGrupo = Factura::where('aliado_id', $aliadoId)
                ->where('np', $factura->np)
                ->where('mes',  $factura->mes)
                ->where('anio', $factura->anio);

            // Si la factura tiene empresa_id, restringir al mismo grupo empresa
            if ($factura->empresa_id) {
                $qGrupo->where('empresa_id', $factura->empresa_id);
            }

            $grupoNp = $qGrupo
                ->with(['contrato.cliente','contrato.eps','contrato.arl',
                        'contrato.pension','contrato.caja','contrato.razonSocial',
                        'abonos','consignaciones.bancoCuenta'])
                ->orderBy('id')
                ->get();
        }

        return view('admin.facturacion.recibo',
            compact('factura','grupoNp'));
    }

    // ─── Anular factura (solo admin) ─────────────────────────────────
    public function anular(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'abonos', 'plano'])
            ->findOrFail($facturaId);

        // ── Protección: factura con planilla pagada solo la puede anular superadmin BryNex ──
        $numeroPlanillaOp = $factura->plano?->numero_planilla;
        if ($numeroPlanillaOp) {
            $esSuperBrynex = $user->es_brynex && $user->hasRole('superadmin');
            if (!$esSuperBrynex) {
                return response()->json([
                    'ok'      => false,
                    'message' => "Esta factura tiene planilla pagada al operador (Nº {$numeroPlanillaOp}). "
                               . 'Solo un superadmin de BryNex puede anularla.',
                ], 403);
            }
        }

        $motivo = trim($request->input('motivo', ''));
        if (!$motivo) {
            return response()->json(['ok' => false, 'message' => 'Debe indicar el motivo de anulación.'], 422);
        }

        // Si tiene NP, anula todo el grupo
        $facturasAnular = collect([$factura]);
        if ($factura->np && $request->boolean('todo_np', false)) {
            $facturasAnular = Factura::where('aliado_id', $aliadoId)
                ->where('np', $factura->np)
                ->where('mes',  $factura->mes)
                ->where('anio', $factura->anio)
                ->with(['abonos', 'plano'])
                ->get();
        }


        DB::transaction(function () use ($facturasAnular, $motivo, $aliadoId, $user) {
            foreach ($facturasAnular as $f) {
                // Registrar en bitácora ANTES de anular
                Bitacora::registrar(
                    accion: 'deleted',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Factura #{$f->numero_factura} anulada. Motivo: {$motivo}",
                    detalle: [
                        'snapshot' => $f->toArray(),
                        'abonos'   => $f->abonos->toArray(),
                        'plano_id' => $f->plano?->id,
                        'motivo'   => $motivo,
                    ],
                    alidoId: $aliadoId
                );

                // Soft-delete de la factura (guarda motivo y quién anuló)
                $f->motivo_anulacion = $motivo;
                $f->anulado_por      = $user->id;
                $f->saldo_proximo    = 0; // limpiar para no influir en futuros cálculos
                $f->save();
                $f->delete(); // SoftDeletes → establece deleted_at

                // Soft-delete del plano (si existe)
                if ($f->plano) {
                    $f->plano->delete();
                }

                // Las consignaciones se eliminan físicamente (quedan en el snapshot de la bitácora)
                DB::table('consignaciones')->where('factura_id', $f->id)->delete();
                DB::table('abonos')->where('factura_id', $f->id)->delete();
            }
        });

        return response()->json([
            'ok'      => true,
            'mensaje' => $facturasAnular->count() > 1
                ? "{$facturasAnular->count()} facturas del NP {$factura->np} anuladas."
                : "Recibo #{$factura->numero_factura} anulado.",
        ]);
    }

    // ─── Listado de facturas anuladas ────────────────────────────────
    public function anuladas(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $q = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'razonSocial']);

        // Filtros opcionales
        if ($request->filled('cedula'))  $q->where('cedula', $request->cedula);
        if ($request->filled('mes'))     $q->where('mes',  $request->mes);
        if ($request->filled('anio'))    $q->where('anio', $request->anio);
        if ($request->filled('buscar')) {
            $b = $request->buscar;
            $q->where(fn($sq) => $sq->where('cedula','like',"%$b%")
                ->orWhere('numero_factura','like',"%$b%")
                ->orWhere('motivo_anulacion','like',"%$b%"));
        }

        $facturas = $q->orderByDesc('deleted_at')->paginate(25)->withQueryString();

        return view('admin.facturacion.anuladas', compact('facturas'));
    }

    // ─── Restaurar una factura anulada ───────────────────────────────
    public function restaurar(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->findOrFail($facturaId);

        DB::transaction(function () use ($factura, $aliadoId, $user) {
            $factura->restore();
            $factura->motivo_anulacion = null;
            $factura->anulado_por      = null;
            $factura->save();

            // Restaurar el plano asociado si existe
            Plano::onlyTrashed()->where('factura_id', $factura->id)->restore();

            Bitacora::registrar(
                accion: 'updated',
                modelo: 'Factura',
                registroId: $factura->id,
                descripcion: 'Recibo #'.$factura->numero_factura.' restaurado por '.($user->nombre ?? $user->name).'.',
                detalle: ['restaurado_por' => $user->id],
                alidoId: $aliadoId
            );
        });

        return response()->json(['ok' => true, 'mensaje' => "Recibo #{$factura->numero_factura} restaurado correctamente."]);
    }


    // ─── Recibo de abono ─────────────────────────────────────────────
    public function reciboAbono(int $abonoId)
    {
        $aliadoId = session('aliado_id_activo');
        $abono    = Abono::whereHas('factura', fn($q) => $q->where('aliado_id', $aliadoId))
            ->with(['factura.contrato.cliente','usuario'])
            ->findOrFail($abonoId);

        return view('admin.facturacion.recibo-abono', compact('abono'));
    }

    // ─── API: Saldo previo de cliente ────────────────────────────────
    public function saldoCliente(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;
        return response()->json(
            Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio)
        );
    }

    // ─── API: Verificar si mes ya está facturado para un contrato ────
    public function mesPagado(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $aliadoId)->find($contratoId);
        if (!$contrato) {
            return response()->json(['pagado' => false, 'mes' => null, 'anio' => null]);
        }

        $mes  = (int) ($request->mes  ?? now()->month);
        $anio = (int) ($request->anio ?? now()->year);

        // Verificar si ya existe factura pagada o pre-factura para este periodo
        $existe = Factura::where('aliado_id', $aliadoId)
            ->where('contrato_id', $contratoId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
            ->exists();

        // Si ya está pagado, calcular el siguiente mes disponible
        $mesSiguiente  = $mes;
        $anioSiguiente = $anio;
        if ($existe) {
            $mesSiguiente++;
            if ($mesSiguiente > 12) { $mesSiguiente = 1; $anioSiguiente++; }
        }

        // Calcular saldo del cliente para el nuevo mes — SOLO del contrato actual
        $saldo = Factura::saldoClienteMesPrevio(
            $aliadoId,
            $contrato->cedula,
            $existe ? $mesSiguiente  : $mes,
            $existe ? $anioSiguiente : $anio,
            $contrato->id  // ← aislar por contrato, no mezclar con otros contratos de la misma cédula
        );

        // Verificar si hay meses anteriores sin facturar (orden secuencial)
        $gap = $this->verificarOrdenFacturacion(
            $aliadoId,
            $contrato,
            $existe ? $mesSiguiente  : $mes,
            $existe ? $anioSiguiente : $anio
        );

        // ── Préstamos pendientes del cliente ──────────────────────────
        // Retorna facturas en estado=prestamo con saldo restante > 0.
        // El JS del modal usa esto para ofrecer cobrar el préstamo junto a la nueva factura.
        $prestamosRaw = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $contrato->cedula)
            ->prestamoPendiente()
            ->with('abonos')
            ->get();

        $prestamosPendientes = $prestamosRaw
            ->filter(fn($f) => $f->saldo_pendiente_prestamo > 0)
            ->map(fn($f) => [
                'id'     => $f->id,
                'mes'    => $f->mes,
                'anio'   => $f->anio,
                'total'  => (int)$f->total,
                'saldo'  => $f->saldo_pendiente_prestamo,
            ])->values();

        return response()->json([
            'pagado'                   => $existe,
            'mes'                      => $existe ? $mesSiguiente  : $mes,
            'anio'                     => $existe ? $anioSiguiente : $anio,
            'saldo_a_favor'            => $saldo['a_favor']   ?? 0,
            'saldo_pendiente'          => $saldo['pendiente'] ?? 0,
            // Información de gap para advertencia en UI
            'tiene_gap'                => !is_null($gap),
            'gap_mes'                  => $gap['mes']     ?? null,
            'gap_anio'                 => $gap['anio']    ?? null,
            'gap_mensaje'              => $gap['mensaje'] ?? null,
            // Préstamos pendientes del cliente
            'tiene_prestamo_pendiente' => $prestamosPendientes->isNotEmpty(),
            'prestamos_pendientes'     => $prestamosPendientes,
        ]);
    }


    // ─── API: N_PLANO actual de una razón social ─────────────────────
    public function planoActual(Request $request, int $razonSocialId)
    {
        $aliadoId = session('aliado_id_activo');
        $rs = DB::table('razones_sociales')->find($razonSocialId);
        $actual = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->max('n_plano') ?? 0;

        return response()->json([
            'n_plano_actual'    => $actual,
            'n_plano_siguiente' => $actual + 1,
            'razon_social'      => $rs?->razon_social ?? '',
        ]);
    }

    // ─── Helpers privados ────────────────────────────────────────────

    /**
     * Verifica que no exista un "gap" (mes sin facturar) antes del período solicitado.
     *
     * Regla: el primer mes facturable es el mes de fecha_ingreso.
     * Cada mes siguiente debe tener al menos una factura registrada antes de permitir
     * facturar el mes actual.
     *
     * @return array|null  null si todo OK; ['mes','anio','mensaje'] si hay gap.
     */
    private function verificarOrdenFacturacion(int $aliadoId, Contrato $contrato, int $mes, int $anio): ?array
    {
        // Convertir a entero YYYYMM para comparación simple
        $periodoTarget = $anio * 100 + $mes;

        // Obtener todos los períodos (YYYYMM) que ya tienen factura para este contrato
        $periodosBilled = Factura::where('aliado_id', $aliadoId)
            ->where('contrato_id', $contrato->id)
            ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
            ->get(['mes', 'anio'])
            ->map(fn($f) => (int)$f->anio * 100 + (int)$f->mes)
            ->unique()
            ->sort()
            ->values();

        // Si no hay ninguna factura previa, permitir facturar libremente
        if ($periodosBilled->isEmpty()) {
            return null;
        }

        // El período máximo ya facturado
        $ultimoPeriodo = $periodosBilled->max();

        // Si el período solicitado ya existe o es el siguiente natural, OK
        if ($periodosBilled->contains($periodoTarget)) {
            return null; // ya facturado (anti-duplicado lo manejará después)
        }

        // Calcular el "siguiente esperado" al último facturado
        $ultimoMes  = $ultimoPeriodo % 100;
        $ultimoAnio = (int)($ultimoPeriodo / 100);
        $sigMes     = $ultimoMes  === 12 ? 1  : $ultimoMes  + 1;
        $sigAnio    = $ultimoMes  === 12 ? $ultimoAnio + 1 : $ultimoAnio;
        $siguientePeriodo = $sigAnio * 100 + $sigMes;

        // Si el target ES el siguiente esperado, está perfecto
        if ($periodoTarget === $siguientePeriodo) {
            return null;
        }

        // Si el target es MENOR al último, puede ser retro-facturación de un hueco puntual — permitir
        if ($periodoTarget < $ultimoPeriodo) {
            return null;
        }

        // Hay un salto: el período solicitado está más de 1 mes adelante del último facturado
        // Exigir que se facture el mes inmediatamente siguiente al último
        $nombreFaltante = \Carbon\Carbon::create($sigAnio, $sigMes, 1)->translatedFormat('F Y');
        $nombreTarget   = \Carbon\Carbon::create($anio, $mes, 1)->translatedFormat('F Y');

        return [
            'mes'     => $sigMes,
            'anio'    => $sigAnio,
            'mensaje' => "Debe facturar {$nombreFaltante} antes de continuar con {$nombreTarget}.",
        ];
    }


    /**
     * Calcula el siguiente n_plano para una razón social en un período dado.
     * Siempre retorna 1.
     */
    private static function _nPlanoParaRS(int $aliadoId, ?int $razonSocialId, int $mes, int $anio): int
    {
        // Siempre inicia en 1. El aliado actualiza manualmente a 2, 3...
        // cuando hace el pago ante el operador PILA, para separar lotes enviados.
        return 1;
    }

    private function calcularDias(Contrato $contrato, int $mes, int $anio): int
    {
        // Tiempo Parcial: se devuelve 30 porque ARL cotiza mensual completo.
        // AFP y CAJA usan sus propios días; ver calcularSS().
        $mod = $contrato->tipoModalidad;
        if ($mod && $mod->esTiempoParcial()) {
            return 30;
        }

        if (!$contrato->fecha_ingreso) return 30;
        $fIng        = $contrato->fecha_ingreso;
        $mesIngreso  = (int)$fIng->month;
        $anioIngreso = (int)$fIng->year;

        // ① Mes de ingreso → afiliación, no hay días de planilla
        if ($mesIngreso === $mes && $anioIngreso === $anio) {
            return 0;
        }

        // ② Mes siguiente al ingreso → primera planilla: días activos del mes de ingreso
        $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior = $mes === 1 ? $anio - 1 : $anio;
        if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
            return max(1, 30 - $fIng->day + 1);
        }

        // ③ Mes normal
        return 30;
    }

    private function calcularSS(Contrato $contrato, int $dias): array
    {
        $aliadoId = session('aliado_id_activo');
        $ibc      = (int)($contrato->ibc ?? $contrato->salario ?? 0);
        $nArl     = (int)($contrato->n_arl ?? 1);
        $plan     = $contrato->plan;
        $mod      = $contrato->tipoModalidad;
        $esTP     = $mod && $mod->esTiempoParcial();
        $esIndep  = $contrato->esIndependiente(); // detectar modalidad real

        // CRÍTICO: usar porcentajes según modalidad.
        // Antes usaba siempre dependiente → SS incorrecto para I ACT/I VENC.
        // Eso causaba mismatch entre granTotal y sum(totales_reales) en ~$75k.
        if ($esIndep) {
            $pctEps = \App\Models\ConfiguracionBrynex::pctSaludIndependiente();
            $pctPen = \App\Models\ConfiguracionBrynex::pctPensionIndependiente();
            $pctCaj = (float)($contrato->porcentaje_caja
                       ?? \App\Models\ConfiguracionBrynex::pctCajaIndependienteAlto());
        } else {
            $pctEps = \App\Models\ConfiguracionBrynex::pctSaludDependiente();
            $pctPen = \App\Models\ConfiguracionBrynex::pctPensionDependiente();
            $pctCaj = \App\Models\ConfiguracionBrynex::pctCajaDependiente();
        }
        $pctArl = \App\Models\ArlTarifa::porcentajePara($nArl, $aliadoId);

        $r = fn($v) => (int)(ceil($v / 100) * 100);

        if ($esTP) {
            // Tiempo Parcial: IBC diferente por entidad
            // ARL  = SM_completo × tasaArl
            // AFP  = SM × factor_afp × pctPen
            // CAJA = SM × factor_caja × pctCaja
            $diasP      = $mod->diasPorEntidad();
            $factorMap  = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
            $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
            $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

            $sm      = (float) \App\Models\ConfiguracionBrynex::obtener('salario_minimo', 1423500);
            $ibcArl  = $sm;
            $ibcAfp  = round($sm * $factorAfp);
            $ibcCaja = round($sm * $factorCaja);

            return [
                'eps'  => 0,
                'arl'  => ($plan?->incluye_arl)     ? $r($ibcArl  * $pctArl / 100) : 0,
                'afp'  => ($plan?->incluye_pension) ? $r($ibcAfp  * $pctPen / 100) : 0,
                'caja' => ($plan?->incluye_caja)    ? $r($ibcCaja * $pctCaj / 100) : 0,
            ];
        }

        // Normal: mes completo → round() igual que calcularCotizacion() del modelo.
        // El saldo_proximo en batches empresa se fija directamente a -credit_i,
        // por lo que el balance de empresa es correcto con round() o ceil().
        // Usar round() hace que total almacenado = estimación UI → recibo exacto.
        $epsMes  = ($plan?->incluye_eps)     ? (int) round($ibc * $pctEps / 100) : 0;
        $arlMes  = ($plan?->incluye_arl)     ? (int) round($ibc * $pctArl / 100) : 0;
        $afpMes  = ($plan?->incluye_pension) ? (int) round($ibc * $pctPen / 100) : 0;
        $cajaMes = ($plan?->incluye_caja)    ? (int) round($ibc * $pctCaj / 100) : 0;

        if ($dias < 30) {
            return [
                'eps'  => $r($epsMes  * $dias / 30),
                'arl'  => $r($arlMes  * $dias / 30),
                'afp'  => $r($afpMes  * $dias / 30),
                'caja' => $r($cajaMes * $dias / 30),
            ];
        }

        // ── Cargo sin-CCF: dependiente E o Ingreso-Retiro sin caja ───────
        // Se cobra $100 fijos a la caja cuando el plan no incluye CCF.
        // Solo aplica en planilla (dias > 0, garantizado porque dias=30 aqui).
        if ($cajaMes === 0 && $contrato->aplicaCargoSinCcf()) {
            $cajaMes = \App\Models\Contrato::CARGO_SIN_CCF;
        }

        return ['eps' => $epsMes, 'arl' => $arlMes, 'afp' => $afpMes, 'caja' => $cajaMes];
    }

    // ─── API: Saldos para N contratos (modo masivo empresa) ─────────
    // GET /admin/facturacion/api/saldos-contratos?contratos[]=1&contratos[]=2&mes=5&anio=2026
    public function saldosContratos(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $contratoIds = array_filter((array)$request->contratos);
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        // ── Obtener empresa_id desde los contratos seleccionados ───────
        // Todos los contratos del modal masivo pertenecen a la misma empresa.
        // Calculamos el saldo neto de la empresa (SUM saldo_proximo de TODOS los
        // contratos de la empresa, no solo los seleccionados) para que el
        // anticipo de un trabajador compense la cartera de otro.
        $empresaId = null;
        if (!empty($contratoIds)) {
            $primerContrato = Contrato::where('aliado_id', $aliadoId)
                ->whereIn('id', $contratoIds)
                ->with('cliente')
                ->first();
            if ($primerContrato) {
                // Buscar empresa_id desde clientes.cod_empresa
                $empresaId = DB::table('clientes')
                    ->where('cedula', $primerContrato->cedula)
                    ->value('cod_empresa');
            }
        }

        // ── Saldo neto REAL de la empresa ────────────────────────────────
        // Se suma TODOS los saldo_proximo de empresa_id sin restricción de fecha.
        // Razón: al facturar un lote parcial (ej. 5 de 14 contratos en Mayo),
        // los 9 ya facturados de Mayo tienen saldo_proximo negativo que DEBEN
        // compensar el saldo positivo de Abril. Si filtramos por mes < actual,
        // excluimos esos negativos de Mayo y el saldo queda inflado.
        //
        // Lógica:
        //   saldoNeto > 0 → empresa tiene anticipo a favor
        //   saldoNeto < 0 → empresa tiene cartera pendiente
        //   saldoNeto = 0 → empresa está al día (caso Fabio Arroyave)
        $saldoNeto = 0;
        if ($empresaId) {
            $saldoNeto = (int) Factura::where('aliado_id', $aliadoId)
                ->where('empresa_id', $empresaId)
                ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
                ->whereNotNull('saldo_proximo')
                ->whereNull('deleted_at')
                ->sum('saldo_proximo');
        } else {
            // Fallback: sumar por contratos individuales si no hay empresa_id
            foreach ($contratoIds as $cId) {
                $contrato = Contrato::where('aliado_id', $aliadoId)->find($cId);
                if (!$contrato) continue;
                $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mes, $anio, $cId);
                $saldoNeto += ($saldo['a_favor'] ?? 0) - ($saldo['pendiente'] ?? 0);
            }
        }

        // Convertir saldo neto a a_favor / pendiente para compatibilidad con el JS
        $totalAFavor    = $saldoNeto > 0 ? $saldoNeto : 0;
        $totalPendiente = $saldoNeto < 0 ? abs($saldoNeto) : 0;

        return response()->json([
            'total_a_favor'   => $totalAFavor,
            'total_pendiente' => $totalPendiente,
            'saldo_neto'      => $saldoNeto,   // neto para debugging
        ]);
    }

    // ─── Historial de pagos del cliente ─────────────────────────────
    public function historial(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');

        // Buscar el cliente directamente por cédula (no depende de contratos)
        $cliente = \App\Models\Cliente::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->first();

        // Si no existe el cliente en este aliado, abortar
        abort_if(!$cliente, 404, 'Cliente no encontrado.');

        // Traemos el contrato de referencia (solo para contexto visual del header)
        $contrato = Contrato::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente', 'razonSocial'])
            ->orderByDesc('created_at')
            ->first();

        // Si no hay contrato vigente, buscar cualquiera
        if (!$contrato) {
            $contrato = Contrato::where('aliado_id', $aliadoId)
                ->where('cedula', $cedula)
                ->with(['cliente', 'razonSocial'])
                ->orderByDesc('created_at')
                ->first();
        }

        // Filtros opcionales
        $filtroAnio = $request->integer('anio', 0);
        $filtroRs   = $request->get('razon_social_id', '');

        // Query principal
        $query = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with(['razonSocial', 'empresa', 'plano.razonSocial', 'usuario'])
            ->orderByDesc('anio')
            ->orderByDesc('mes');

        if ($filtroAnio > 0) {
            $query->where('anio', $filtroAnio);
        }
        if ($filtroRs !== '') {
            $query->where('razon_social_id', $filtroRs);
        }

        // Sin filtros activos: últimas 20
        $sinFiltros = !$filtroAnio && $filtroRs === '';
        if ($sinFiltros) {
            $facturas = $query->limit(20)->get();
        } else {
            $facturas = $query->get();
        }

        // Agrupar: [razon_social → [anio → [facturas]]]
        // Prioridad por FK: factura.razon_social_id → plano.razon_social_id (via relación)
        $agrupado = [];
        foreach ($facturas as $f) {
            $rs = $f->razonSocial?->razon_social
               ?? $f->plano?->razonSocial?->razon_social
               ?? 'Sin razón social';
            $anio = $f->anio;
            $agrupado[$rs][$anio][] = $f;
        }

        // Para filtros: años y razones sociales disponibles del cliente
        $aniosDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->select('anio')->distinct()->orderByDesc('anio')
            ->pluck('anio');

        $rsSocDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with('razonSocial')
            ->select('razon_social_id')->distinct()
            ->get()
            ->map(fn($f) => [
                'id'    => $f->razon_social_id,
                'label' => $f->razonSocial?->razon_social ?? 'Sin razón social',
            ])->unique('id');

        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                       'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        return view('admin.facturacion.historial', compact(
            'cliente', 'contrato', 'cedula', 'agrupado',
            'filtroAnio', 'filtroRs', 'sinFiltros',
            'aniosDisp', 'rsSocDisp', 'meses'
        ));
    }


    // ─── Imagen de consignación ────────────────────────────────────────
    /**
     * POST /admin/facturacion/consignacion/{id}/imagen
     * Sube la imagen/PDF de soporte de una consignación y guarda la ruta.
     */
    public function subirImagenConsignacion(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:8192', // 8 MB
        ]);

        // Eliminar imagen anterior si existe
        if ($consig->imagen_path && \Storage::disk('public')->exists($consig->imagen_path)) {
            \Storage::disk('public')->delete($consig->imagen_path);
        }

        $file = $request->file('imagen');
        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "consignaciones/{$aliadoId}/{$consig->factura_id}",
            "{$id}.{$ext}",
            'public'
        );

        $consig->update(['imagen_path' => $path]);

        return response()->json([
            'ok'  => true,
            'url' => \Storage::url($path),
        ]);
    }

    /**
     * GET /admin/facturacion/consignacion/{id}/imagen
     * Redirige a la URL pública de la imagen de soporte.
     */
    public function verImagenConsignacion(int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        if (!$consig->imagen_path || !\Storage::disk('public')->exists($consig->imagen_path)) {
            abort(404, 'Imagen no encontrada.');
        }

        return redirect(\Storage::url($consig->imagen_path));
    }

    // ─── Facturar Otro Ingreso (Trámite) ─────────────────────────────
    /**
     * POST /admin/facturacion/otro-ingreso
     * Crea una factura de tipo 'otro_ingreso' (trámites, servicios adicionales).
     * NO genera plano PILA. IVA aplica si cliente OR empresa tiene iva='SI'.
     * Asesor se toma del campo asesor del cliente o de la empresa.
     */
    public function facturarOtroIngreso(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'cedula'              => 'required|integer',
            'descripcion_tramite' => 'required|string|max:300',
            'mes'                 => 'required|integer|min:1|max:12',
            'anio'                => 'required|integer|min:2000|max:2100',
            'valor_admon'         => 'required|numeric|min:0',
            'valor_asesor'        => 'nullable|numeric|min:0',
            'forma_pago'          => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'              => 'required|in:pre_factura,pagada,prestamo',
            'valor_efectivo'      => 'nullable|numeric|min:0',
            'valor_prestamo'      => 'nullable|numeric|min:0',
            'consignaciones'                   => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'empresa_id'          => 'nullable|integer',
            'observacion'         => 'nullable|string|max:500',
        ]);

        $cedula  = (int)$validated['cedula'];
        $mes     = (int)$validated['mes'];
        $anio    = (int)$validated['anio'];

        // ── IVA: aplica si cliente OR empresa tienen iva='SI' ─────────
        $clienteIva  = DB::table('clientes')->where('cedula', $cedula)->value('iva');
        $empresaId   = $validated['empresa_id'] ?? null;
        $empresaIva  = $empresaId
            ? DB::table('empresas')->where('id', $empresaId)->value('iva')
            : null;

        $aplicaIva = strtoupper(trim($clienteIva ?? '')) === 'SI'
                  || strtoupper(trim($empresaIva ?? '')) === 'SI';

        $valorAdmon  = (int)($validated['valor_admon']  ?? 0);
        $valorAsesor = (int)($validated['valor_asesor'] ?? 0);
        $ivaBase     = $valorAdmon + $valorAsesor;

        $iva = 0;
        if ($aplicaIva && $ivaBase > 0) {
            $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
            $iva    = (int) ceil($ivaBase * $cfgIva / 100 / 100) * 100;
        }

        $total = $valorAdmon + $valorAsesor + $iva;

        // ── Pagos ──────────────────────────────────────────────────────
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalConsig  = array_sum(array_column($consignacionesData, 'valor'));
        $totalEfectivo = (int)($validated['valor_efectivo'] ?? 0);
        $totalPrestamo = (int)($validated['valor_prestamo'] ?? 0);

        // ── Saldo previo del cliente ────────────────────────────────────
        $saldo = Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio);

        $factura = DB::transaction(function () use (
            $aliadoId, $cedula, $mes, $anio, $validated,
            $valorAdmon, $valorAsesor, $iva, $total,
            $totalConsig, $totalEfectivo, $totalPrestamo,
            $consignacionesData, $saldo, $empresaId
        ) {
            $numeroFactura = Factura::siguienteNumero($aliadoId);

            $factura = Factura::create([
                'aliado_id'           => $aliadoId,
                'numero_factura'      => $numeroFactura,
                'tipo'                => Factura::TIPO_OTRO_INGRESO,
                'cedula'              => $cedula,
                'contrato_id'         => null,
                'empresa_id'          => $empresaId,
                'mes'                 => $mes,
                'anio'                => $anio,
                'fecha_pago'          => now()->toDateString(),
                'estado'              => $validated['estado'],
                'es_prestamo'         => $validated['estado'] === 'prestamo',
                'forma_pago'          => $validated['forma_pago'],
                'valor_consignado'    => (int)$totalConsig,
                'valor_efectivo'      => $totalEfectivo,
                'valor_prestamo'      => $totalPrestamo,
                // SS = 0 (no es planilla)
                'dias_cotizados'      => 0,
                'v_eps'               => 0,
                'v_arl'               => 0,
                'v_afp'               => 0,
                'v_caja'              => 0,
                'total_ss'            => 0,
                // Valores administrativos
                'admon'               => $valorAdmon,
                'admin_asesor'        => 0,     // planilla: no aplica
                'admon_asesor_oi'     => $valorAsesor,
                'iva'                 => $iva,
                'total'               => max(0, $total),
                'seguro'              => 0,
                'afiliacion'          => 0,
                'mensajeria'          => 0,
                'otros'               => 0,
                // Descripción del trámite
                'descripcion_tramite' => $validated['descripcion_tramite'],
                'observacion'         => $validated['observacion'] ?? null,
                'usuario_id'          => Auth::id(),
            ]);

            // ── saldo_proximo ──────────────────────────────────────────
            if ($factura->es_prestamo) {
                $saldoProximo = -(int)$factura->total;
            } else {
                $pagadoReal   = (int)$factura->valor_consignado + (int)$factura->valor_efectivo;
                $saldoProximo = $pagadoReal - (int)$factura->total;
            }
            $factura->update(['saldo_proximo' => $saldoProximo]);

            // ── Consignaciones ─────────────────────────────────────────
            foreach ($consignacionesData as $cs) {
                $valorCs = (int)$cs['valor'];
                if ($valorCs <= 0) continue;
                \App\Models\Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'factura_id'      => $factura->id,
                    'banco_cuenta_id' => (int)$cs['banco_cuenta_id'],
                    'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                    'valor'           => $valorCs,
                    'referencia'      => $cs['referencia'] ?? null,
                    'confirmado'      => false,
                    'usuario_id'      => Auth::id(),
                ]);
            }

            // ── NO se genera plano PILA ────────────────────────────────
            return $factura;
        });

        return response()->json([
            'ok'              => true,
            'mensaje'         => 'Otro ingreso registrado correctamente. Recibo #' . $factura->numero_factura,
            'factura_id'      => $factura->id,
            'recibo_url'      => route('admin.facturacion.recibo', $factura->id),
            'consignacion_ids' => \App\Models\Consignacion::where('factura_id', $factura->id)
                ->orderBy('id')->pluck('id')->values()->all(),
        ]);
    }
    // ─── Historial de facturación de una empresa ────────────────────
    public function historialEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $facturas = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresaId)
            ->with(['usuario', 'consignaciones.bancoCuenta'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        // Agrupar por numero_factura (cada NP puede tener varios trabajadores con el mismo número)
        $grupos = $facturas->groupBy('numero_factura')->map(function ($grupo) {
            $primera = $grupo->first();

            // Saldo generado por este NP → positivo = sobró (va al siguiente mes),
            // negativo = consumió saldo previo, cero = equilibrado.
            $saldoProximoTotal = $grupo->sum(fn($f) => (int)($f->saldo_proximo ?? 0));

            return (object)[
                'id'                  => $primera->id,
                'np'                  => $primera->np,
                'tipo'                => $primera->tipo,
                'numero_factura'      => $primera->numero_factura,
                'fecha_pago'          => $primera->fecha_pago,
                'mes'                 => $primera->mes,
                'anio'                => $primera->anio,
                'estado'              => $primera->estado,
                'descripcion_tramite' => $primera->descripcion_tramite,
                'total'               => $grupo->sum(fn($f) => (int)$f->total),
                'cantidad'            => $grupo->count(),
                'usuario'             => $primera->usuario,
                // ── Saldo ──────────────────────────────────────────────────
                // saldo_proximo > 0 → generó anticipo para el siguiente mes
                // saldo_proximo < 0 → consumió saldo que venía de meses anteriores
                // saldo_proximo = 0 → equilibrado
                'saldo_proximo'       => $saldoProximoTotal,
                'saldo_a_favor_aplicado' => 0, // columna eliminada — ya no disponible
            ];
        })->values();

        return view('admin.facturacion.historial_empresa', compact('empresa', 'grupos', 'facturas'));
    }

    // ─── Editar empresa ──────────────────────────────────────────────
    public function editEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.facturacion.empresa_edit', compact('empresa', 'asesores'));
    }

    // ─── Actualizar empresa ──────────────────────────────────────────
    public function updateEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $validated = $request->validate([
            'empresa'    => 'required|string|max:255',
            'nit'        => 'nullable|numeric',
            'contacto'   => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:50',
            'celular'    => 'nullable|string|max:50',
            'correo'     => 'nullable|email|max:150',
            'direccion'  => 'nullable|string|max:255',
            'iva'        => 'nullable|string|max:20',
            'asesor_id'  => 'nullable|exists:asesores,id',
            'observacion'=> 'nullable|string|max:500',
        ]);

        $empresa->update($validated);

        return redirect()
            ->route('admin.facturacion.empresa', [
                'id'  => $empresaId,
                'mes' => now()->month,
                'anio'=> now()->year,
            ])
            ->with('success', 'Empresa actualizada correctamente.');
    }

    // ─── Cuenta de Cobro ─────────────────────────────────────────────
    public function cuentaCobroPreview(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $aliado   = \App\Models\Aliado::find($aliadoId);

        $contratoIds = $request->input('contratos', []);
        $mes         = (int) $request->input('mes',  now()->month);
        $anio        = (int) $request->input('anio', now()->year);
        $empresaId   = (int) $request->input('empresa_id');
        $tipo        = $request->input('tipo', 'simple'); // simple | detallada

        $empresa = Empresa::where('aliado_id', $aliadoId)->find($empresaId);

        // Cuentas bancarias marcadas para cobro
        $cuentasCobro = BancoCuenta::paraCobro($aliadoId);

        // Contratos seleccionados con sus relaciones
        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('id', $contratoIds)
            ->with(['cliente', 'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja'])
            ->get();

        // Facturas existentes para el período — indexadas por contrato_id
        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->whereNotNull('contrato_id')
            ->get()
            ->keyBy('contrato_id');  // ← por contrato, no por cédula

        $r100 = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100);

        $items = $contratos->map(function ($c) use ($mes, $anio, $facturasExistentes, $r100, $aliadoId) {
            $fact   = $facturasExistentes->get($c->id);  // ← busca por contrato->id
            $nombre = $c->cliente?->nombre_completo
                      ?? trim(($c->cliente?->primer_nombre ?? '') . ' ' .
                              ($c->cliente?->segundo_nombre ?? '') . ' ' .
                              ($c->cliente?->primer_apellido ?? '') . ' ' .
                              ($c->cliente?->segundo_apellido ?? ''))
                      ?: '—';

            $dias = $this->calcularDias($c, $mes, $anio);

            // ¿Es afiliación?
            $esAfil = $c->fecha_ingreso
                && (int)$c->fecha_ingreso->month === $mes
                && (int)$c->fecha_ingreso->year  === $anio;
            if ($fact) $esAfil = $fact->tipo === 'afiliacion';

            if ($fact) {
                $vEps  = $r100($fact->v_eps);
                $vArl  = $r100($fact->v_arl);
                $vAFP  = $r100($fact->v_afp);
                $vCaja = $r100($fact->v_caja);
                $vAdm  = (int)($fact->admon + $fact->admin_asesor);
                $vIva  = $r100($fact->iva);
                $vTot  = (int)$fact->total;
                $estado = $fact->estado;
            } elseif ($esAfil) {
                $vEps=$vArl=$vAFP=$vCaja=$vAdm=$vIva=0;
                $vTot = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                $estado = 'sin_factura';
            } else {
                $cotiz = $c->calcularCotizacion($dias);
                $vEps  = $r100($cotiz['eps']  ?? 0);
                $vArl  = $r100($cotiz['arl']  ?? 0);
                $vAFP  = $r100($cotiz['pen']  ?? 0);
                $vCaja = $r100($cotiz['caja'] ?? 0);
                $vAdm  = (int)(($c->administracion ?? 0) + ($c->admon_asesor ?? 0));
                $vIva  = $r100($cotiz['iva']  ?? 0);
                $vTot  = $vEps + $vArl + $vAFP + $vCaja + $vAdm + $vIva;
                $estado = 'sin_factura';
            }

            $saldo = Factura::saldoClienteMesPrevio($aliadoId, $c->cedula, $mes, $anio);

            return (object)[
                'cedula'         => $c->cedula,
                'nombre'         => $nombre,
                'fecha_ingreso'  => $c->fecha_ingreso,
                'razon_social'   => $c->razonSocial?->razon_social ?? '—',
                'eps_nombre'     => $c->eps?->nombre ?? '—',
                'arl_nombre'     => $c->arl?->nombre ?? '—',
                'n_arl'          => $c->n_arl ?? 1,
                'afp_nombre'     => $c->pension?->nombre ?? '—',
                'caja_nombre'    => $c->caja?->nombre ?? '—',
                'es_afil'        => $esAfil,
                'dias'           => $dias,
                'v_eps'          => $vEps,
                'v_arl'          => $vArl,
                'v_afp'          => $vAFP,
                'v_caja'         => $vCaja,
                'v_admon'        => $vAdm,
                'v_iva'          => $vIva,
                'v_total'        => $vTot,
                'estado'         => $estado,
                // saldo_proximo: neto acumulado hasta este período por contrato
                'saldo_proximo'  => (int) Factura::saldoClienteMesPrevio(
                    $aliadoId, $c->cedula, $mes, $anio, $c->id
                )['a_favor'] - (int) Factura::saldoClienteMesPrevio(
                    $aliadoId, $c->cedula, $mes, $anio, $c->id
                )['pendiente'],
            ];
        });

        $totalGeneral   = $items->sum('v_total');
        // Saldo neto de la empresa derivado de saldo_proximo acumulado
        $saldoNetoEmpresaCC = (int) Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresa->id)
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->whereNotNull('saldo_proximo')
            ->whereNull('deleted_at')
            ->sum('saldo_proximo');
        $totalFavor     = $saldoNetoEmpresaCC > 0 ? $saldoNetoEmpresaCC : 0;
        $totalPendiente = $saldoNetoEmpresaCC < 0 ? abs($saldoNetoEmpresaCC) : 0;

        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $vista = $tipo === 'detallada'
            ? 'admin.facturacion.cuenta_cobro_detallada'
            : 'admin.facturacion.cuenta_cobro_simple';

        return view($vista, compact(
            'aliado','empresa','items','cuentasCobro',
            'mes','anio','meses','totalGeneral','totalFavor','totalPendiente'
        ));
    }
}



