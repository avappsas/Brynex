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
            $this->datosFormulario($alidoId, $cliente, null),
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

        DB::transaction(function () use ($data) {
            $contrato = Contrato::create($data);
            // Generar radicados pendientes según el plan
            $contrato->load('plan');
            $contrato->crearRadicadosPendientes();
        });

        return redirect()->route('admin.contratos.index')
            ->with('success', 'Contrato creado correctamente. Se generaron los radicados pendientes.');
    }

    // ─── Formulario editar ────────────────────────────────────────────
    public function edit(int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)->with(['cliente','radicados.user'])->findOrFail($id);
        $cliente  = $contrato->cliente;

        return view('admin.contratos.form', array_merge(
            $this->datosFormulario($alidoId, $cliente, $contrato->razon_social_id),
            compact('contrato', 'cliente')
        ));
    }

    // ─── Actualizar contrato ──────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)->findOrFail($id);
        $data     = $this->validar($request, $contrato);

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

        DB::transaction(function () use ($contrato, $data) {
            $oldPlanId = $contrato->plan_id;
            $contrato->update($data);

            // Si cambió el plan, agregar nuevos radicados pendientes
            if (isset($data['plan_id']) && $data['plan_id'] != $oldPlanId) {
                $contrato->load('plan');
                $contrato->crearRadicadosPendientes();
            }
        });

        return redirect()->route('admin.contratos.edit', $id)
            ->with('success', 'Contrato actualizado correctamente.');
    }

    // ─── Retirar contrato ─────────────────────────────────────────────
    public function retirar(Request $request, int $id)
    {
        $alidoId  = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $alidoId)->findOrFail($id);

        $request->validate([
            'motivo_retiro_id' => 'required|exists:motivos_retiro,id',
            'fecha_retiro'     => 'required|date',
            'observacion'      => 'nullable|string|max:500',
        ]);

        $contrato->update([
            'estado'           => 'retirado',
            'motivo_retiro_id' => $request->motivo_retiro_id,
            'fecha_retiro'     => $request->fecha_retiro,
            'observacion'      => $request->observacion ?? $contrato->observacion,
        ]);

        return redirect()->route('admin.contratos.edit', $id)
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

        // Calculos por mes completo
        $epsMes  = ($plan && $plan->incluye_eps)      ? $r($ibc * $pctEps  / 100) : 0;
        $arlMes  = ($plan && $plan->incluye_arl)      ? $r($ibc * $pctArl  / 100) : 0;
        $penMes  = ($plan && $plan->incluye_pension)   ? $r($ibc * $pctPen  / 100) : 0;
        $cajaMes = ($plan && $plan->incluye_caja)     ? $r($ibc * $pctCaja / 100) : 0;

        // Prorratear por dias cotizados (dias/30); admon y seguro siempre completos
        $eps  = $dias < 30 ? $r($epsMes  * $dias / 30) : $epsMes;
        $arl  = $dias < 30 ? $r($arlMes  * $dias / 30) : $arlMes;
        $pen  = $dias < 30 ? $r($penMes  * $dias / 30) : $penMes;
        $caja = $dias < 30 ? $r($cajaMes * $dias / 30) : $cajaMes;
        $ss   = $eps + $arl + $pen + $caja;

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
            'eps'         => $eps,
            'arl'         => $arl,
            'pen'         => $pen,
            'caja'        => $caja,
            'ss'          => $ss,
            'seguro'      => $seguro,
            'admon'       => $admonTotal,
            'admonBase'   => $admon,
            'admonAsesor' => $admonAsesor,
            'iva'         => $iva,
            'total'       => $total,
            'dias'        => $dias,
            'epsMes'      => $epsMes,
            'arlMes'      => $arlMes,
            'penMes'      => $penMes,
            'cajaMes'     => $cajaMes,
            'ibcSugerido' => $ibcSugerido,
            'pctEps'      => $pctEps,
            'pctPen'      => $pctPen,
            'pctArl'      => $pctArl,
            'pctCaja'     => $pctCaja,
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
    private function datosFormulario(int $alidoId, ?object $cliente = null, ?int $razonSocialId = null): array
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

        return [
            'razonesSociales'           => RazonSocial::where('aliado_id', $alidoId)->orderBy('razon_social')->get(),
            'asesores'                  => Asesor::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get(),
            'epsList'                   => Eps::orderBy('nombre')->get(),
            'pensiones'                 => Pension::orderBy('razon_social')->get(),
            'arlList'                   => Arl::orderBy('nombre_arl')->get(),
            'cajas'                     => Caja::orderBy('nombre')->get(),
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
            // Defaults de tarifas
            'defaultTarifas'            => Contrato::tarifasParaAliado($alidoId, null),
            'bancos'                    => BancoCuenta::activas($alidoId),
        ];
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
            'cobra_planilla_primer_mes' => 'boolean',
        ]);
    }
}
