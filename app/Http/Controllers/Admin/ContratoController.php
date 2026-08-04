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
        $contrato = Contrato::where('aliado_id', $alidoId)->with(['cliente','radicados.user','plan','razonSocial'])->findOrFail($id);
        $cliente  = $contrato->cliente;

        // URL de retorno: viene como ?back=... o se toma del referrer
        $backUrl = request('back') ?: url()->previous();

        // ── Radicados indexados por tipo (eps, arl, caja, pension) ──────
        $radicadosPorTipo = $contrato->radicados->keyBy('tipo');

        // ── ¿La RS está bloqueada por afiliaciones activas? ─────────────
        // Si algún radicado está en tramite u ok, no se puede cambiar la RS
        $estadosBloqueantes = ['tramite', 'ok'];
        $hayAfiliacionActiva = $contrato->radicados
            ->whereIn('estado', $estadosBloqueantes)
            ->isNotEmpty();

        // El superadmin no queda bloqueado: la vista le deja los campos
        // editables y le advierte antes de guardar (ver $rsDesbloqueoSuperadmin
        // en form.blade.php). El cambio forzado queda en bitácora.
        $puedeForzarBloqueo      = Auth::user()->hasRole('superadmin');
        $rsBloquedaPorAfiliacion = $hayAfiliacionActiva && !$puedeForzarBloqueo;
        $rsDesbloqueoSuperadmin  = $hayAfiliacionActiva && $puedeForzarBloqueo;

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

        // Verificar si el contrato tiene planillas con días cotizados > 0
        // Para independientes (es_independiente=1): siempre se permite retiro informativo
        // porque el cliente paga la SS por sus propios medios.
        $rsEdit = $contrato->razonSocial;
        $esIndependienteEdit = $contrato->esIndependiente() || ($rsEdit && $rsEdit->es_independiente);
        $tienePlanillaConDias = $esIndependienteEdit
            ? false
            : \App\Models\Factura::where('contrato_id', $contrato->id)
                ->where('tipo', 'planilla')
                ->where('dias_cotizados', '>', 0)
                ->where('numero_factura', '>', 0)
                ->exists();

        // ── Modal Duplicar (Plan Ingreso-Retiro) ─────────────
        $rsIrOpciones = [];
        $rsIrPreviewId = null;
        $rsIrHayDisponible = false;

        if ($contrato->estaVigente() && (int)$contrato->tipo_modalidad_id === 12) {
            $alidoIdIr = $alidoId;
            $todasRsIr = \App\Models\RazonSocial::where('aliado_id', $alidoIdIr)
                ->where('es_independiente', false)
                ->where('estado', 'Activa')
                ->whereRaw("UPPER(razon_social) NOT LIKE '%RAZON SOCIAL%'")
                ->get(['id', 'razon_social']);

            $rsVigentesIrSet = DB::table('contratos')
                ->where('cedula', $contrato->cedula)
                ->where('aliado_id', $alidoIdIr)
                ->where('estado', 'vigente')
                ->pluck('razon_social_id')
                ->filter() // excluir NULLs para evitar error en flip()
                ->flip();

            $ultimosRetiros = DB::table('contratos')
                ->where('cedula', $contrato->cedula)
                ->where('aliado_id', $alidoIdIr)
                ->where('estado', 'retirado')
                ->whereNotNull('fecha_retiro')
                ->select('razon_social_id', DB::raw('MAX(fecha_retiro) as ultimo_retiro'))
                ->groupBy('razon_social_id')
                ->get()
                ->keyBy('razon_social_id');

            $rsConHistIr = DB::table('contratos')
                ->where('cedula', $contrato->cedula)
                ->where('aliado_id', $alidoIdIr)
                ->pluck('razon_social_id')
                ->unique()
                ->filter() // excluir NULLs para evitar error en flip()
                ->flip();

            $ahora = \Carbon\Carbon::now();

            foreach ($todasRsIr as $rsItem) {
                $esActual  = (int)$rsItem->id === (int)$contrato->razon_social_id;
                $esVigente = isset($rsVigentesIrSet[$rsItem->id]);
                $bloqueada = $esActual || $esVigente;

                $tiempoTexto = null;
                $ultimoRet = $ultimosRetiros->get($rsItem->id);
                if ($ultimoRet && $ultimoRet->ultimo_retiro) {
                    $fechaRet = \Carbon\Carbon::parse($ultimoRet->ultimo_retiro);
                    $meses = (int)$fechaRet->diffInMonths($ahora);
                    $anios = (int)floor($meses / 12);
                    $mesesRest = $meses % 12;
                    if ($anios > 0 && $mesesRest > 0) $tiempoTexto = "Retirado hace {$anios}a {$mesesRest}m";
                    elseif ($anios > 0) $tiempoTexto = "Retirado hace {$anios} año" . ($anios > 1 ? 's' : '');
                    elseif ($meses > 0) $tiempoTexto = "Retirado hace {$meses} mes" . ($meses > 1 ? 'es' : '');
                    else $tiempoTexto = 'Retirado este mes';
                }

                if ($bloqueada) {
                    $prioridad = 99;
                } elseif (!isset($rsConHistIr[$rsItem->id])) {
                    $prioridad = 0;
                } else {
                    $prioridad = $ultimoRet ? \Carbon\Carbon::parse($ultimoRet->ultimo_retiro)->timestamp : 50;
                }

                $rsIrOpciones[] = [
                    'id'          => $rsItem->id,
                    'nombre'      => $rsItem->razon_social,
                    'bloqueada'   => $bloqueada,
                    'es_actual'   => $esActual,
                    'es_vigente'  => $esVigente,
                    'nunca_usada' => !isset($rsConHistIr[$rsItem->id]),
                    'tiempo'      => $tiempoTexto,
                    'prioridad'   => $prioridad,
                ];
            }

            usort($rsIrOpciones, fn($a, $b) => $a['bloqueada'] <=> $b['bloqueada'] ?: $a['prioridad'] <=> $b['prioridad']);

            foreach ($rsIrOpciones as $op) {
                if (!$op['bloqueada']) {
                    $rsIrPreviewId = $op['id'];
                    break;
                }
            }
            $rsIrHayDisponible = $rsIrPreviewId !== null;
        }

        $cfgAliado = \App\Models\ConfiguracionAliado::paraAliado($alidoId);
        $diaIngresoIr = max(1, min(28, (int)($cfgAliado?->dia_ingreso_ir ?? 26)));

        return view('admin.contratos.form', array_merge(
            $this->datosFormulario($alidoId, $cliente, $contrato->razon_social_id, $contrato->id),
            compact('contrato', 'cliente', 'backUrl', 'radicadosPorTipo', 'rsBloquedaPorAfiliacion', 'rsDesbloqueoSuperadmin', 'otrosContratosVigentes', 'tienePlanillaConDias', 'rsIrOpciones', 'rsIrPreviewId', 'rsIrHayDisponible', 'diaIngresoIr')
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
        // Excepción: el superadmin sí puede forzarlo (la vista ya le advirtió);
        // el cambio queda registrado en bitácora más abajo.
        $estadosBloqueantes = ['tramite', 'ok'];
        $hayAfiliacionActiva = $contrato->radicados
            ->whereIn('estado', $estadosBloqueantes)
            ->isNotEmpty();

        $puedeForzarBloqueo      = Auth::user()->hasRole('superadmin');
        $rsBloquedaPorAfiliacion = $hayAfiliacionActiva && !$puedeForzarBloqueo;

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

        // ── Protección de ENTIDADES con radicados activos ──────────────
        // Regla: si el contrato tiene radicados en estado 'tramite' u 'ok'
        // para una entidad (eps, arl, pension, caja), el nuevo plan seleccionado
        // DEBE incluir esa misma entidad. Si el nuevo plan elimina una entidad
        // que ya tiene una afiliación en curso, se bloquea el cambio.
        //
        // Ejemplo BLOQUEADO: contrato con EPS ok → nuevo plan sin EPS.
        // Ejemplo PERMITIDO:  contrato I Venc → I Act (mismo plan, mismas entidades).
        // Ejemplo PERMITIDO:  agregar CAJA a un plan que antes no la tenía.
        $tiposConRadicadoActivo = $contrato->radicados
            ->whereIn('estado', $estadosBloqueantes)
            ->pluck('tipo')
            ->unique()
            ->values()
            ->toArray(); // ej: ['eps', 'arl']

        if (!empty($tiposConRadicadoActivo) && !$puedeForzarBloqueo && isset($data['plan_id']) && (int)$data['plan_id'] !== (int)$contrato->plan_id) {
            $nuevoPlan = \App\Models\PlanContrato::find($data['plan_id']);
            if ($nuevoPlan) {
                $mapaNuevoPlan = [
                    'eps'     => (bool) $nuevoPlan->incluye_eps,
                    'arl'     => (bool) $nuevoPlan->incluye_arl,
                    'pension' => (bool) $nuevoPlan->incluye_pension,
                    'caja'    => (bool) $nuevoPlan->incluye_caja,
                ];
                $entidadesExcluidas = array_filter(
                    $tiposConRadicadoActivo,
                    fn($tipo) => !($mapaNuevoPlan[$tipo] ?? false)
                );
                if (!empty($entidadesExcluidas)) {
                    $nombresEntidades = array_map(fn($t) => strtoupper($t), $entidadesExcluidas);
                    return redirect()
                        ->route('admin.contratos.edit', array_filter([
                            $id,
                            'back'   => $request->input('back_url'),
                            'iframe' => $request->input('iframe') ? '1' : null,
                        ]))
                        ->withErrors(['plan_id' => 'No se puede cambiar al plan "' . $nuevoPlan->nombre . '": ya existe afiliación activa (tramite/ok) en ' . implode(', ', $nombresEntidades) . '. El nuevo plan debe incluir esas entidades.']);
                }
            }
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

        // ── Rastro de cambios forzados por superadmin ──────────────────
        // Si el contrato tenía afiliaciones activas y el usuario es superadmin,
        // los campos que normalmente estarían bloqueados sí pudieron cambiar.
        // Se deja constancia en bitácora de qué se tocó y quién lo tocó.
        $cambiosForzados = [];
        if ($hayAfiliacionActiva && $puedeForzarBloqueo) {
            $camposProtegidos = ['razon_social_id', 'plan_id', 'fecha_ingreso', 'tipo_modalidad_id', 'encargado_id'];
            foreach ($camposProtegidos as $campo) {
                if (!array_key_exists($campo, $data)) continue;
                $viejo = $contrato->$campo;
                $nuevo = $data[$campo];
                if ($campo === 'fecha_ingreso') {
                    $viejo = $viejo ? \Carbon\Carbon::parse($viejo)->format('Y-m-d') : null;
                    $nuevo = $nuevo ? \Carbon\Carbon::parse($nuevo)->format('Y-m-d') : null;
                } else {
                    $viejo = $viejo === null ? null : (int) $viejo;
                    $nuevo = $nuevo === null || $nuevo === '' ? null : (int) $nuevo;
                }
                if ($viejo !== $nuevo) {
                    $cambiosForzados[$campo] = ['old' => $viejo, 'new' => $nuevo];
                }
            }
        }

        DB::transaction(function () use ($contrato, $data, $alidoId, $cambiosForzados) {
            $oldPlanId = $contrato->plan_id;

            // Detectar cambios en campos sensibles de tarifa
            $cambios = [];
            foreach (['administracion', 'admon_asesor', 'costo_afiliacion', 'seguro'] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $oldVal = (float)($contrato->$campo ?? 0);
                    $newVal = (float)($data[$campo] ?? 0);
                    if ($oldVal !== $newVal) {
                        $cambios[$campo] = [
                            'old' => $oldVal,
                            'new' => $newVal
                        ];
                    }
                }
            }

            $contrato->update($data);

            if (!empty($cambios)) {
                \App\Models\Bitacora::registrar(
                    'updated', 'Contrato', $contrato->id,
                    "Tarifas de contrato modificadas (Cédula: {$contrato->cedula}).",
                    ['cambios' => $cambios],
                    $alidoId
                );
            }

            if (!empty($cambiosForzados)) {
                \App\Models\Bitacora::registrar(
                    'updated', 'Contrato', $contrato->id,
                    'Superadmin modificó campos protegidos de un contrato con afiliaciones en trámite u OK (Cédula: '.$contrato->cedula.').',
                    ['cambios_forzados' => $cambiosForzados],
                    $alidoId
                );
            }

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
            'valor_ss'         => 'nullable|numeric|min:0',
            'mora'             => 'nullable|numeric|min:0',
        ]);

        $tipoRetiro  = $validated['tipo_retiro'];
        $fechaRetiro = $validated['fecha_retiro'];
        $numDias = $tipoRetiro === 'real'
            ? max(1, min(30, (int)($validated['num_dias'] ?? 1)))
            : 0;

        // Por seguridad: bloquear retiro informativo si tiene planillas con días > 0
        // Excepción: contratos de RS independiente (es_independiente=1) siempre pueden
        // hacer retiro informativo porque el cliente paga la SS por sus propios medios.
        if ($tipoRetiro === 'informativo') {
            $rsRetiroCheck = $contrato->razonSocial;
            $esIndependienteRetiro = $contrato->esIndependiente() || ($rsRetiroCheck && $rsRetiroCheck->es_independiente);

            if (!$esIndependienteRetiro) {
                $tienePlanillaConDias = \App\Models\Factura::where('contrato_id', $contrato->id)
                    ->where('tipo', 'planilla')
                    ->where('dias_cotizados', '>', 0)
                    ->where('numero_factura', '>', 0)
                    ->exists();
                if ($tienePlanillaConDias) {
                    return redirect()
                        ->route('admin.contratos.edit', [$id, 'back' => $request->input('back_url')])
                        ->withErrors(['tipo_retiro' => 'No se puede aplicar retiro informativo porque el contrato ya tiene planillas pagadas con días cotizados.']);
                }
            }
        }

        // Validar que mes_plano sea exactamente el periodo consecutivo permitido
        $ultimoPlano = DB::table('planos')
            ->where('contrato_id', $contrato->id)
            ->where('num_dias', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('anio_plano', 'desc')
            ->orderBy('mes_plano', 'desc')
            ->first();

        $mesEsperado = null;
        $anioEsperado = null;

        if ($ultimoPlano) {
            $mesEsperado = (int)$ultimoPlano->mes_plano + 1;
            $anioEsperado = (int)$ultimoPlano->anio_plano;
            if ($mesEsperado > 12) {
                $mesEsperado = 1;
                $anioEsperado++;
            }
        } else {
            if ($contrato->fecha_ingreso) {
                $ingreso = \Carbon\Carbon::parse($contrato->fecha_ingreso);
                $mesEsperado = $ingreso->month;
                $anioEsperado = $ingreso->year;
            } else {
                $mesEsperado = now()->month;
                $anioEsperado = now()->year;
            }
        }

        if ((int)$validated['mes_plano'] !== $mesEsperado || (int)$validated['anio_plano'] !== $anioEsperado) {
            $mesesNombres = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $nombreMes = $mesesNombres[$mesEsperado] ?? '';
            return redirect()
                ->route('admin.contratos.edit', [$id, 'back' => $request->input('back_url')])
                ->withErrors(['mes_plano' => "El retiro debe aplicarse exactamente en el periodo consecutivo: {$nombreMes} de {$anioEsperado}. No se permiten saltos de periodos sin planilla."]);
        }

        // ── Calcular SS del retiro real usando calcularCotizacion() del modelo ─
        $vEpsRetiro = 0; $vArlRetiro = 0; $vAfpRetiro = 0; $vCajaRetiro = 0; $totalSsRetiro = 0;

        if ($tipoRetiro === 'real' && $numDias > 0) {
            // Fallback para contratos legacy donde ambos campos son 0: usar SM.
            // Si solo ibc=0 (dependiente) o solo salario=0 (independiente),
            // calcularCotizacion() lo resuelve internámente según modalidad.
            $ibcOriginal = (float)($contrato->ibc ?? 0);
            $salOriginal = (float)($contrato->salario ?? 0);
            if ($ibcOriginal <= 0 && $salOriginal <= 0) {
                // Ninguna base registrada → usar salario mínimo del sistema
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

            // Si el usuario ingresó un valor total manual en el modal, lo usamos y distribuimos proporcionalmente
            if ($request->has('valor_ss') && !is_null($request->input('valor_ss'))) {
                $valorSsManual = (int)$request->input('valor_ss');
                if ($valorSsManual >= 0) {
                    if ($totalSsRetiro > 0) {
                        $factor = $valorSsManual / $totalSsRetiro;
                        $vEpsRetiro  = (int)round($vEpsRetiro * $factor);
                        $vArlRetiro  = (int)round($vArlRetiro * $factor);
                        $vAfpRetiro  = (int)round($vAfpRetiro * $factor);
                        $vCajaRetiro = (int)round($vCajaRetiro * $factor);

                        // Ajustar remanentes
                        $sumaTemp = $vEpsRetiro + $vArlRetiro + $vAfpRetiro + $vCajaRetiro;
                        $diff = $valorSsManual - $sumaTemp;
                        if ($diff !== 0) {
                            if ($vEpsRetiro > 0) $vEpsRetiro += $diff;
                            elseif ($vAfpRetiro > 0) $vAfpRetiro += $diff;
                            elseif ($vArlRetiro > 0) $vArlRetiro += $diff;
                            else $vEpsRetiro += $diff;
                        }
                    } else {
                        $vEpsRetiro = $valorSsManual;
                    }
                    $totalSsRetiro = $valorSsManual;
                }
            }

            // Restaurar valores originales (evita mutar el objeto si se reutiliza)
            $contrato->ibc     = $ibcOriginal;
            $contrato->salario = $salOriginal;
        }


        // ── Mora real del retiro (sin tramos mínimos) ─────────────────────────
        $moraRetiro = 0;
        $esMesActual = (int)($contrato->tipo_modalidad_id) === 11;

        if ($request->has('mora') && !is_null($request->input('mora'))) {
            $moraRetiro = (int)$request->input('mora');
        } else {
            try {
                $rsRetiro   = $contrato->razonSocial;
                $esIndep    = $contrato->esIndependiente() || ($rsRetiro && $rsRetiro->es_independiente);
                $rsNitRet   = $esIndep ? (int)$contrato->cedula : ($rsRetiro ? (int)($rsRetiro->nit ?: $rsRetiro->id) : 0);
                $rsDiaHRet  = $esIndep ? null : ($rsRetiro ? ($rsRetiro->dia_habil ?? null) : null);

                $mesRet     = (int)($validated['mes_plano']  ?? now()->month);
                $anioRet    = (int)($validated['anio_plano'] ?? now()->year);

                if ($tipoRetiro === 'real') {
                    if ($esMesActual) {
                        $mesVence   = $mesRet;
                        $anioVence  = $anioRet;
                    } else {
                        // La planilla de mes_plano (periodo cotizado) vence y se paga en el mes siguiente
                        $mesVence   = $mesRet + 1;
                        $anioVence  = $anioRet;
                        if ($mesVence > 12) {
                            $mesVence  = 1;
                            $anioVence++;
                        }
                    }
                } else {
                    // Retiro Informativo: vence en el mismo mes del plano (o no aplica mora)
                    $mesVence   = $mesRet;
                    $anioVence  = $anioRet;
                }

                if ($rsNitRet && $totalSsRetiro > 0) {
                    $periodoActualNum = now()->year * 100 + now()->month;
                    $periodoVenceNum = $anioVence * 100 + $mesVence;

                    if ($periodoVenceNum > $periodoActualNum) {
                        $moraRetiro = 0;
                    } else {
                        $moraInfo   = MoraClienteService::calcular($alidoId, $rsNitRet, $rsDiaHRet, $totalSsRetiro, $mesVence, $anioVence);
                        $moraRetiro = (int) round($moraInfo['mora_real'] ?? 0); // solo el interés real
                    }
                }
            } catch (\Throwable) {}
        }

        $mesFactura = (int)$validated['mes_plano'];
        $anioFactura = (int)$validated['anio_plano'];
        if ($tipoRetiro === 'real' && !$esMesActual) {
            $mesFactura++;
            if ($mesFactura > 12) {
                $mesFactura = 1;
                $anioFactura++;
            }
        }

        DB::transaction(function () use ($contrato, $validated, $alidoId, $tipoRetiro, $fechaRetiro, $numDias,
                                         $vEpsRetiro, $vArlRetiro, $vAfpRetiro, $vCajaRetiro, $totalSsRetiro, $moraRetiro,
                                         $mesFactura, $anioFactura) {
            // 1) Actualizar contrato → retirado
            $contrato->update([
                'estado'           => 'retirado',
                'motivo_retiro_id' => $validated['motivo_retiro_id'],
                'fecha_retiro'     => $fechaRetiro,
                'observacion'      => $validated['observacion'] ?? $contrato->observacion,
            ]);

            // 2) n_plano del retiro = plano actual de la RS.
            //    NOTA: El plano 100 es exclusivo del flujo "Duplicar Contrato" (IR rotation).
            //    El retiro normal — incluso en IR (id=12) — usa el n_plano de la RS.
            //    Se calcula ANTES de Factura::create() para que la factura también lo reciba.
            $nPlano = $contrato->razon_social_id
                ? (\App\Models\RazonSocial::find($contrato->razon_social_id)?->n_plano ?? 1)
                : 1;

            // 3) Crear factura de retiro (numero_factura=0, total=$0, pero SS calculado)
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
                'mes'              => $mesFactura,
                'anio'             => $anioFactura,
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
                'v_eps'            => $vEpsRetiro,
                'v_arl'            => $vArlRetiro,
                'v_afp'            => $vAfpRetiro,
                'v_caja'           => $vCajaRetiro,
                'total_ss'         => $totalSsRetiro,
                'mora'             => $moraRetiro,  // campo dedicado mora (no es ingreso)
                'admon'            => 0,
                'admin_asesor'     => 0,
                'seguro'           => 0,
                'afiliacion'       => 0,
                'iva'              => 0,
                'total'            => 0,   // el cliente no paga
                'saldo_proximo'    => 0,
                'n_plano'          => $nPlano, // ← FIX: la factura también debe tener el n_plano
                'usuario_id'       => Auth::id(),
                'observacion'      => $validated['observacion'] ?? null,
            ]);

            // 4) Mes/año del plano:
            //    validated['mes_plano'] = mes de cotización (vencido) que ingresa el usuario.
            //    El módulo de planos con mes=7 busca mes_plano=6 (mesVencido = mes-1),
            //    que coincide con validated['mes_plano'] cuando el usuario ingresa Junio=6
            //    y la factura queda registrada en Julio (mesFactura = mes_plano + 1).
            //    NO se resta 1: validated['mes_plano'] ya ES el mes de cotización correcto.
            $mesPlan  = (int)$validated['mes_plano'];
            $anioPlan = (int)$validated['anio_plano'];


            // 4) Crear plano con fecha_ret y num_dias
            $cliente = $contrato->cliente;
            $eps     = $contrato->eps;
            $afp     = $contrato->pension;
            $arl     = $contrato->arl;
            $caja    = $contrato->caja;
            $rs      = $contrato->razonSocial;

            $arlSnapshot = \App\Models\Plano::resolverArlSnapshot($contrato, $rs);
            $codArl = $arlSnapshot['cod_arl'];
            $nombreArl = $arlSnapshot['nombre_arl'];

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

    // ─── API: Calcular Costo Retiro y Mora (devuelve JSON) ───────────
    public function apiCalcularRetiro(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $aliadoId)
            ->with(['eps', 'arl', 'pension', 'caja', 'tipoModalidad', 'razonSocial', 'cliente'])
            ->findOrFail($contratoId);

        $dias = (int) $request->get('dias', 1);
        $mesPlano = (int) $request->get('mes_plano', now()->month);
        $anioPlano = (int) $request->get('anio_plano', now()->year);
        $tipoRetiro = $request->get('tipo_retiro', 'real');

        $costoSs = 0;
        $mora = 0;
        $esMesActual = (int)($contrato->tipo_modalidad_id) === 11;

        $desglose = [
            'eps'  => ['valor' => 0, 'mora' => 0],
            'arl'  => ['valor' => 0, 'mora' => 0],
            'pen'  => ['valor' => 0, 'mora' => 0],
            'caja' => ['valor' => 0, 'mora' => 0],
        ];

        if ($tipoRetiro === 'real' && $dias > 0) {
            $ibcOriginal = (float)($contrato->ibc ?? 0);
            $salOriginal = (float)($contrato->salario ?? 0);
            // Fallback solo cuando ambos son 0 (legacy sin ningún dato).
            // Si solo ibc=0 (dependiente) o solo salario=0 (independiente),
            // calcularCotizacion() lo resuelve internámente según modalidad.
            if ($ibcOriginal <= 0 && $salOriginal <= 0) {
                $sm = (float) \App\Models\ConfiguracionBrynex::obtener('salario_minimo', 1423500);
                $contrato->ibc     = $sm;
                $contrato->salario = $sm;
            }

            $cotizacion = $contrato->calcularCotizacion($dias);
            $vEps  = (int)($cotizacion['eps'] ?? 0);
            $vArl  = (int)($cotizacion['arl'] ?? 0);
            $vPen  = (int)($cotizacion['pen'] ?? 0);
            $vCaja = (int)($cotizacion['caja'] ?? 0);
            $costoSs = $vEps + $vArl + $vPen + $vCaja;

            // Restaurar
            $contrato->ibc     = $ibcOriginal;
            $contrato->salario = $salOriginal;

            // Calcular mora
            if ($esMesActual) {
                $mesVence = $mesPlano;
                $anioVence = $anioPlano;
            } else {
                $mesVence = $mesPlano + 1;
                $anioVence = $anioPlano;
                if ($mesVence > 12) {
                    $mesVence = 1;
                    $anioVence++;
                }
            }

            $rsRetiro = $contrato->razonSocial;
            $esIndep = $contrato->esIndependiente() || ($rsRetiro && $rsRetiro->es_independiente);
            $rsNitRet = $esIndep ? (int)$contrato->cedula : ($rsRetiro ? (int)($rsRetiro->nit ?: $rsRetiro->id) : 0);
            $rsDiaHRet = $esIndep ? null : ($rsRetiro ? ($rsRetiro->dia_habil ?? null) : null);

            if ($rsNitRet && $costoSs > 0) {
                $periodoActualNum = now()->year * 100 + now()->month;
                $periodoVenceNum = $anioVence * 100 + $mesVence;

                if ($periodoVenceNum > $periodoActualNum) {
                    $mora = 0;
                } else {
                    $moraInfo = \App\Services\MoraClienteService::calcular($aliadoId, $rsNitRet, $rsDiaHRet, $costoSs, $mesVence, $anioVence);
                    $mora = (int) round($moraInfo['mora_real'] ?? 0);
                }
            }

            // Prorratear la mora proporcionalmente por entidad
            $mEps = 0; $mArl = 0; $mPen = 0; $mCaja = 0;
            if ($mora > 0 && $costoSs > 0) {
                $mEps  = (int) round($mora * ($vEps  / $costoSs));
                $mArl  = (int) round($mora * ($vArl  / $costoSs));
                $mPen  = (int) round($mora * ($vPen  / $costoSs));
                $mCaja = (int) round($mora * ($vCaja / $costoSs));

                // Ajustar remanentes con la diferencia
                $sumaMora = $mEps + $mArl + $mPen + $mCaja;
                $diff = $mora - $sumaMora;
                if ($diff !== 0) {
                    if ($vPen >= $vEps && $vPen >= $vArl && $vPen >= $vCaja) {
                        $mPen += $diff;
                    } elseif ($vEps >= $vArl && $vEps >= $vCaja) {
                        $mEps += $diff;
                    } else {
                        $mArl += $diff;
                    }
                }
            }

            $desglose = [
                'eps'  => ['valor' => $vEps,  'mora' => $mEps],
                'arl'  => ['valor' => $vArl,  'mora' => $mArl],
                'pen'  => ['valor' => $vPen,  'mora' => $mPen],
                'caja' => ['valor' => $vCaja, 'mora' => $mCaja],
            ];
        }

        return response()->json([
            'ok' => true,
            'costo_ss' => $costoSs,
            'mora' => $mora,
            'desglose' => $desglose
        ]);
    }

    // ─── API: Cotizador (devuelve JSON) ───────────────────────────────
    public function cotizar(Request $request)
    {
        $alidoId = session('aliado_id_activo');

        $resultado = \App\Services\CotizadorService::calcular($request->all(), $alidoId);
        unset($resultado['plan_nombre'], $resultado['tipo_modalidad_nombre']);

        return response()->json($resultado);
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
        // Planos ya generados para este contrato (para inhabilitar meses del plano en retiros)
        // Solo inhabilitamos meses que tengan planillas con más de 0 días (evita bloquear por afiliaciones de 0 días).
        $planosExistentes = [];
        if ($excludeContratoId) {
            $planosExistentes = DB::table('planos')
                ->where('contrato_id', $excludeContratoId)
                ->where('num_dias', '>', 0)
                ->whereNull('deleted_at')
                ->select('mes_plano', 'anio_plano')
                ->get()
                ->map(fn($p) => ['mes' => (int)$p->mes_plano, 'anio' => (int)$p->anio_plano])
                ->toArray();
        }

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
        $idsTP = TipoModalidad::where('es_tiempo_parcial', true)->pluck('id')->map(fn($id) => (int)$id)->toArray();
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
            'clientePensionado'         => (int) ($cliente?->pension_id ?? 0) === \App\Models\Pension::ID_PENSIONADO,
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
            'planosExistentes'          => $planosExistentes,
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
            // Se usa DB::table directamente (no Eloquent) para garantizar que el UPDATE
            // persista en SQL Server dentro de la transacción, ya que el modelo $original
            // fue hidratado fuera del scope de la transacción.
            $filasAfectadas = DB::table('contratos')
                ->where('id', $original->id)
                ->where('aliado_id', $alidoId)
                ->where('estado', 'vigente') // Safety check: solo si sigue vigente
                ->update([
                    'estado'           => 'retirado',
                    'motivo_retiro_id' => (int)$validated['motivo_retiro_id'],
                    'fecha_retiro'     => $fechaRetiro,
                    'observacion'      => $validated['observacion'] ?? $original->observacion,
                    'updated_at'       => now(),
                ]);

            if ($filasAfectadas === 0) {
                throw new \RuntimeException('No se pudo marcar el retiro del contrato original. Puede que ya haya sido retirado por otra operación.');
            }

            // Refrescar el objeto en memoria para que el resto del closure use el estado actualizado
            $original->refresh();


            // ── 2. Crear plano de retiro con n_plano = 0 ─────────────────
            $cliente = $original->cliente;
            $eps     = $original->eps;
            $afp     = $original->pension;
            $arl     = $original->arl;
            $caja    = $original->caja;
            $rs      = $original->razonSocial;

            $arlSnapshot = \App\Models\Plano::resolverArlSnapshot($original, $rs);
            $codArl = $arlSnapshot['cod_arl'];
            $nombreArl = $arlSnapshot['nombre_arl'];

            $apellidos = $cliente?->apellidos ?? trim(($cliente?->primer_apellido ?? '') . ' ' . ($cliente?->segundo_apellido ?? ''));
            $nombres   = $cliente?->nombres   ?? trim(($cliente?->primer_nombre   ?? '') . ' ' . ($cliente?->segundo_nombre   ?? ''));
            $partsApe  = preg_split('/\s+/', trim($apellidos), 2);
            $partsNom  = preg_split('/\s+/', trim($nombres),   2);

            // Calcular la cotización para el retiro real (aportes a seguridad social)
            $vEpsRetiro = 0; $vArlRetiro = 0; $vAfpRetiro = 0; $vCajaRetiro = 0; $totalSsRetiro = 0;
            if ($numDias > 0) {
                // Fallback solo cuando ambos son 0 (legacy sin ningún dato).
                // Si solo ibc=0 (dependiente) o solo salario=0 (independiente),
                // calcularCotizacion() lo resuelve internámente según modalidad.
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

            // ── 3. Crear nuevo contrato ──
            $cfgAliado = \App\Models\ConfiguracionAliado::paraAliado($alidoId);
            $diaIngreso = max(1, min(28, (int)($cfgAliado?->dia_ingreso_ir ?? 26)));
            $nuevaFechaIngreso = now()->startOfMonth()->addDays($diaIngreso - 1)->toDateString();

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
     * - Ya está pensionado (fondo "PENSIONADO" en su ficha) — sin importar edad, género ni documento
     * - doc: CE (Cédula Extranjería), PT (Permiso Prot. Temporal), PE (Permiso Especial), PA (Pasaporte)
     * - Hombre ≥ 55 años  |  Mujer ≥ 50 años
     */
    private function detectarExencionAfp(?object $cliente): bool
    {
        // La regla vive en Cliente::motivoExencionAfp() — una sola fuente para admin, web e IA.
        return $cliente instanceof \App\Models\Cliente && $cliente->esExentoAfp();
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
