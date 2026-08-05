<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionAliado;
use App\Models\ArlTarifa;
use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;
use App\Models\Aliado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConfiguracionAliadoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin']);
    }
    /** Hub central de configuración */
    public function hub()
    {
        // Preferir EPS con PDF ya cargado; si ninguna, tomar la primera disponible
        $primeraEps = \App\Models\Eps::select('id','nombre','formulario_pdf')
            ->whereNotNull('formulario_pdf')->orderBy('nombre')->first()
            ?? \App\Models\Eps::select('id','nombre','formulario_pdf')
               ->orderBy('nombre')->first();

        // Obtener el primer operador de planilla disponible para el editor de planillas
        $primerOperador = \App\Models\OperadorPlanilla::orderBy('nombre')->first();

        // Slug del aliado activo, para el link de la tarjeta "Página Web Pública"
        $aliadoActivo = Aliado::select('id', 'slug')->find(session('aliado_id_activo'));

        return view('admin.configuracion.hub', compact('primeraEps', 'primerOperador', 'aliadoActivo'));
    }

    /**
     * Muestra la pantalla de configuración del aliado activo.
     */
    public function index()
    {
        $alidoId = session('aliado_id_activo');

        $planes     = PlanContrato::where('activo', true)->get();
        $usuarios   = \App\Models\User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get();

        // Configuraciones por plan (y la global sin plan)
        $configs    = ConfiguracionAliado::where('aliado_id', $alidoId)
            ->with('plan')
            ->orderBy('plan_id')
            ->get()
            ->keyBy(fn($c) => $c->plan_id ?? 'global');

        // Tarifas ARL del aliado (null = usa global)
        $arlAliado  = ArlTarifa::where('aliado_id', $alidoId)->orderBy('nivel')->get()->keyBy('nivel');
        $arlGlobal  = ArlTarifa::whereNull('aliado_id')->orderBy('nivel')->get()->keyBy('nivel');

        // Config global Brynex (salario mínimo, porcentajes SS)
        $configBrynex = ConfiguracionBrynex::all()->keyBy('clave');

        // Aliado activo (para logo y parámetros especiales)
        $aliadoActual = Aliado::find($alidoId);

        // Afiliación por plan + modalidad + nivel de riesgo ARL (solo planes que incluyen ARL):
        // para cada plan, sus modalidades compatibles (sin las ocultas "solo_ia") y, para cada una,
        // el costo de afiliación configurado en los 5 niveles (null = usa el costo_afiliacion
        // general del plan, ver CotizacionPublicaService::cotizar()).
        $modalidadesArlPorPlan = [];
        $valoresArlExistentes = \App\Models\AfiliacionArlModalidad::where('aliado_id', $alidoId)
            ->get()
            ->keyBy(fn ($v) => $v->plan_id . '_' . $v->tipo_modalidad_id . '_' . $v->nivel_arl);

        foreach ($planes->where('incluye_arl', true) as $plan) {
            $idsModalidad = DB::table('modalidad_planes')
                ->where('plan_id', $plan->id)
                ->where('solo_ia', false)
                ->pluck('tipo_modalidad_id');

            $modalidades = \App\Models\TipoModalidad::whereIn('id', $idsModalidad)
                ->orderBy('orden')
                ->get(['id', 'modalidad', 'observacion'])
                ->map(function ($modalidad) use ($plan, $valoresArlExistentes) {
                    $niveles = [];
                    for ($n = 1; $n <= 5; $n++) {
                        $valor = $valoresArlExistentes->get("{$plan->id}_{$modalidad->id}_{$n}");
                        $niveles[$n] = $valor?->costo_afiliacion;
                    }
                    return [
                        'id'      => $modalidad->id,
                        'nombre'  => $modalidad->observacion ?: $modalidad->modalidad,
                        'niveles' => $niveles,
                    ];
                });

            $modalidadesArlPorPlan[$plan->id] = $modalidades;
        }

        return view('admin.configuracion.index', compact(
            'planes', 'usuarios', 'configs', 'arlAliado', 'arlGlobal', 'configBrynex', 'aliadoActual',
            'modalidadesArlPorPlan'
        ));
    }

    /**
     * Guarda/actualiza la configuración general + por plan del aliado.
     */
    public function store(Request $request)
    {
        $alidoId = session('aliado_id_activo');

        $request->validate([
            'configs.*.administracion'          => 'nullable|numeric|min:0',
            'configs.*.admon_asesor'            => 'nullable|numeric|min:0',
            'configs.*.costo_afiliacion'        => 'nullable|numeric|min:0',
            'configs.*.promocion_costo_afiliacion' => 'nullable|numeric|min:0',
            'configs.*.promocion_vencimiento'      => 'nullable|date',
            'configs.*.dist_admon_pct'          => 'nullable|numeric|min:0|max:100',
            'configs.*.dist_retiro_pct'         => 'nullable|numeric|min:0|max:100',
            'configs.*.seguro_valor'            => 'nullable|numeric|min:0',
            'configs.*.encargado_default_id'    => 'nullable|exists:users,id',
            'configs.*.dia_ingreso_ir'          => 'nullable|integer|min:1|max:28',
            // Mora al cliente
            'configs.*.mora_dia_habil_inicio'   => 'nullable|integer|min:2|max:16',
            'configs.*.mora_minimo'             => 'nullable|numeric|min:0',
            'configs.*.mora_segundo'            => 'nullable|numeric|min:0',
            'arl.*.porcentaje'                  => 'nullable|numeric|min:0|max:100',
            'arl_afiliacion.*.*.*'               => 'nullable|numeric|min:0',
            'brynex.*'                          => 'nullable|numeric|min:0',
            // Sin SVG: se guarda en disco público y un SVG puede llevar <script>
            // embebido, que se ejecuta al abrirlo directamente (XSS).
            'seguro_logo'                       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'recibo_doble_copia'                => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $alidoId) {
            // ── 0. Parámetros del aliado (tabla aliados, no por plan) ──
            // Solo si el formulario trae el campo, para que un POST parcial
            // no apague el interruptor sin querer.
            if ($request->has('recibo_doble_copia')) {
                Aliado::where('id', $alidoId)->update([
                    'recibo_doble_copia' => $request->boolean('recibo_doble_copia'),
                ]);
            }

            // ── 1. Parámetros globales BryNex (solo superadmin + es_brynex) ──
            if (auth()->user()->hasRole('superadmin') && auth()->user()->es_brynex && $request->has('brynex')) {
                foreach ($request->input('brynex', []) as $clave => $valor) {
                    if ($valor !== null && $valor !== '') {
                        ConfiguracionBrynex::establecer($clave, $valor);
                    }
                }
            }

            // ── 2. Configuraciones por plan ──
            foreach ($request->input('configs', []) as $planKey => $data) {
                $planId = ($planKey === 'global') ? null : (int) $planKey;

                ConfiguracionAliado::updateOrCreate(
                    ['aliado_id' => $alidoId, 'plan_id' => $planId],
                    [
                        'administracion'          => $data['administracion']        ?? 0,
                        'admon_asesor'            => $data['admon_asesor']          ?? 0,
                        'costo_afiliacion'        => $data['costo_afiliacion']      ?? 0,
                        'promocion_costo_afiliacion' => ($data['promocion_costo_afiliacion'] ?? '') !== ''
                                                     ? $data['promocion_costo_afiliacion'] : null,
                        'promocion_vencimiento'      => ($data['promocion_vencimiento'] ?? '') !== ''
                                                     ? $data['promocion_vencimiento'] : null,
                        'dist_admon_pct'          => $data['dist_admon_pct']        ?? 0,
                        'dist_retiro_pct'         => $data['dist_retiro_pct']       ?? 0,
                        'seguro_valor'            => $data['seguro_valor']          ?? 0,
                        'encargado_default_id'    => $data['encargado_default_id'] ?: null,
                        'dia_ingreso_ir'          => (($data['dia_ingreso_ir'] ?? null) !== '' && ($data['dia_ingreso_ir'] ?? null) !== null)
                                                     ? (int) $data['dia_ingreso_ir'] : 26,
                        // Mora al cliente
                        'mora_dia_habil_inicio'   => (($data['mora_dia_habil_inicio'] ?? null) !== '' && ($data['mora_dia_habil_inicio'] ?? null) !== null)
                                                     ? (int) $data['mora_dia_habil_inicio'] : null,
                        'mora_minimo'             => $data['mora_minimo']  ?? 2000,
                        'mora_segundo'            => $data['mora_segundo'] ?? 5000,
                        // Un solo valor por aliado (solo tiene sentido en la fila global, pero
                        // guardarlo también en filas por plan no hace daño: nunca se leen de ahí).
                        'activo'                  => true,
                    ]
                );
            }

            // ── 3. Tarifas ARL personalizadas (solo superadmin + es_brynex) ──
            if (auth()->user()->hasRole('superadmin') && auth()->user()->es_brynex) {
                foreach ($request->input('arl', []) as $nivel => $data) {
                    $pct = $data['porcentaje'] ?? null;
                    if ($pct === null || $pct === '') {
                        ArlTarifa::where('aliado_id', $alidoId)->where('nivel', $nivel)->delete();
                    } else {
                        ArlTarifa::updateOrCreate(
                            ['aliado_id' => $alidoId, 'nivel' => $nivel],
                            ['porcentaje' => $pct, 'descripcion' => $data['descripcion'] ?? null]
                        );
                    }
                }
            }

            // ── 3b. Afiliación por plan + modalidad + nivel de riesgo ARL ──
            // arl_afiliacion[plan_id][tipo_modalidad_id][nivel] = valor. Vacío = elimina la fila
            // (vuelve a caer al costo_afiliacion general del plan, ver CotizacionPublicaService).
            foreach ($request->input('arl_afiliacion', []) as $planId => $porModalidad) {
                foreach ($porModalidad as $modalidadId => $porNivel) {
                    foreach ($porNivel as $nivel => $valor) {
                        if ($valor === null || $valor === '') {
                            \App\Models\AfiliacionArlModalidad::where('aliado_id', $alidoId)
                                ->where('plan_id', $planId)
                                ->where('tipo_modalidad_id', $modalidadId)
                                ->where('nivel_arl', $nivel)
                                ->delete();
                        } else {
                            \App\Models\AfiliacionArlModalidad::updateOrCreate(
                                ['aliado_id' => $alidoId, 'plan_id' => $planId, 'tipo_modalidad_id' => $modalidadId, 'nivel_arl' => $nivel],
                                ['costo_afiliacion' => $valor]
                            );
                        }
                    }
                }
            }
        });

        // ── 4. Logo de la aseguradora (fuera de la transacción para manejar archivos) ──
        if ($request->hasFile('seguro_logo') && $request->file('seguro_logo')->isValid()) {
            $configGlobal = ConfiguracionAliado::where('aliado_id', $alidoId)
                ->whereNull('plan_id')
                ->first();

            if ($configGlobal) {
                // Eliminar logo anterior si existe
                if ($configGlobal->seguro_logo && Storage::disk('public')->exists($configGlobal->seguro_logo)) {
                    Storage::disk('public')->delete($configGlobal->seguro_logo);
                }
                $path = $request->file('seguro_logo')->store('aliados/seguros', 'public');
                $configGlobal->update(['seguro_logo' => $path]);
            }
        }

        return redirect()->route('admin.configuracion.index')
            ->with('success', 'Configuración guardada correctamente.');
    }

    // ─── Cuentas Bancarias ────────────────────────────────────────────
    public function cuentas()
    {
        $alidoId = session('aliado_id_activo');
        $cuentas = \App\Models\BancoCuenta::where('aliado_id', $alidoId)
            ->orderBy('banco')->get();
        return view('admin.configuracion.cuentas', compact('cuentas'));
    }

    public function storeCuenta(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $v = $request->validate([
            'banco'        => 'required|string|max:100',
            'nombre'       => 'nullable|string|max:150',
            'nit'          => 'nullable|string|max:20',
            'tipo_cuenta'  => 'nullable|in:Ahorros,Corriente',
            'numero_cuenta'=> 'required|string|max:30',
            'activo'       => 'boolean',
            'cobro'        => 'boolean',
            'facturacion'  => 'boolean',
            'incapacidad'  => 'boolean',
            'observacion'  => 'nullable|string|max:300',
            'llave'        => 'nullable|string|max:100',
        ]);
        $v['aliado_id']   = $alidoId;
        $v['activo']      = $request->boolean('activo');
        $v['cobro']       = $request->boolean('cobro');
        $v['facturacion'] = $request->boolean('facturacion');
        $v['incapacidad'] = $request->boolean('incapacidad');
        \App\Models\BancoCuenta::create($v);
        return redirect()->route('admin.configuracion.cuentas')
            ->with('success', 'Cuenta bancaria creada.');
    }

    public function updateCuenta(Request $request, int $id)
    {
        $alidoId = session('aliado_id_activo');
        $cuenta  = \App\Models\BancoCuenta::where('aliado_id', $alidoId)->findOrFail($id);
        $v = $request->validate([
            'banco'        => 'required|string|max:100',
            'nombre'       => 'nullable|string|max:150',
            'nit'          => 'nullable|string|max:20',
            'tipo_cuenta'  => 'nullable|in:Ahorros,Corriente',
            'numero_cuenta'=> 'required|string|max:30',
            'activo'       => 'boolean',
            'cobro'        => 'boolean',
            'facturacion'  => 'boolean',
            'incapacidad'  => 'boolean',
            'observacion'  => 'nullable|string|max:300',
            'llave'        => 'nullable|string|max:100',
        ]);
        $v['activo']      = $request->boolean('activo');
        $v['cobro']       = $request->boolean('cobro');
        $v['facturacion'] = $request->boolean('facturacion');
        $v['incapacidad'] = $request->boolean('incapacidad');
        $cuenta->update($v);
        // Si petición AJAX (fetch) devuelve JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('admin.configuracion.cuentas')
            ->with('success', 'Cuenta actualizada.');
    }

    public function destroyCuenta(int $id)
    {
        $alidoId = session('aliado_id_activo');
        $cuenta  = \App\Models\BancoCuenta::where('aliado_id', $alidoId)->findOrFail($id);

        // Verificar que no tenga registros en consignaciones ni anticipos
        $tieneRegistros = DB::table('consignaciones')->where('banco_cuenta_id', $id)->exists()
            || DB::table('consignaciones')->where('banco_cuenta2_id', $id)->exists()
            || DB::table('anticipos')->where('banco_cuenta_id', $id)->exists();

        if ($tieneRegistros) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Esta cuenta tiene registros asociados (facturas o consignaciones). Solo puede inactivarse.'
            ], 422);
        }

        $cuenta->delete();
        return response()->json(['ok' => true]);
    }

    public function inactivarCuenta(int $id)
    {
        $alidoId = session('aliado_id_activo');
        $cuenta  = \App\Models\BancoCuenta::where('aliado_id', $alidoId)->findOrFail($id);
        $cuenta->update(['activo' => false]);
        return response()->json(['ok' => true]);
    }

    public function estadoCuentaContratos(int $id)
    {
        $alidoId = session('aliado_id_activo');
        $cuenta  = \App\Models\BancoCuenta::where('aliado_id', $alidoId)->find($id);

        if (!$cuenta) {
            return response()->json(['error' => 'No encontrada'], 404);
        }

        $tieneRegistros = DB::table('consignaciones')->where('banco_cuenta_id', $id)->exists()
            || DB::table('consignaciones')->where('banco_cuenta2_id', $id)->exists()
            || DB::table('anticipos')->where('banco_cuenta_id', $id)->exists();

        return response()->json([
            'banco'          => $cuenta->banco,
            'numero_cuenta'  => $cuenta->numero_cuenta,
            'activo'         => (bool) $cuenta->activo,
            'tiene_registros'=> $tieneRegistros,
            'puede_eliminar' => !$tieneRegistros,
            'puede_inactivar'=> (bool) $cuenta->activo,
        ]);
    }
}

