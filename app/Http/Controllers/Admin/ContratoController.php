<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Cliente;
use App\Models\RazonSocial;
use App\Models\Asesor;
use App\Models\Eps;
use App\Models\Pension;
use App\Models\Arl;
use App\Models\ArlTarifa;
use App\Models\Caja;
use App\Models\TipoModalidad;
use App\Models\PlanContrato;
use App\Models\ActividadEconomica;
use App\Models\MotivoAfiliacion;
use App\Models\MotivoRetiro;
use App\Models\Radicado;
use App\Models\ConfiguracionBrynex;
use App\Models\BancoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\MoraClienteService;

class ContratoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin|usuario']);
    }

    // ─── Listado de contratos del aliado activo ───────────────────────
    public function index(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $estado  = $request->get('estado', 'vigente');
        $buscar  = $request->get('q');

        $query = Contrato::where('contratos.aliado_id', $alidoId)
            ->when($estado !== 'todos', fn($q) => $q->where('estado', $estado))
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($inner) use ($buscar) {
                    $inner->where('cedula', 'like', "%{$buscar}%")
                          ->orWhereHas('cliente', fn($c) => $c->where('primer_nombre', 'like', "%{$buscar}%")
                                ->orWhere('primer_apellido', 'like', "%{$buscar}%"));
                });
            })
            ->with(['cliente', 'razonSocial', 'plan', 'tipoModalidad', 'asesor'])
            ->orderByDesc('id');

        $contratos = $query->paginate(25)->withQueryString();

        return view('admin.contratos.index', compact('contratos', 'estado', 'buscar'));
    }

    // ─── Formulario crear ─────────────────────────────────────────────
    public function create(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $cedula  = $request->get('cedula');
        $cliente = $cedula ? Cliente::where('cedula', $cedula)
            ->where('aliado_id', $alidoId)->first() : null;

        return view('admin.contratos.form', array_merge(
            $this->datosFormulario($alidoId, $cliente, null, null),
            ['contrato' => new Contrato(), 'cliente' => $cliente]
        ));
    }

    // ─── Guardar nuevo contrato ───────────────────────────────────────
    public function store(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $data = $this->validar($request);
        $data['aliado_id']       = $alidoId;
        $data['estado']          = 'vigente';
        $data['encargado_id']    = $data['encargado_id'] ?? Auth::id();
        $data['fecha_created']   = now();

        // IBC = salario si no se indica diferente
        if (empty($data['ibc'])) {
            $data['ibc'] = $data['salario'];
        }

        // Auto-derivar nit cotizante ARL si no vino explícito del formulario
        if (empty($data['arl_nit_cotizante'])) {
            if (($data['arl_modo'] ?? null) === 'razon_social' && !empty($data['razon_social_id'])) {
                $data['arl_nit_cotizante'] = (int) $data['razon_social_id']; // PK = NIT
            } elseif (($data['arl_modo'] ?? null) === 'independiente' && !empty($data['cedula'])) {
                $data['arl_nit_cotizante'] = (int) $data['cedula'];
            }
        }

        DB::transaction(function () use ($data, &$nuevoContrato) {
            $nuevoContrato = Contrato::create($data);
            // Generar radicados pendientes según el plan
            $nuevoContrato->load('plan');
            $nuevoContrato->crearRadicadosPendientes();
        });

        // Si la RS es independiente y viene operador_planilla_id, guardarlo en el cliente
        $operadorId = $data['operador_planilla_id'] ?? null;
        if ($operadorId) {
            $cedStore = $nuevoContrato->cedula ?? ($data['cedula'] ?? null);
            if ($cedStore) {
                $rsIdStore = $nuevoContrato->razon_social_id ?? ($data['razon_social_id'] ?? null);
                $esIndepRS = $rsIdStore && DB::table('razones_sociales')
                    ->where('id', $rsIdStore)->value('es_independiente');
                if ($esIndepRS) {
                    Cliente::where('cedula', $cedStore)
                        ->where('aliado_id', $alidoId)
                        ->update(['operador_planilla_id' => $operadorId]);
                }
            }
        }

        // Redirigir al cliente del contrato creado
        $cedula  = $nuevoContrato->cedula ?? ($data['cedula'] ?? null);
        $cliente = $cedula ? \App\Models\Cliente::where('cedula', $cedula)
            ->where('aliado_id', $alidoId)->first() : null;
        if ($cliente) {
            return redirect()->route('admin.clientes.edit', $cliente->id)
                ->with('success', 'Contrato creado correctamente. Se generaron los radicados pendientes.');
        }
        return redirect()->route('admin.contratos.index')
            ->with('success', 'Contrato creado correctamente.');
    }

    // ─── Formulario editar ────────────────────────────────────────────
    public function edit(int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)->with(['cliente','radicados.user','plan'])->findOrFail($id);
        $cliente  = $contrato->cliente;

        // URL de retorno: viene como ?back=... o se toma del referrer
        $backUrl = request('back') ?: url()->previous();

        // ── Radicados indexados por tipo (eps, arl, caja, pension) ──────
        $radicadosPorTipo = $contrato->radicados->keyBy('tipo');

        // ── ¿La RS está bloqueada por afiliaciones activas? ─────────────
        // Si algún radicado está en tramite u ok, no se puede cambiar la RS
        $estadosBloqueantes = ['tramite', 'ok'];
        $rsBloquedaPorAfiliacion = $contrato->radicados
            ->whereIn('estado', $estadosBloqueantes)
            ->isNotEmpty();

        // ── Otros contratos vigentes del mismo cliente (para modal multi-contrato) ──
        // Se excluye el contrato actual. Solo se muestran vigentes (no activo, no retirado).
        $otrosContratosVigentes = Contrato::where('aliado_id', $alidoId)
            ->where('cedula', $contrato->cedula)
            ->where('estado', 'vigente')
            ->where('id', '!=', $id)
            ->with('razonSocial')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'razon_social' => $c->razonSocial?->razon_social ?? 'Sin RS',
            ]);

        return view('admin.contratos.form', array_merge(
            $this->datosFormulario($alidoId, $cliente, $contrato->razon_social_id, $contrato->id),
            compact('contrato', 'cliente', 'backUrl', 'radicadosPorTipo', 'rsBloquedaPorAfiliacion', 'otrosContratosVigentes')
        ));
    }

    // ─── Actualizar contrato ──────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)->with('radicados')->findOrFail($id);
        $data     = $this->validar($request, $contrato);

        // ── Protección RS por afiliaciones activas (tramite u ok) ──────
        // Si la RS ya tiene afiliaciones en proceso o confirmadas, NO se puede cambiar.
        // La única vía para desligar es marcar retiro del contrato.
        $estadosBloqueantes = ['tramite', 'ok'];
        $rsBloquedaPorAfiliacion = $contrato->radicados
            ->whereIn('estado', $estadosBloqueantes)
            ->isNotEmpty();

        if ($rsBloquedaPorAfiliacion &&
            isset($data['razon_social_id']) &&
            (int)$data['razon_social_id'] !== (int)$contrato->razon_social_id) {
            return redirect()
                ->route('admin.contratos.edit', array_filter([
                    $id,
                    'back'   => $request->input('back_url'),
                    'iframe' => $request->input('iframe') ? '1' : null,
                ]))
                ->withErrors(['razon_social_id' => 'No se puede cambiar la Razón Social: ya existe una afiliación en trámite u OK. Para cambiarla, marque retiro del contrato.']);
        }

        // Si hay afiliaciones activas, preservar también modalidad, plan y fecha_ingreso
        if ($rsBloquedaPorAfiliacion) {
            $data['tipo_modalidad_id'] = $contrato->tipo_modalidad_id;
            $data['plan_id']           = $contrato->plan_id;
            $data['fecha_ingreso']     = $contrato->fecha_ingreso;
        }

        // Protección razón social: solo admin puede cambiarla si está bloqueada
        if ($contrato->razon_social_bloqueada &&
            Auth::user()->hasAnyRole(['usuario']) &&
            isset($data['razon_social_id']) &&
            (int)$data['razon_social_id'] !== (int)$contrato->razon_social_id) {
            unset($data['razon_social_id']);
        }

        // Al first save de razon_social → bloquearla
        if (!$contrato->razon_social_bloqueada && !empty($data['razon_social_id'])) {
            $data['razon_social_bloqueada'] = true;
        }

        // Auto-derivar nit cotizante ARL si no vino explícito del formulario
        if (empty($data['arl_nit_cotizante'])) {
            $rsId   = $data['razon_social_id']   ?? $contrato->razon_social_id;
            $cedula = $data['cedula']             ?? $contrato->cedula;
            $modo   = $data['arl_modo']           ?? $contrato->arl_modo;
            if ($modo === 'razon_social' && !empty($rsId)) {
                $data['arl_nit_cotizante'] = (int) $rsId;
            } elseif ($modo === 'independiente' && !empty($cedula)) {
                $data['arl_nit_cotizante'] = (int) $cedula;
            }
        }

        // Proteger plan_id: si llega vacío, conservar el plan original del contrato
        if (empty($data['plan_id']) && $contrato->plan_id) {
            $data['plan_id'] = $contrato->plan_id;
        }

        // Limpiar entidades que no aplican según el plan seleccionado
        // (evita que queden eps_id/pension_id/arl_id/caja_id con valores cuando el plan no los cubre)
        $planId = $data['plan_id'] ?? $contrato->plan_id;
        if ($planId) {
            $plan = \App\Models\PlanContrato::find($planId);
            if ($plan) {
                if (!$plan->incluye_eps)     $data['eps_id']     = null;
                if (!$plan->incluye_pension) $data['pension_id'] = null;
                if (!$plan->incluye_arl)     $data['arl_id']     = null;
                if (!$plan->incluye_caja)    $data['caja_id']    = null;
            }
        }

        DB::transaction(function () use ($contrato, $data) {
            $oldPlanId = $contrato->plan_id;
            $contrato->update($data);

            // Si cambio el plan, agregar nuevos radicados pendientes
            if (isset($data['plan_id']) && $data['plan_id'] != $oldPlanId) {
                $contrato->load('plan');
                $contrato->crearRadicadosPendientes();
            }
        });

        // Si la RS es independiente y viene operador_planilla_id, guardarlo en el cliente
        $operadorIdUpd = $request->input('operador_planilla_id');
        if ($operadorIdUpd !== null) {
            $rsIdUpd = $data['razon_social_id'] ?? $contrato->razon_social_id;
            $esIndepRSUpd = $rsIdUpd && DB::table('razones_sociales')
                ->where('id', $rsIdUpd)->value('es_independiente');
            if ($esIndepRSUpd) {
                $cedUpd = $data['cedula'] ?? $contrato->cedula;
                if ($cedUpd) {
                    Cliente::where('cedula', $cedUpd)
                        ->where('aliado_id', $alidoId)
                        ->update(['operador_planilla_id' => $operadorIdUpd ?: null]);
                }
            }
        }

        $redirectParams = [$id, 'back' => $request->input('back_url')];
        if ($request->input('iframe')) {
            $redirectParams['iframe'] = '1';
        }

        return redirect()
            ->route('admin.contratos.edit', $redirectParams)
            ->with('success', 'Contrato actualizado correctamente.');
    }

    // ─── Retirar contrato ─────────────────────────────────────────────
    public function retirar(Request $request, int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)
            ->with(['eps','arl','pension','caja','tipoModalidad','razonSocial','cliente','plan'])
            ->findOrFail($id);

        $validated = $request->validate([
            'motivo_retiro_id' => 'required|exists:motivos_retiro,id',
            'fecha_retiro'     => 'required|date',
            'tipo_retiro'      => 'required|in:real,informativo',
            'num_dias'         => 'nullable|integer|min:0|max:30',
            'mes_plano'        => 'required|integer|between:1,12',
            'anio_plano'       => 'required|integer|min:2020|max:2099',
            'observacion'      => 'nullable|string|max:500',
        ]);

        $tipoRetiro  = $validated['tipo_retiro'];
        $fechaRetiro = $validated['fecha_retiro'];
        $numDias = $tipoRetiro === 'real'
            ? max(1, min(30, (int)($validated['num_dias'] ?? 1)))
            : 0;

        // Validar que mes_plano no sea anterior al mes de ingreso
        if ($contrato->fecha_ingreso) {
            $ingreso = \Carbon\Carbon::parse($contrato->fecha_ingreso);
            $planoPeriodo = \Carbon\Carbon::createFromDate($validated['anio_plano'], $validated['mes_plano'], 1);
            if ($planoPeriodo->lt($ingreso->startOfMonth())) {
                return redirect()
                    ->route('admin.contratos.edit', [$id, 'back' => $request->input('back_url')])
                    ->withErrors(['mes_plano' => 'El mes del plano no puede ser anterior al mes de ingreso del contrato.']);
            }
        }

        // ── Calcular SS del retiro real usando calcularCotizacion() del modelo ─
        // Delegar al modelo centraliza TODAS las reglas del plan:
        //   • Cargo sin-CCF $100 para modalidades 0/12 sin caja
        //   • Tiempo Parcial (IBC por entidad, factores por días)
        //   • Prorrateo por num_dias
        //   • Flags incluye_eps / incluye_arl / incluye_pension / incluye_caja
        //   • Fallback plan→IDs del contrato (contratos legacy sin plan_id)
        // Solo aplica para retiro real con días cotizados.
        $vEpsRetiro = 0; $vArlRetiro = 0; $vAfpRetiro = 0; $vCajaRetiro = 0; $totalSsRetiro = 0;

        if ($tipoRetiro === 'real' && $numDias > 0) {
            // ── Fallback IBC: contratos legacy con ibc/salario = 0 → usar SM ──
            // calcularCotizacion() no tiene este guard; se inyecta temporalmente.
            $ibcOriginal = (float)($contrato->ibc ?? 0);
            $salOriginal = (float)($contrato->salario ?? 0);
            if ($ibcOriginal <= 0 && $salOriginal <= 0) {
                $sm = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);
                $contrato->ibc     = $sm;
                $contrato->salario = $sm;
            }

            // Una sola llamada — misma fuente de verdad que la facturación normal
            $cotizacion  = $contrato->calcularCotizacion($numDias);
            $vEpsRetiro  = (int)($cotizacion['eps']  ?? 0);
            $vArlRetiro  = (int)($cotizacion['arl']  ?? 0);
            $vAfpRetiro  = (int)($cotizacion['pen']  ?? 0);
            $vCajaRetiro = (int)($cotizacion['caja'] ?? 0);
            $totalSsRetiro = $vEpsRetiro + $vArlRetiro + $vAfpRetiro + $vCajaRetiro;

            // Restaurar valores originales (evita mutar el objeto si se reutiliza)
            $contrato->ibc     = $ibcOriginal;
            $contrato->salario = $salOriginal;
        }


        // ── Mora real del retiro (sin tramos mínimos) ─────────────────────────
        // En retiro NO se cobra al cliente el mínimo: solo el interés real calculado.
        // Se guarda en facturas.mora para conciliación. El aliado la recibe como 'otros'.
        $moraRetiro = 0;
        try {
            $rsRetiro   = $contrato->razonSocial;
            $rsNitRet   = $rsRetiro ? (int)($rsRetiro->nit ?: $rsRetiro->id) : 0;
            $rsDiaHRet  = $rsRetiro ? ($rsRetiro->dia_habil ?? null) : null;
            $mesRet     = (int)($validated['mes_plano']  ?? now()->month);
            $anioRet    = (int)($validated['anio_plano'] ?? now()->year);
            if ($rsNitRet && $totalSsRetiro > 0) {
                $moraInfo   = MoraClienteService::calcular($alidoId, $rsNitRet, $rsDiaHRet, $totalSsRetiro, $mesRet, $anioRet);
                $moraRetiro = (int) round($moraInfo['mora_real'] ?? 0); // solo el interés real
            }
        } catch (\Throwable) {}

        DB::transaction(function () use ($contrato, $validated, $alidoId, $tipoRetiro, $fechaRetiro, $numDias,
                                         $vEpsRetiro, $vArlRetiro, $vAfpRetiro, $vCajaRetiro, $totalSsRetiro, $moraRetiro) {
            // 1) Actualizar contrato → retirado
            $contrato->update([
                'estado'           => 'retirado',
                'motivo_retiro_id' => $validated['motivo_retiro_id'],
                'fecha_retiro'     => $fechaRetiro,
                'observacion'      => $validated['observacion'] ?? $contrato->observacion,
            ]);

            // 2) Crear factura de retiro (numero_factura=0, total=$0, pero SS calculado)
            //    El total sigue en $0 porque el dinero no entró como ingreso.
            //    Los campos v_eps/v_arl/v_afp/v_caja reflejan el COSTO del retiro en SS.
            //    Se excluyen de ingresos en informes filtrando WHERE numero_factura = 0.
            $factura = \App\Models\Factura::create([
                'aliado_id'        => $alidoId,
                'numero_factura'   => 0,
                'tipo'             => 'planilla',
                'cedula'           => $contrato->cedula,
                'contrato_id'      => $contrato->id,
                'razon_social_id'  => $contrato->razon_social_id,
                'empresa_id'       => null,
                'mes'              => now()->month,
                'anio'             => now()->year,
                'fecha_pago'       => now()->toDateString(),
                'estado'           => 'pagada',
                'forma_pago'       => 'efectivo',
                'valor_efectivo'   => 0,
                'valor_consignado' => 0,
                'valor_prestamo'   => 0,
                'otros'            => $moraRetiro,  // mora real informativa para el aliado
                'otros_admon'      => 0,
                'mensajeria'       => 0,
                'dias_cotizados'   => $numDias,
                'v_eps'       => $vEpsRetiro,
                'v_arl'       => $vArlRetiro,
                'v_afp'       => $vAfpRetiro,
                'v_caja'      => $vCajaRetiro,
                'total_ss'    => $totalSsRetiro,
                'mora'        => $moraRetiro,  // campo dedicado mora (no es ingreso)
                'admon'       => 0,
                'admin_asesor'=> 0,
                'seguro'      => 0,
                'afiliacion'  => 0,
                'iva'         => 0,
                'total'       => 0,   // el cliente no paga
                'saldo_proximo'=> 0,
                'usuario_id'  => Auth::id(),
                'observacion' => $validated['observacion'] ?? null,
            ]);

            // 3) Mes/año del plano: viene del select del modal (controlado por el usuario)
            $mesPlan  = (int) $validated['mes_plano'];
            $anioPlan = (int) $validated['anio_plano'];

            // n_plano del plano de retiro = plano actual de la RS.
            // NOTA: El plano 100 es exclusivo del flujo "Duplicar Contrato" (IR rotation).
            // El retiro normal — incluso en IR (id=12) — usa el n_plano de la RS.
            $nPlano = $contrato->razon_social_id
                ? (\App\Models\RazonSocial::find($contrato->razon_social_id)?->n_plano ?? 1)
                : 1;

            // 4) Crear plano con fecha_ret y num_dias
            $cliente = $contrato->cliente;
            $eps     = $contrato->eps;
            $afp     = $contrato->pension;
            $arl     = $contrato->arl;
            $caja    = $contrato->caja;
            $rs      = $contrato->razonSocial;

            $codArl    = $rs?->arl_nit ?? $arl?->nit ?? $arl?->codigo_arl ?? null;
            $nombreArl = null;
            if ($rs?->arl_nit) {
                $nombreArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('nombre_arl');
            }
            if (!$nombreArl) $nombreArl = $arl?->nombre_arl ?? null;

            $apellidos = $cliente?->apellidos ?? trim(($cliente?->primer_apellido ?? '') . ' ' . ($cliente?->segundo_apellido ?? ''));
            $nombres   = $cliente?->nombres   ?? trim(($cliente?->primer_nombre   ?? '') . ' ' . ($cliente?->segundo_nombre   ?? ''));
            $partsApe  = preg_split('/\s+/', trim($apellidos), 2);
            $partsNom  = preg_split('/\s+/', trim($nombres),   2);

            \App\Models\Plano::create([
                'factura_id'        => $factura->id,
                'contrato_id'       => $contrato->id,
                'aliado_id'         => $alidoId,
                'numero_factura'    => 0,
                'tipo_reg'          => 'retiro',
                'tipo_doc'          => strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC',
                'no_identifi'       => $contrato->cedula,
                'primer_ape'        => strtoupper($partsApe[0] ?? ''),
                'segundo_ape'       => strtoupper($partsApe[1] ?? ''),
                'primer_nombre'     => strtoupper($partsNom[0] ?? ''),
                'segundo_nombre'    => strtoupper($partsNom[1] ?? ''),
                'fecha_ing'         => null,
                'fecha_ret'         => \Carbon\Carbon::parse($fechaRetiro)->toDateString(),
                'num_dias'          => $numDias,
                'cod_eps'           => $eps?->nit  ?? $eps?->cod_eps  ?? null,
                'nombre_eps'        => $eps?->nombre ?? null,
                'cod_afp'           => $afp?->nit  ?? $afp?->cod_afp  ?? null,
                'nombre_afp'        => $afp?->razon_social ?? null,
                'cod_arl'           => $codArl,
                'nombre_arl'        => $nombreArl,
                'cod_caja'          => $caja?->nit ?? $caja?->cod_caja ?? null,
                'nombre_caja'       => $caja?->nombre ?? null,
                'nivel_riesgo'      => $contrato->n_arl ?? 1,
                'salario_basico'    => $contrato->salario ?? 0,
                'n_plano'           => $nPlano,
                'mes_plano'         => $mesPlan,
                'anio_plano'        => $anioPlan,
                'razon_social'      => $rs?->razon_social ?? null,
                'razon_social_id'   => $contrato->razon_social_id,
                'tipo_p'            => $contrato->tipo_modalidad_id,
                'tipo_modalidad_id' => $contrato->tipo_modalidad_id,
                'usuario_id'        => Auth::id(),
            ]);
        });

        $retiroParams = [$id, 'back' => $request->input('back_url')];
        if ($request->input('iframe')) {
            $retiroParams['iframe'] = '1';
        }

        return redirect()
            ->route('admin.contratos.edit', $retiroParams)
            ->with('success', 'Contrato retirado correctamente.');
    }

    // ─── API: Cotizador (devuelve JSON) ───────────────────────────────
    public function cotizar(Request $request)
    {
        $alidoId       = session('aliado_id_activo');
        $tipoModalidad = TipoModalidad::find($request->get('tipo_modalidad_id'));
        $planId        = (int) $request->get('plan_id');
        $plan          = PlanContrato::find($planId);
        $nivelArl      = (int) $request->get('n_arl', 1);
        $salario       = (float) $request->get('salario', 0);
        $ibc           = (float) $request->get('ibc', $salario) ?: $salario; // nunca 0
        $admon         = (float) $request->get('administracion', 0);
        $admonAsesor   = (float) $request->get('admon_asesor', 0);
        $seguro        = (float) $request->get('seguro', 0);
        $dias          = max(1, min(30, (int) $request->get('dias', 30))); // entre 1 y 30
        $cedula        = $request->get('cedula');

        $esIndep = $tipoModalidad && $tipoModalidad->esIndependiente();
        $esTP    = $tipoModalidad && $tipoModalidad->esTiempoParcial();

        // Porcentajes
        $pctEps  = $esIndep ? ConfiguracionBrynex::pctSaludIndependiente()  : ConfiguracionBrynex::pctSaludDependiente();
        $pctPen  = $esIndep ? ConfiguracionBrynex::pctPensionIndependiente() : ConfiguracionBrynex::pctPensionDependiente();
        $pctArl  = ArlTarifa::porcentajePara($nivelArl, $alidoId);

        // Caja: empresa siempre 4%; independiente usa el valor enviado (2% o 0.6%)
        if ($esIndep) {
            $pctCajaReq = (float) $request->get('porcentaje_caja', 0);
            $pctCaja    = $pctCajaReq ?: ConfiguracionBrynex::pctCajaIndependienteAlto();
        } else {
            $pctCaja = ConfiguracionBrynex::pctCajaDependiente(); // empresa: siempre 4%
        }

        // Redondear HACIA ARRIBA al 100 mas cercano (ceil)
        $r = fn($v) => ceil($v / 100) * 100;

        if ($esTP) {
            // ── Tiempo Parcial: IBC diferente por entidad, sin EPS ─────────
            // ARL  = SM_completo × tasaArl  (cotiza mes completo, 30 días)
            // AFP  = SM × factor_afp × pctPen
            // CAJA = SM × factor_caja × pctCaja (factor_caja ≠ factor_afp en 7-14, 7-21, 14-21)
            $diasP      = $tipoModalidad->diasPorEntidad();
            $factorMap  = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
            $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
            $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

            // Salario mínimo desde ConfiguracionBrynex
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
            // Aplica cuando el plan NO incluye CCF y la modalidad es de ese tipo.
            // Se cobra $100 fijos, igual que si cotizara caja normalmente.
            $tipoModalidadIdInt = (int) $request->get('tipo_modalidad_id', -99);
            if ($cajaMes === 0 && in_array($tipoModalidadIdInt, \App\Models\Contrato::IDS_SIN_CCF)
                && $plan && !$plan->incluye_caja) {
                $cajaMes = \App\Models\Contrato::CARGO_SIN_CCF;
            }

            // Prorratear por dias cotizados (dias/30); admon y seguro siempre completos.
            // EPS: ceil al centena superior (mismo comportamiento que calcularCotizacion del modelo).
            // ARL/AFP/CAJA: round al centena más cercano (evita doble-ceil que infla valores pequeños,
            //   e.g. ARL 9200/30=306.67 → 400 con ceil, pero debe quedar en 300).
            $rRound = fn($v) => (int)(round($v / 100) * 100);
            $eps  = $dias < 30 ? $r($epsMes       * $dias / 30) : $epsMes;
            $arl  = $dias < 30 ? $rRound($arlMes  * $dias / 30) : $arlMes;
            $pen  = $dias < 30 ? $rRound($penMes  * $dias / 30) : $penMes;
            // Cargo sin-CCF es fijo: NO se prorratea por días
            $caja = ($cajaMes === \App\Models\Contrato::CARGO_SIN_CCF)
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
            $iva = DB::table('clientes')->where('cedula', (int)$cedula)->value('iva');
            $tieneIva = strtoupper(trim($iva ?? '')) === 'SI';
        }
        $pctIva = $tieneIva ? ConfiguracionBrynex::porcentajeIva() : 0;
        $iva    = $tieneIva ? $r($admonTotal * $pctIva / 100) : 0;
        $total  = $ss + $seguro + $admonTotal + $iva;

        $ibcSugerido = $esIndep ? $r($salario * ConfiguracionBrynex::pctIbcIndependienteSugerido() / 100) : null;

        return response()->json([
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
        ]);
    }

    // ─── API: Cargar tarifas del aliado por plan ──────────────────────
    public function tarifasPorPlan(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $planId  = (int) $request->get('plan_id');
        $tarifas = Contrato::tarifasParaAliado($alidoId, $planId);
        return response()->json($tarifas);
    }

    // ─── Actualizar estado de radicado (AJAX) ─────────────────────────
    public function actualizarRadicado(Request $request, int $radicadoId)
    {
        $alidoId  = session('aliado_id_activo');
        $radicado = Radicado::where('aliado_id', $alidoId)->findOrFail($radicadoId);

        $data = $request->validate([
            'estado'               => 'sometimes|in:pendiente,en_tramite,confirmado,rechazado',
            'numero_radicado'      => 'sometimes|nullable|string|max:80',
            'canal_envio'          => 'sometimes|nullable|in:web,correo,asesor,presencial,otro',
            'enviado_al_cliente'   => 'sometimes|boolean',
            'canal_envio_cliente'  => 'sometimes|nullable|in:correo,whatsapp,fisica,otro',
            'observacion'          => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($data['estado'])) {
            if ($data['estado'] === 'en_tramite' && !$radicado->fecha_inicio_tramite) {
                $data['fecha_inicio_tramite'] = now();
            }
            if ($data['estado'] === 'confirmado' && !$radicado->fecha_confirmacion) {
                $data['fecha_confirmacion'] = now();
            }
        }

        if (isset($data['enviado_al_cliente']) && $data['enviado_al_cliente'] && !$radicado->fecha_envio_cliente) {
            $data['fecha_envio_cliente'] = now();
        }

        $data['user_id'] = Auth::id();
        $radicado->update($data);

        return response()->json(['ok' => true, 'radicado' => $radicado->fresh()]);
    }

    // ─── Datos comunes del formulario ─────────────────────────────────
    private function datosFormulario(int $alidoId, ?object $cliente = null, ?int $razonSocialId = null, ?int $excludeContratoId = null): array
    {
        // ARL predeterminada de la razón social (por arl_nit)
        $arlIdRazonSocial = null;
        if ($razonSocialId) {
            $arlNit = DB::table('razones_sociales')->where('id', $razonSocialId)->value('arl_nit');
            if ($arlNit) {
                $arlIdRazonSocial = DB::table('arls')->where('nit', $arlNit)->value('id');
            }
        }

        // Modalidades que permiten cambiar ARL y muestran Modo ARL
        $modalidadesArlLibre = \App\Models\TipoModalidad::IDS_ARL_LIBRE;  // [10, 11, -1, 8]
        $modalidadesModoArl  = \App\Models\TipoModalidad::IDS_MODO_ARL;   // [10, 11, -1]

        // IDs de modalidades independientes (I Act=11, I Venc=10, En el Exterior=14, UPC=13)
        // NOTA: Las modalidades TP NO son "independientes" a efectos del filtro de RS;
        // se manejan por su propia lógica (es_tiempo_parcial=1 en la BD).
        $modalidadesIndependientes = [10, 11, 13, 14];

        // Mapa: tipo_modalidad_id => [plan_ids] — para filtrado dinámico en el JS
        $planesPermitidos = DB::table('modalidad_planes')
            ->get()
            ->groupBy('tipo_modalidad_id')
            ->map(fn($rows) => $rows->pluck('plan_id')->values())
            ->toArray();

        // ── RS ya ocupadas por contratos VIGENTES de este cliente ──────
        // Se excluye el contrato actual (en edición) para no bloquear su propia RS.
        $rsOcupadasIds = [];
        if ($cliente) {
            $query = Contrato::where('aliado_id', $alidoId)
                ->where('cedula', $cliente->cedula)
                ->where('estado', 'vigente')
                ->whereNotNull('razon_social_id');
            if ($excludeContratoId) {
                $query->where('id', '!=', $excludeContratoId);
            }
            $rsOcupadasIds = $query->pluck('razon_social_id')
                ->unique()->values()->toArray();
        }

        // ── Regla AFP obligatorio ───────────────────────────────────────
        // Modalidades donde AFP es obligatorio (a menos que el cliente esté exento):
        //   - Dependiente E (0), I Venc (10), I Act (11)
        //   - TODAS las modalidades con es_tiempo_parcial=1 (independiente del ID)
        //     → el plan "ARL+CCF" sin AFP (APTP) solo es válido para clientes exentos
        $idsTP = TipoModalidad::where('es_tiempo_parcial', true)->pluck('id')->toArray();
        $modalidadesAfpObligatorio = array_values(array_unique(array_merge([0, 10, 11], $idsTP)));

        return [
            // Razones sociales: activas primero (ordenadas por nombre), inactivas al final
            'razonesSociales'           => RazonSocial::where('aliado_id', $alidoId)
                                            ->orderByRaw("CASE WHEN estado = 'Activa' THEN 0 ELSE 1 END")
                                            ->orderBy('razon_social')
                                            ->get(),
            'asesores'                  => Asesor::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get(),
            'epsList'                   => Eps::orderBy('nombre')->get(),
            'pensiones'                 => Pension::orderBy('razon_social')->get(),
            'arlList'                   => Arl::orderBy('nombre_arl')->get(),
            'cajas'                     => $this->cajasOrdenadas($cliente),
            'tiposModalidad'            => TipoModalidad::activos()->get(),
            'planes'                    => PlanContrato::where('activo', true)->get(),
            'actividades'               => ActividadEconomica::where('activo', true)->orderBy('nombre')->get(),
            'motivosAfiliacion'         => MotivoAfiliacion::where('activo', true)->get(),
            'motivosRetiro'             => MotivoRetiro::where('activo', true)->get(),
            'usuarios'                  => \App\Models\User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get(),
            'salarioMinimo'             => ConfiguracionBrynex::salarioMinimo(),
            'pctIbcSugerido'            => ConfiguracionBrynex::pctIbcIndependienteSugerido(),
            // Defaults entidades
            'arlIdRazonSocial'          => $arlIdRazonSocial,
            'clienteEpsId'              => $cliente?->eps_id,
            'clientePensionId'          => $cliente?->pension_id,
            'modalidadesArlLibre'       => $modalidadesArlLibre,
            'modalidadesModoArl'        => $modalidadesModoArl,
            // Filtrado inteligente
            'planesPermitidos'          => $planesPermitidos,
            'modalidadesIndependientes' => $modalidadesIndependientes,
            'clienteExentoAfp'          => $this->detectarExencionAfp($cliente),
            'clienteTipoDoc'            => $cliente?->tipo_doc,
            'clienteEdad'               => $cliente?->edad,
            'clienteGenero'             => $cliente?->genero,
            // Regla AFP obligatorio
            'reglaAfpActiva'            => ConfiguracionBrynex::reglaAfpObligatorio(),
            'modalidadesAfpObligatorio' => $modalidadesAfpObligatorio,
            // Defaults de tarifas
            'defaultTarifas'            => Contrato::tarifasParaAliado($alidoId, null),
            'bancos'                    => BancoCuenta::activas($alidoId),
            // RS ya usadas (para deshabilitar en el select de creación)
            'rsOcupadasIds'             => $rsOcupadasIds,
            // Operador de planilla (todos los globales, para RS independiente)
            'operadoresPlanilla'        => DB::table('operadores_planilla')
                                            ->whereNull('aliado_id')
                                            ->orderBy('orden')
                                            ->orderBy('nombre')
                                            ->get(['id', 'nombre', 'codigo_ni']),
            // Valor actual del operador asignado al cliente
            'clienteOperadorId'         => $cliente?->operador_planilla_id,
        ];
    }

    // ─── Cajas ordenadas por departamento del cliente ─────────────────
    /**
     * Retorna las cajas de compensación ordenadas así:
     *   1. Las del departamento del cliente (según municipio_id → ciudades.departamento_id)
     *   2. El resto, alfabéticamente
     *
     * Agrega un atributo virtual 'es_local' para que la vista pueda destacarlas.
     */
    private function cajasOrdenadas(?object $cliente): \Illuminate\Support\Collection
    {
        // Obtener el departamento del cliente según su municipio_id
        $deptCliente = null;
        if ($cliente && $cliente->municipio_id) {
            $deptCliente = DB::table('ciudades')
                ->where('id', $cliente->municipio_id)
                ->value('departamento_id');
        }

        $cajas = Caja::orderBy('nombre')->get();

        if (!$deptCliente) {
            // Sin departamento conocido: orden alfabético normal
            return $cajas->each(fn($c) => $c->es_local = false);
        }

        // Separar cajas del departamento del cliente y el resto
        $locales  = $cajas->where('id_dept', $deptCliente)->values();
        $resto    = $cajas->where('id_dept', '!=', $deptCliente)
                          ->whereNotNull('id_dept')
                          ->merge($cajas->whereNull('id_dept'))
                          ->sortBy('nombre')
                          ->values();

        $locales->each(fn($c) => $c->es_local = true);
        $resto->each(fn($c)   => $c->es_local = false);

        return $locales->merge($resto);
    }

    // ─── Duplicar contrato Plan Ingreso-Retiro (id=12) ───────────────
    /**
     * Marca retiro en el contrato actual (n_plano=0, num_dias 1-3)
     * y crea un nuevo contrato con la siguiente RS disponible,
     * fecha_ingreso = 26 del mes actual y estado = vigente.
     */
    public function duplicarIngresoRetiro(Request $request, int $contrato)
    {
        $alidoId  = session('aliado_id_activo');
        $original = Contrato::where('aliado_id', $alidoId)
            ->with(['eps', 'arl', 'pension', 'caja', 'tipoModalidad', 'razonSocial', 'cliente', 'plan'])
            ->findOrFail($contrato);

        // Validar que sea plan Ingreso-Retiro vigente
        if ((int)$original->tipo_modalidad_id !== 12 || !$original->estaVigente()) {
            return response()->json(['error' => true, 'mensaje' => 'Este contrato no aplica para duplicación Ingreso-Retiro.'], 422);
        }

        $validated = $request->validate([
            'num_dias'         => 'required|integer|min:1|max:3',
            'motivo_retiro_id' => 'required|exists:motivos_retiro,id',
            'observacion'      => 'nullable|string|max:500',
            'nueva_rs_id'      => 'nullable|integer',
        ]);

        // Seleccionar nueva RS: usar la del usuario si vino y es válida, sino el algoritmo automático
        $nuevaRsId = null;
        if (!empty($validated['nueva_rs_id'])) {
            // Verificar que sea una RS válida (dependiente, activa, del aliado, distinta a la actual)
            $rsManual = DB::table('razones_sociales')
                ->where('id', $validated['nueva_rs_id'])
                ->where('aliado_id', $alidoId)
                ->where('es_independiente', false)
                ->where('estado', 'Activa')
                ->where('id', '!=', $original->razon_social_id)
                ->whereRaw("UPPER(razon_social) NOT LIKE '%RAZON SOCIAL%'")
                ->exists();
            if ($rsManual) {
                $nuevaRsId = (int)$validated['nueva_rs_id'];
            }
        }
        if (!$nuevaRsId) {
            $nuevaRsId = $this->seleccionarRsParaIR($alidoId, $original->cedula, (int)$original->razon_social_id);
        }
        if (!$nuevaRsId) {
            return response()->json(['error' => true, 'mensaje' => 'No se encontró una Razón Social disponible para asignar. Verifique que existan RS dependientes activas.'], 422);
        }


        $nuevoContrato = null;

        DB::transaction(function () use ($original, $validated, $alidoId, $nuevaRsId, &$nuevoContrato) {
            $numDias         = (int)$validated['num_dias'];
            $fechaIngreso    = \Carbon\Carbon::parse($original->fecha_ingreso);
            $mesAnterior     = now()->subMonth()->startOfMonth();

            // Si la afiliación fue exactamente el mes anterior → fecha_ingreso + (dias-1)
            // Si fue antes del mes anterior → día 1 del mes anterior
            if (
                $fechaIngreso->year  === $mesAnterior->year &&
                $fechaIngreso->month === $mesAnterior->month
            ) {
                $fechaRetiro = $fechaIngreso->copy()->addDays($numDias - 1)->toDateString();
            } else {
                $fechaRetiro = $mesAnterior->toDateString(); // 1ro del mes anterior
            }

            // ── 1. Marcar retiro en contrato original ─────────────────────
            $original->update([
                'estado'           => 'retirado',
                'motivo_retiro_id' => $validated['motivo_retiro_id'],
                'fecha_retiro'     => $fechaRetiro,
                'observacion'      => $validated['observacion'] ?? $original->observacion,
            ]);

            // ── 2. Crear plano de retiro con n_plano = 0 ─────────────────
            $cliente = $original->cliente;
            $eps     = $original->eps;
            $afp     = $original->pension;
            $arl     = $original->arl;
            $caja    = $original->caja;
            $rs      = $original->razonSocial;

            $codArl    = $rs?->arl_nit ?? $arl?->nit ?? $arl?->codigo_arl ?? null;
            $nombreArl = null;
            if ($rs?->arl_nit) {
                $nombreArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('nombre_arl');
            }
            if (!$nombreArl) $nombreArl = $arl?->nombre_arl ?? null;

            $apellidos = $cliente?->apellidos ?? trim(($cliente?->primer_apellido ?? '') . ' ' . ($cliente?->segundo_apellido ?? ''));
            $nombres   = $cliente?->nombres   ?? trim(($cliente?->primer_nombre   ?? '') . ' ' . ($cliente?->segundo_nombre   ?? ''));
            $partsApe  = preg_split('/\s+/', trim($apellidos), 2);
            $partsNom  = preg_split('/\s+/', trim($nombres),   2);

            // Calcular la cotización para el retiro real (aportes a seguridad social)
            $vEpsRetiro = 0; $vArlRetiro = 0; $vAfpRetiro = 0; $vCajaRetiro = 0; $totalSsRetiro = 0;
            if ($numDias > 0) {
                // Fallback IBC si ibc/salario son 0 en contratos legacy
                $ibcOriginal = (float)($original->ibc ?? 0);
                $salOriginal = (float)($original->salario ?? 0);
                if ($ibcOriginal <= 0 && $salOriginal <= 0) {
                    $sm = (float) \App\Models\ConfiguracionBrynex::obtener('salario_minimo', 1423500);
                    $original->ibc     = $sm;
                    $original->salario = $sm;
                }

                $cotizacion    = $original->calcularCotizacion($numDias);
                $vEpsRetiro    = (int)($cotizacion['eps']  ?? 0);
                $vArlRetiro    = (int)($cotizacion['arl']  ?? 0);
                $vAfpRetiro    = (int)($cotizacion['pen']  ?? 0);
                $vCajaRetiro   = (int)($cotizacion['caja'] ?? 0);
                $totalSsRetiro = $vEpsRetiro + $vArlRetiro + $vAfpRetiro + $vCajaRetiro;

                // Restaurar
                $original->ibc     = $ibcOriginal;
                $original->salario = $salOriginal;
            }

            // Crear factura de retiro (costo interno, no ingreso)
            $facRetiro = \App\Models\Factura::create([
                'aliado_id'        => $alidoId,
                'numero_factura'   => 0,
                'tipo'             => 'planilla',
                'cedula'           => $original->cedula,
                'contrato_id'      => $original->id,
                'razon_social_id'  => $original->razon_social_id,
                'empresa_id'       => null,
                'mes'              => now()->month,
                'anio'             => now()->year,
                'fecha_pago'       => now()->toDateString(),
                'estado'           => 'pagada',
                'forma_pago'       => 'efectivo',
                'valor_efectivo'   => 0,
                'valor_consignado' => 0,
                'valor_prestamo'   => 0,
                'otros'            => 0,
                'otros_admon'      => 0,
                'mensajeria'       => 0,
                'dias_cotizados'   => $numDias,
                'v_eps'            => $vEpsRetiro,
                'v_arl'            => $vArlRetiro,
                'v_afp'            => $vAfpRetiro,
                'v_caja'           => $vCajaRetiro,
                'total_ss'         => $totalSsRetiro,
                'admon'            => 0,
                'admin_asesor'     => 0,
                'seguro'           => 0,
                'afiliacion'       => 0,
                'iva'              => 0,
                'total'            => 0,
                'saldo_proximo'    => 0,
                'usuario_id'       => \Illuminate\Support\Facades\Auth::id(),
                'observacion'      => $validated['observacion'] ?? null,
            ]);

            // Parsear la fecha de retiro para obtener el mes y año de la cotización real
            $carbonRetiro = \Carbon\Carbon::parse($fechaRetiro);

            \App\Models\Plano::create([
                'factura_id'        => $facRetiro->id,
                'contrato_id'       => $original->id,
                'aliado_id'         => $alidoId,
                'numero_factura'    => 0,
                'tipo_reg'          => 'retiro',
                'tipo_doc'          => strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC',
                'no_identifi'       => $original->cedula,
                'primer_ape'        => strtoupper($partsApe[0] ?? ''),
                'segundo_ape'       => strtoupper($partsApe[1] ?? ''),
                'primer_nombre'     => strtoupper($partsNom[0] ?? ''),
                'segundo_nombre'    => strtoupper($partsNom[1] ?? ''),
                'fecha_ing'         => null,
                'fecha_ret'         => $fechaRetiro,
                'num_dias'          => $numDias,
                'cod_eps'           => $eps?->nit  ?? $eps?->cod_eps  ?? null,
                'nombre_eps'        => $eps?->nombre ?? null,
                'cod_afp'           => $afp?->nit  ?? $afp?->cod_afp  ?? null,
                'nombre_afp'        => $afp?->razon_social ?? null,
                'cod_arl'           => $codArl,
                'nombre_arl'        => $nombreArl,
                'cod_caja'          => $caja?->nit ?? $caja?->cod_caja ?? null,
                'nombre_caja'       => $caja?->nombre ?? null,
                'nivel_riesgo'      => $original->n_arl ?? 1,
                'salario_basico'    => $original->salario ?? 0,
                'n_plano'           => 100, // IR siempre en plano 100 (separado de planillas normales)
                'mes_plano'         => $carbonRetiro->month,
                'anio_plano'        => $carbonRetiro->year,
                'razon_social'      => $rs?->razon_social ?? null,
                'razon_social_id'   => $original->razon_social_id,
                'tipo_p'            => $original->tipo_modalidad_id,
                'tipo_modalidad_id' => $original->tipo_modalidad_id,
                'usuario_id'        => \Illuminate\Support\Facades\Auth::id(),
            ]);

            // ── 3. Crear nuevo contrato (fecha_ingreso = 26 del mes actual) ──
            $nuevaFechaIngreso = now()->startOfMonth()->addDays(25)->toDateString(); // día 26

            // Derivar arl_nit_cotizante de la nueva RS
            $nuevaRsRow = DB::table('razones_sociales')->where('id', $nuevaRsId)->first();
            $nuevoArlNitCotizante = $nuevaRsRow ? (int)$nuevaRsId : null; // RS dependiente → cotiza por RS

            $nuevoContrato = Contrato::create([
                'aliado_id'               => $alidoId,
                'cedula'                  => $original->cedula,
                'razon_social_id'         => $nuevaRsId,
                'plan_id'                 => $original->plan_id,
                'tipo_modalidad_id'       => $original->tipo_modalidad_id, // 12
                'eps_id'                  => $original->eps_id,
                'pension_id'              => $original->pension_id,
                'arl_id'                  => $original->arl_id,
                'n_arl'                   => $original->n_arl,
                'arl_modo'                => $original->arl_modo ?? 'razon_social',
                'arl_nit_cotizante'       => $nuevoArlNitCotizante,
                'caja_id'                 => $original->caja_id,
                'cargo'                   => $original->cargo,
                'actividad_economica_id'  => $original->actividad_economica_id,
                'salario'                 => $original->salario,
                'ibc'                     => $original->ibc,
                'porcentaje_caja'         => $original->porcentaje_caja,
                'administracion'          => $original->administracion,
                'admon_asesor'            => $original->admon_asesor,
                'costo_afiliacion'        => $original->costo_afiliacion,
                'seguro'                  => $original->seguro,
                'asesor_id'               => $original->asesor_id,
                'encargado_id'            => $original->encargado_id,
                'motivo_afiliacion_id'    => 8, // Ingreso-Retiro (rotación automática)
                'envio_planilla'          => $original->envio_planilla,
                'observacion'             => $original->observacion,
                'observacion_afiliacion'  => $original->observacion_afiliacion,
                'np'                      => $original->np,
                // Campos modificados para el nuevo contrato:
                'fecha_ingreso'           => $nuevaFechaIngreso,
                'estado'                  => 'vigente',
                'fecha_created'           => now(),
                'razon_social_bloqueada'  => false,
                'cobra_planilla_primer_mes' => false,
                // NO se copian: fecha_retiro, motivo_retiro_id
            ]);

            // ── 4. Crear radicados pendientes en el nuevo contrato ────────
            $nuevoContrato->load('plan');
            $nuevoContrato->crearRadicadosPendientes();
        });

        // Si es request AJAX (desde el modal) devolver JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'          => true,
                'nuevo_id'    => $nuevoContrato->id,
                'redirect_url'=> route('admin.contratos.edit', $nuevoContrato->id),
                'mensaje'     => 'Retiro marcado y contrato duplicado correctamente.',
            ]);
        }

        $redirectParams = [$nuevoContrato->id];
        if ($request->input('back_url')) {
            $redirectParams['back'] = $request->input('back_url');
        }

        return redirect()
            ->route('admin.contratos.edit', $redirectParams)
            ->with('success', '✅ Retiro marcado y contrato duplicado. Nueva RS: #' . $nuevoContrato->razon_social_id . ' · Ingreso: ' . \Carbon\Carbon::parse($nuevoContrato->fecha_ingreso)->format('d/m/Y'));
    }

    /**
     * Selecciona la mejor RS para el plan Ingreso-Retiro:
     * 1. RS donde el cliente NUNCA ha estado (sin contratos previos)
     * 2. Si ya estuvo en todas → la que tenga fecha_retiro más antigua
     *
     * Excluye: RS actuales (vigente), RS con "RAZON SOCIAL" en nombre, RS independientes.
     */
    private function seleccionarRsParaIR(int $alidoId, string $cedula, int $rsActualId): ?int
    {
        // Candidatas: dependientes, activas, sin "RAZON SOCIAL" en nombre, excluyendo la actual
        $candidatas = DB::table('razones_sociales')
            ->where('aliado_id', $alidoId)
            ->where('es_independiente', false)
            ->where('estado', 'Activa')
            ->where('id', '!=', $rsActualId)
            ->whereRaw("UPPER(razon_social) NOT LIKE '%RAZON SOCIAL%'")
            ->pluck('id');

        if ($candidatas->isEmpty()) {
            return null;
        }

        // RS donde el cliente tiene contrato VIGENTE (excluir — ya ocupada)
        $rsVigentes = DB::table('contratos')
            ->where('cedula', $cedula)
            ->where('aliado_id', $alidoId)
            ->where('estado', 'vigente')
            ->whereIn('razon_social_id', $candidatas)
            ->pluck('razon_social_id');

        $candidatasLibres = $candidatas->diff($rsVigentes);

        if ($candidatasLibres->isEmpty()) {
            return null;
        }

        // RS donde el cliente NUNCA ha estado (sin contratos históricos)
        $rsConHistorial = DB::table('contratos')
            ->where('cedula', $cedula)
            ->where('aliado_id', $alidoId)
            ->whereIn('razon_social_id', $candidatasLibres)
            ->pluck('razon_social_id')
            ->unique();

        $sinHistorial = $candidatasLibres->diff($rsConHistorial);

        if ($sinHistorial->isNotEmpty()) {
            // Prioridad 1: RS nunca usada → tomar la primera
            return $sinHistorial->first();
        }

        // Prioridad 2: ya estuvo en todas → la con fecha_retiro más antigua
        $rsOrdenada = DB::table('contratos')
            ->where('cedula', $cedula)
            ->where('aliado_id', $alidoId)
            ->where('estado', 'retirado')
            ->whereIn('razon_social_id', $candidatasLibres)
            ->whereNotNull('fecha_retiro')
            ->orderBy('fecha_retiro', 'asc') // más antigua = más tiempo sin usar
            ->value('razon_social_id');

        return $rsOrdenada ?: $candidatasLibres->first();
    }

    // ─── Detectar exención de AFP del cliente ─────────────────────────
    /**
     * Un cliente puede omitir AFP si:
     * - doc: CE (Cédula Extranjería), PT (Permiso Prot. Temporal), PE (Permiso Especial), PA (Pasaporte)
     * - Hombre ≥ 55 años  |  Mujer ≥ 50 años
     */
    private function detectarExencionAfp(?object $cliente): bool
    {
        if (!$cliente) return false;

        $tipoDoc = strtoupper(trim($cliente->tipo_doc ?? ''));

        // PT = Permiso de Protección Temporal (antes llamado PP — ambos exentos)
        $docExentos = ['CE', 'PT', 'PP', 'PE', 'PA'];
        if (in_array($tipoDoc, $docExentos)) {
            return true;
        }

        // Por edad y género
        $edad   = $cliente->edad ?? null;
        $genero = strtoupper(trim($cliente->genero ?? ''));
        if ($edad === null) return false;

        return ($genero === 'M' && $edad >= 55)
            || ($genero === 'F' && $edad >= 50);
    }

    // ─── Validación ───────────────────────────────────────────────────
    private function validar(Request $request, ?Contrato $contrato = null): array
    {
        return $request->validate([
            'cedula'               => 'required|digits_between:6,15',
            'razon_social_id'      => 'nullable|exists:razones_sociales,id',
            'plan_id'              => 'nullable|exists:planes_contrato,id',
            'tipo_modalidad_id'    => 'nullable|exists:tipo_modalidad,id',
            'eps_id'               => 'nullable|exists:eps,id',
            'pension_id'           => 'nullable|exists:pensiones,id',
            'arl_id'               => 'nullable|exists:arls,id',
            'n_arl'                => 'nullable|integer|min:1|max:5',
            'arl_modo'             => 'nullable|in:razon_social,independiente',
            'arl_nit_cotizante'    => 'nullable|integer|min:0',
            'caja_id'              => 'nullable|exists:cajas,id',
            'cargo'                => 'nullable|string|max:255',
            'fecha_ingreso'        => 'nullable|date',
            'fecha_retiro'         => 'nullable|date',
            'actividad_economica_id' => 'nullable|exists:actividades_economicas,id',
            'salario'              => 'nullable|numeric|min:0',
            'ibc'                  => 'nullable|numeric|min:0',
            'porcentaje_caja'      => 'nullable|numeric|min:0|max:100',
            'administracion'       => 'nullable|numeric|min:0',
            'admon_asesor'         => 'nullable|numeric|min:0',
            'costo_afiliacion'     => 'nullable|numeric|min:0',
            'seguro'               => 'nullable|numeric|min:0',
            'asesor_id'            => 'nullable|exists:asesores,id',
            'encargado_id'         => 'nullable|exists:users,id',
            'motivo_afiliacion_id' => 'nullable|exists:motivos_afiliacion,id',
            'motivo_retiro_id'     => 'nullable|exists:motivos_retiro,id',
            'fecha_arl'            => 'nullable|date',
            'envio_planilla'       => 'nullable|string|max:55',
            'np'                   => 'nullable|string|max:255',
            'observacion'          => 'nullable|string',
            'observacion_afiliacion' => 'nullable|string',
            'operador_planilla_id'      => 'nullable|integer',
            'cobra_planilla_primer_mes' => 'boolean',
        ]);
    }
}
