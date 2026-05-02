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
        $cliente = $cedula ? Cliente::where('cedula', $cedula)->first() : null;

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
                        ->update(['operador_planilla_id' => $operadorId]);
                }
            }
        }

        // Redirigir al cliente del contrato creado
        $cedula  = $nuevoContrato->cedula ?? ($data['cedula'] ?? null);
        $cliente = $cedula ? \App\Models\Cliente::where('cedula', $cedula)->first() : null;
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

        return view('admin.contratos.form', array_merge(
            $this->datosFormulario($alidoId, $cliente, $contrato->razon_social_id, $contrato->id),
            compact('contrato', 'cliente', 'backUrl', 'radicadosPorTipo', 'rsBloquedaPorAfiliacion')
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

        // ── Calcular SS del retiro real (misma lógica que cotizar()) ──────────
        // Solo aplica para retiro real y cuando hay días cotizados
        $vEpsRetiro = 0; $vArlRetiro = 0; $vAfpRetiro = 0; $vCajaRetiro = 0; $totalSsRetiro = 0;

        if ($tipoRetiro === 'real' && $numDias > 0) {
            $modal   = $contrato->tipoModalidad;
            $plan    = $contrato->plan;
            $nivelArl = (int)($contrato->n_arl ?? 1);
            $salario  = (float)($contrato->salario ?? 0);
            $ibc      = (float)($contrato->ibc ?? $salario) ?: $salario;
            $sm       = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);

            // ── Fallback IBC: si salario/ibc = 0 (común en contratos legacy), usar salario mínimo
            if ($ibc <= 0) {
                $ibc = $sm;
            }

            $esIndep = $modal && $modal->esIndependiente();
            $esTP    = $modal && $modal->esTiempoParcial();

            $pctEps  = $esIndep ? ConfiguracionBrynex::pctSaludIndependiente()  : ConfiguracionBrynex::pctSaludDependiente();
            $pctPen  = $esIndep ? ConfiguracionBrynex::pctPensionIndependiente() : ConfiguracionBrynex::pctPensionDependiente();
            $pctArl  = ArlTarifa::porcentajePara($nivelArl, $alidoId);

            if ($esIndep) {
                $pctCaja = ConfiguracionBrynex::pctCajaIndependienteAlto();
            } else {
                $pctCaja = ConfiguracionBrynex::pctCajaDependiente();
            }

            // ── Fallback plan: si el contrato no tiene plan asignado,
            //    inferir entidades directamente desde los IDs del contrato.
            //    Esto aplica a contratos migrados del legacy que no tienen plan_id.
            $incluyeEps     = $plan ? (bool)$plan->incluye_eps     : ($contrato->eps_id     !== null);
            $incluyeArl     = $plan ? (bool)$plan->incluye_arl     : ($contrato->arl_id     !== null);
            $incluyePension = $plan ? (bool)$plan->incluye_pension  : ($contrato->pension_id  !== null);
            $incluyeCaja    = $plan ? (bool)$plan->incluye_caja     : ($contrato->caja_id     !== null);

            // Redondear hacia arriba al 100 más cercano
            $r = fn($v) => ceil($v / 100) * 100;

            if ($esTP) {
                // Tiempo Parcial: usar días y factor del tipo de modalidad
                $diasP     = $modal->diasPorEntidad();
                $factorMap = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
                $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
                $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

                $vEpsRetiro  = 0;
                $vArlRetiro  = $incluyeArl     ? (int)$r($sm * $pctArl / 100)                     : 0;
                $vAfpRetiro  = $incluyePension  ? (int)$r($sm * $factorAfp  * $pctPen / 100)       : 0;
                $vCajaRetiro = $incluyeCaja     ? (int)$r($sm * $factorCaja * $pctCaja / 100)      : 0;
            } else {
                // Normal: proporcional a num_dias / 30
                $epsMes  = $incluyeEps     ? $r($ibc * $pctEps  / 100) : 0;
                $arlMes  = $incluyeArl     ? $r($ibc * $pctArl  / 100) : 0;
                $penMes  = $incluyePension  ? $r($ibc * $pctPen  / 100) : 0;
                $cajaMes = $incluyeCaja     ? $r($ibc * $pctCaja / 100) : 0;

                $vEpsRetiro  = $numDias < 30 ? (int)$r($epsMes  * $numDias / 30) : (int)$epsMes;
                $vArlRetiro  = $numDias < 30 ? (int)$r($arlMes  * $numDias / 30) : (int)$arlMes;
                $vAfpRetiro  = $numDias < 30 ? (int)$r($penMes  * $numDias / 30) : (int)$penMes;
                $vCajaRetiro = $numDias < 30 ? (int)$r($cajaMes * $numDias / 30) : (int)$cajaMes;
            }

            $totalSsRetiro = $vEpsRetiro + $vArlRetiro + $vAfpRetiro + $vCajaRetiro;
        }

        DB::transaction(function () use ($contrato, $validated, $alidoId, $tipoRetiro, $fechaRetiro, $numDias,
                                         $vEpsRetiro, $vArlRetiro, $vAfpRetiro, $vCajaRetiro, $totalSsRetiro) {
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
                'otros'            => 0,
                'otros_admon'      => 0,
                'mensajeria'       => 0,
                'dias_cotizados'   => $numDias,
                'v_eps'       => $vEpsRetiro,
                'v_arl'       => $vArlRetiro,
                'v_afp'       => $vAfpRetiro,
                'v_caja'      => $vCajaRetiro,
                'total_ss'    => $totalSsRetiro,
                'admon'       => 0,
                'admin_asesor'=> 0,
                'seguro'      => 0,
                'afiliacion'  => 0,
                'iva'         => 0,
                'total'       => 0,
                'saldo_proximo'=> 0,
                'usuario_id'  => Auth::id(),
                'observacion' => $validated['observacion'] ?? null,
            ]);

            // 3) Mes/año del plano: viene del select del modal (controlado por el usuario)
            $mesPlan  = (int) $validated['mes_plano'];
            $anioPlan = (int) $validated['anio_plano'];

            // Último n_plano de la RS o 1
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
                'tipo_doc'          => 'CC',
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

            // Prorratear por dias cotizados (dias/30); admon y seguro siempre completos
            $eps  = $dias < 30 ? $r($epsMes  * $dias / 30) : $epsMes;
            $arl  = $dias < 30 ? $r($arlMes  * $dias / 30) : $arlMes;
            $pen  = $dias < 30 ? $r($penMes  * $dias / 30) : $penMes;
            // Cargo sin-CCF es fijo: NO se prorratea por días
            $caja = ($cajaMes === \App\Models\Contrato::CARGO_SIN_CCF)
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

        // IDs de modalidades independientes (I Act, I Venc, En el Exterior)
        $modalidadesIndependientes = [10, 11, 14];

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
        //   - Todas las variantes de Tiempo Parcial (1,2,3,4,-6,-7,-8)
        //     → el plan "ARL+CCF" sin AFP (APTP) solo es válido para clientes exentos
        $modalidadesAfpObligatorio = [0, 10, 11, 1, 2, 3, 4, -6, -7, -8];

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

    // ─── Detectar exención de AFP del cliente ─────────────────────────
    /**
     * Un cliente puede omitir AFP si:
     * - doc: CE (Cédula Extranjería), PP (Permiso Prot. Temporal), PE (Permiso Especial), PA (Pasaporte)
     * - Hombre ≥ 55 años  |  Mujer ≥ 50 años
     */
    private function detectarExencionAfp(?object $cliente): bool
    {
        if (!$cliente) return false;

        // Por tipo de documento
        $docExentos = ['CE', 'PP', 'PE', 'PA'];
        if (in_array(strtoupper(trim($cliente->tipo_doc ?? '')), $docExentos)) {
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
