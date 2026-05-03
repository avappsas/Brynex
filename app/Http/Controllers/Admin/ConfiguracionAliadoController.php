<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionAliado;
use App\Models\ArlTarifa;
use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('admin.configuracion.hub', compact('primeraEps'));
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

        return view('admin.configuracion.index', compact(
            'planes', 'usuarios', 'configs', 'arlAliado', 'arlGlobal', 'configBrynex'
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
            'configs.*.dist_admon_pct'          => 'nullable|numeric|min:0|max:100',
            'configs.*.dist_retiro_pct'         => 'nullable|numeric|min:0|max:100',
            'configs.*.seguro_valor'            => 'nullable|numeric|min:0',
            'configs.*.encargado_default_id'    => 'nullable|exists:users,id',
            // Mora al cliente
            'configs.*.mora_dia_habil_inicio'   => 'nullable|integer|min:2|max:16',
            'configs.*.mora_minimo'             => 'nullable|numeric|min:0',
            'configs.*.mora_segundo'            => 'nullable|numeric|min:0',
            'arl.*.porcentaje'                  => 'nullable|numeric|min:0|max:100',
            'brynex.*'                          => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $alidoId) {
            // ── 1. Parámetros globales BryNex (solo superadmin) ──
            if (auth()->user()->hasRole('superadmin') && $request->has('brynex')) {
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
                        'dist_admon_pct'          => $data['dist_admon_pct']        ?? 0,
                        'dist_retiro_pct'         => $data['dist_retiro_pct']       ?? 0,
                        'seguro_valor'            => $data['seguro_valor']          ?? 0,
                        'encargado_default_id'    => $data['encargado_default_id'] ?: null,
                        // Mora al cliente
                        'mora_dia_habil_inicio'   => ($data['mora_dia_habil_inicio'] !== '' && $data['mora_dia_habil_inicio'] !== null)
                                                     ? (int) $data['mora_dia_habil_inicio'] : null,
                        'mora_minimo'             => $data['mora_minimo']  ?? 2000,
                        'mora_segundo'            => $data['mora_segundo'] ?? 5000,
                        'activo'                  => true,
                    ]
                );
            }

            // ── 3. Tarifas ARL personalizadas ──
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
        });

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
            'tipo_cuenta'  => 'required|in:Ahorros,Corriente',
            'numero_cuenta'=> 'required|string|max:30',
            'activo'       => 'boolean',
            'cobro'        => 'boolean',
            'observacion'  => 'nullable|string|max:300',
        ]);
        $v['aliado_id'] = $alidoId;
        $v['activo']    = $request->boolean('activo', true);
        $v['cobro']     = $request->boolean('cobro', false);
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
            'tipo_cuenta'  => 'required|in:Ahorros,Corriente',
            'numero_cuenta'=> 'required|string|max:30',
            'activo'       => 'boolean',
            'cobro'        => 'boolean',
            'observacion'  => 'nullable|string|max:300',
        ]);
        $v['activo'] = $request->boolean('activo', true);
        $v['cobro']  = $request->boolean('cobro', false);
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
        \App\Models\BancoCuenta::where('aliado_id', $alidoId)->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}

