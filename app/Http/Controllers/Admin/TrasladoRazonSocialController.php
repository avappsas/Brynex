<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Factura;
use App\Models\Plano;
use App\Models\RazonSocial;
use App\Models\User;
use App\Services\PlanoPilaTxtService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TrasladoRazonSocialController
 *
 * Flujo:
 *  1. index()         → Vista principal (selección RS origen + pegar cédulas)
 *  2. validar()       → API: busca contratos vigentes en la RS origen
 *  3. ejecutar()      → Crea nuevos contratos + planos de afiliación (costo 0)
 *  4. retirarOpcionA() → Duplica el último plano con fecha_ret + marca retiro
 *  5. retirarOpcionB() → Crea plano de retiro futuro + marca retiro
 *  6. descargarPlano() → Genera TXT MiPlanilla con novedades ING+RET (opción A)
 */
class TrasladoRazonSocialController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin']);
    }

    // ─── 1. Vista principal ───────────────────────────────────────────────────
    public function index(): \Illuminate\View\View
    {
        $aliadoId = session('aliado_id_activo');

        $razonesSociales = RazonSocial::where('aliado_id', $aliadoId)
            ->orderByRaw("CASE WHEN estado = 'Activa' THEN 0 ELSE 1 END")
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'estado', 'n_plano']);

        $usuarios = User::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $tienePivotOp = DB::table('aliado_operadores_planilla')->where('aliado_id', $aliadoId)->exists();
        if ($tienePivotOp) {
            $operadores = DB::table('operadores_planilla AS op')
                ->join('aliado_operadores_planilla AS piv',
                    fn($j) => $j->on('piv.operador_id', '=', 'op.id')
                                 ->where('piv.aliado_id', $aliadoId)
                                 ->where('piv.activo', true))
                ->whereNull('op.aliado_id')
                ->where('op.activo', true)
                ->orderBy('op.orden')
                ->select('op.*')
                ->get();
        } else {
            $operadores = DB::table('operadores_planilla')
                ->whereNull('aliado_id')
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'nombre', 'codigo_ni']);
        }

        return view('admin.traslados.index', compact(
            'razonesSociales', 'usuarios', 'operadores'
        ));
    }

    // ─── 2. API: Validar cédulas en la RS origen ──────────────────────────────
    public function validar(Request $request): JsonResponse
    {
        $aliadoId = session('aliado_id_activo');

        $request->validate([
            'razon_social_origen_id' => 'required',
            'cedulas'                => 'required|string',
        ]);

        $rsOrigenId = (int) $request->input('razon_social_origen_id');

        // Verificar que la RS pertenece al aliado
        $rsOrigen = RazonSocial::where('aliado_id', $aliadoId)->find($rsOrigenId);
        if (!$rsOrigen) {
            return response()->json(['ok' => false, 'mensaje' => 'Razón Social no encontrada.'], 422);
        }

        // Parsear cédulas: separadas por salto de línea, coma, punto y coma o espacio
        $raw    = $request->input('cedulas');
        $partes = array_filter(
            array_map('trim', preg_split('/[\n\r,;]+/', $raw)),
            fn($c) => $c !== ''
        );
        $cedulas = array_values(array_unique(array_map(
            fn($c) => preg_replace('/\D/', '', $c),
            $partes
        )));
        $cedulas = array_filter($cedulas, fn($c) => $c !== '');

        if (empty($cedulas)) {
            return response()->json(['ok' => false, 'mensaje' => 'No se encontraron cédulas válidas.'], 422);
        }

        // Buscar contratos VIGENTES de esas cédulas en la RS origen
        $contratos = DB::table('contratos AS c')
            ->leftJoin('clientes AS cl', function ($j) use ($aliadoId) {
                $j->on(DB::raw('CAST(cl.cedula AS VARCHAR(20))'), '=', DB::raw('CAST(c.cedula AS VARCHAR(20))'))
                  ->where('cl.aliado_id', $aliadoId);
            })
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'c.razon_social_id')
            ->leftJoin('eps AS e',         'e.id',  '=', 'c.eps_id')
            ->leftJoin('pensiones AS p',   'p.id',  '=', 'c.pension_id')
            ->leftJoin('arls AS a',        'a.id',  '=', 'c.arl_id')
            ->leftJoin('cajas AS cj',      'cj.id', '=', 'c.caja_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->leftJoin('planes_contrato AS pc', 'pc.id', '=', 'c.plan_id')
            ->leftJoin('users AS uc',      'uc.id', '=', 'c.encargado_id')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.razon_social_id', $rsOrigenId)
            ->where('c.estado', 'vigente')
            ->whereIn(DB::raw('CAST(c.cedula AS VARCHAR(20))'), $cedulas)
            ->select([
                'c.id AS contrato_id',
                DB::raw('CAST(c.cedula AS VARCHAR(20)) AS cedula'),
                'c.plan_id',
                'c.tipo_modalidad_id',
                'c.eps_id',
                'c.pension_id',
                'c.arl_id',
                'c.n_arl',
                'c.arl_modo',
                'c.arl_nit_cotizante',
                'c.caja_id',
                'c.salario',
                'c.ibc',
                'c.porcentaje_caja',
                'c.administracion',
                'c.admon_asesor',
                'c.costo_afiliacion',
                'c.seguro',
                'c.asesor_id',
                'c.encargado_id',
                'c.motivo_afiliacion_id',
                'c.cargo',
                'c.actividad_economica_id',
                'c.envio_planilla',
                'c.fecha_probable_pago',
                'c.modo_probable_pago',
                'c.cobra_planilla_primer_mes',
                DB::raw("CONVERT(VARCHAR(10), c.fecha_ingreso, 23) AS fecha_ingreso"),
                'c.observacion',
                'c.np',
                'c.razon_social_id',
                // Nombre completo del cliente (snapshot)
                DB::raw("ISNULL(LTRIM(RTRIM(
                    ISNULL(cl.primer_nombre,'') + ' ' +
                    ISNULL(cl.segundo_nombre,'') + ' ' +
                    ISNULL(cl.primer_apellido,'') + ' ' +
                    ISNULL(cl.segundo_apellido,'')
                )), '') AS nombre_completo"),
                'cl.tipo_doc',
                // Entidades
                'rs.razon_social AS rs_nombre',
                'e.nombre AS eps_nombre',
                'p.razon_social AS pension_nombre',
                'a.nombre_arl AS arl_nombre',
                'cj.nombre AS caja_nombre',
                'tm.tipo_modalidad AS modalidad_nombre',
                'pc.nombre AS plan_nombre',
                'uc.nombre AS encargado_nombre',
            ])
            ->get();

        // Cédulas no encontradas
        $cedulasEncontradas = $contratos->pluck('cedula')->map(fn($c) => (string)$c)->toArray();
        $noEncontradas = array_values(array_diff(
            array_map('strval', $cedulas),
            $cedulasEncontradas
        ));

        return response()->json([
            'ok'             => true,
            'contratos'      => $contratos,
            'no_encontradas' => $noEncontradas,
            'rs_origen'      => $rsOrigen->razon_social,
            'total'          => $contratos->count(),
        ]);
    }

    // ─── 3. Ejecutar traslado: crear nuevos contratos + planos de afiliación ──
    public function ejecutar(Request $request): JsonResponse
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        $validated = $request->validate([
            'contrato_ids'           => 'required|array|min:1',
            'contrato_ids.*'         => 'integer',
            'razon_social_destino_id'=> 'required|integer',
            'encargado_id'           => 'required|integer',
        ]);

        $rsDestino = RazonSocial::where('aliado_id', $aliadoId)
            ->find($validated['razon_social_destino_id']);
        if (!$rsDestino) {
            return response()->json(['ok' => false, 'mensaje' => 'Razón Social destino no encontrada.'], 404);
        }

        $encargado = User::where('aliado_id', $aliadoId)->find($validated['encargado_id']);
        if (!$encargado) {
            return response()->json(['ok' => false, 'mensaje' => 'Encargado no encontrado.'], 404);
        }

        // Fecha de ingreso = 1 del mes actual
        $fechaIngreso = Carbon::now()->startOfMonth()->toDateString();

        $nuevosContratos = [];
        $errores         = [];

        DB::transaction(function () use (
            $validated, $aliadoId, $usuarioId, $rsDestino, $encargado,
            $fechaIngreso, &$nuevosContratos, &$errores
        ) {
            $contratos = Contrato::where('aliado_id', $aliadoId)
                ->whereIn('id', $validated['contrato_ids'])
                ->where('estado', 'vigente')
                ->with(['plan', 'eps', 'pension', 'arl', 'caja', 'cliente'])
                ->get();

            foreach ($contratos as $contratoOrigen) {
                try {
                    // ── Crear nuevo contrato copiando todos los campos relevantes ──
                    $nuevoContrato = Contrato::create([
                        'aliado_id'               => $aliadoId,
                        'cedula'                  => $contratoOrigen->cedula,
                        'estado'                  => 'vigente',
                        // Nueva RS, encargado y fecha de ingreso
                        'razon_social_id'         => $rsDestino->id,
                        'razon_social_bloqueada'  => false,
                        'encargado_id'            => $encargado->id,
                        'fecha_ingreso'           => $fechaIngreso,
                        // Datos copiados del contrato original
                        'plan_id'                 => $contratoOrigen->plan_id,
                        'tipo_modalidad_id'       => $contratoOrigen->tipo_modalidad_id,
                        'eps_id'                  => $contratoOrigen->eps_id,
                        'pension_id'              => $contratoOrigen->pension_id,
                        'arl_id'                  => $contratoOrigen->arl_id,
                        'n_arl'                   => $contratoOrigen->n_arl,
                        'arl_modo'                => $contratoOrigen->arl_modo,
                        // ARL NIT cotizante: si era por RS, usar la nueva RS
                        'arl_nit_cotizante'       => ($contratoOrigen->arl_modo === 'razon_social')
                            ? (int) $rsDestino->id
                            : $contratoOrigen->arl_nit_cotizante,
                        'caja_id'                 => $contratoOrigen->caja_id,
                        'cargo'                   => $contratoOrigen->cargo,
                        'salario'                 => $contratoOrigen->salario,
                        'ibc'                     => $contratoOrigen->ibc,
                        'porcentaje_caja'         => $contratoOrigen->porcentaje_caja,
                        'administracion'          => $contratoOrigen->administracion,
                        'admon_asesor'            => $contratoOrigen->admon_asesor,
                        'costo_afiliacion'        => $contratoOrigen->costo_afiliacion,
                        'seguro'                  => $contratoOrigen->seguro,
                        'asesor_id'               => $contratoOrigen->asesor_id,
                        'motivo_afiliacion_id'    => $contratoOrigen->motivo_afiliacion_id,
                        'actividad_economica_id'  => $contratoOrigen->actividad_economica_id,
                        'envio_planilla'          => $contratoOrigen->envio_planilla,
                        'fecha_probable_pago'     => $contratoOrigen->fecha_probable_pago,
                        'modo_probable_pago'      => $contratoOrigen->modo_probable_pago,
                        'cobra_planilla_primer_mes' => $contratoOrigen->cobra_planilla_primer_mes,
                        'np'                      => $contratoOrigen->np,
                        'observacion'             => $contratoOrigen->observacion,
                        'observacion_afiliacion'  => "Traslado desde RS #{$contratoOrigen->razon_social_id}. Contrato origen #{$contratoOrigen->id}.",
                        'fecha_created'           => now(),
                    ]);

                    // ── Crear factura de afiliación con costo 0 ──
                    $cliente  = $contratoOrigen->cliente;
                    $arl      = $contratoOrigen->arl;
                    $rs       = $rsDestino;

                    $codArl    = $rs->arl_nit ?? $arl?->nit ?? $arl?->codigo_arl ?? null;
                    $nombreArl = null;
                    if ($rs->arl_nit) {
                        $nombreArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('nombre_arl');
                    }
                    if (!$nombreArl) {
                        $nombreArl = $arl?->nombre_arl ?? null;
                    }

                    $eps  = $contratoOrigen->eps;
                    $afp  = $contratoOrigen->pension;
                    $caja = $contratoOrigen->caja;

                    $facturaAfil = Factura::create([
                        'aliado_id'        => $aliadoId,
                        'numero_factura'   => 0,
                        'tipo'             => 'afiliacion',
                        'cedula'           => $nuevoContrato->cedula,
                        'contrato_id'      => $nuevoContrato->id,
                        'razon_social_id'  => $rsDestino->id,
                        'empresa_id'       => null,
                        'mes'              => now()->month,
                        'anio'             => now()->year,
                        'fecha_pago'       => now()->toDateString(),
                        'estado'           => 'pagada',
                        'forma_pago'       => 'efectivo',
                        'valor_efectivo'   => 0,
                        'valor_consignado' => 0,
                        'valor_prestamo'   => 0,
                        'dias_cotizados'   => 0,
                        'v_eps'   => 0, 'v_arl'  => 0, 'v_afp'  => 0, 'v_caja' => 0,
                        'total_ss'=> 0, 'admon'  => 0, 'admin_asesor' => 0,
                        'otros_admon' => 0, 'seguro' => 0, 'afiliacion' => 0,
                        'mensajeria' => 0, 'otros' => 0, 'mora' => 0, 'iva' => 0,
                        'total'       => 0,
                        'saldo_proximo'=> 0,
                        'n_plano'     => $rsDestino->n_plano ?? 1,
                        'razon_social_id' => $rsDestino->id,
                        'usuario_id'  => $usuarioId,
                        'observacion' => "Afiliación por traslado de RS. Contrato #{$nuevoContrato->id}.",
                    ]);

                    // ── Crear plano de afiliación ──
                    $apellidos = $cliente?->apellidos ?? trim(($cliente?->primer_apellido ?? '') . ' ' . ($cliente?->segundo_apellido ?? ''));
                    $nombres   = $cliente?->nombres   ?? trim(($cliente?->primer_nombre   ?? '') . ' ' . ($cliente?->segundo_nombre   ?? ''));
                    $partsApe  = preg_split('/\s+/', trim($apellidos), 2);
                    $partsNom  = preg_split('/\s+/', trim($nombres),   2);

                    Plano::create([
                        'factura_id'        => $facturaAfil->id,
                        'contrato_id'       => $nuevoContrato->id,
                        'aliado_id'         => $aliadoId,
                        'numero_factura'    => 0,
                        'tipo_reg'          => 'afiliacion',
                        'tipo_doc'          => strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC',
                        'no_identifi'       => $nuevoContrato->cedula,
                        'primer_ape'        => strtoupper($partsApe[0] ?? ''),
                        'segundo_ape'       => strtoupper($partsApe[1] ?? ''),
                        'primer_nombre'     => strtoupper($partsNom[0] ?? ''),
                        'segundo_nombre'    => strtoupper($partsNom[1] ?? ''),
                        'fecha_ing'         => $fechaIngreso,
                        'fecha_ret'         => null,
                        'num_dias'          => 0,
                        'cod_eps'           => $eps?->nit  ?? $eps?->cod_eps  ?? null,
                        'nombre_eps'        => $eps?->nombre ?? null,
                        'cod_afp'           => $afp?->nit  ?? $afp?->cod_afp  ?? null,
                        'nombre_afp'        => $afp?->razon_social ?? null,
                        'cod_arl'           => $codArl,
                        'nombre_arl'        => $nombreArl,
                        'cod_caja'          => $caja?->nit ?? $caja?->cod_caja ?? null,
                        'nombre_caja'       => $caja?->nombre ?? null,
                        'nivel_riesgo'      => $nuevoContrato->n_arl ?? 1,
                        'salario_basico'    => (int)($nuevoContrato->salario ?? 0),
                        'n_plano'           => $rsDestino->n_plano ?? 1,
                        'mes_plano'         => now()->month,
                        'anio_plano'        => now()->year,
                        'razon_social'      => $rsDestino->razon_social,
                        'razon_social_id'   => $rsDestino->id,
                        'tipo_p'            => $nuevoContrato->tipo_modalidad_id,
                        'tipo_modalidad_id' => $nuevoContrato->tipo_modalidad_id,
                        'usuario_id'        => $usuarioId,
                    ]);

                    // ── Crear radicados pendientes del nuevo contrato ──
                    $nuevoContrato->load('plan');
                    $nuevoContrato->crearRadicadosPendientes();

                    $nuevosContratos[] = [
                        'contrato_id_nuevo'   => $nuevoContrato->id,
                        'contrato_id_origen'  => $contratoOrigen->id,
                        'cedula'              => $nuevoContrato->cedula,
                        'factura_afil_id'     => $facturaAfil->id,
                    ];

                } catch (\Throwable $e) {
                    $errores[] = [
                        'cedula'  => $contratoOrigen->cedula,
                        'mensaje' => $e->getMessage(),
                    ];
                }
            }
        });

        if (!empty($errores) && empty($nuevosContratos)) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se pudo crear ningún contrato.',
                'errores' => $errores,
            ], 500);
        }

        return response()->json([
            'ok'               => true,
            'nuevos_contratos' => $nuevosContratos,
            'errores'          => $errores,
            'mensaje'          => count($nuevosContratos) . ' contrato(s) creado(s) correctamente en ' . $rsDestino->razon_social . '.',
        ]);
    }

    // ─── 4a. Retiro Opción A: duplicar plano con fecha_ret ───────────────────
    // Crea nueva factura de retiro (numero_factura=0, costo=0) con plano que
    // tiene la fecha_ret y duplica los datos del último plano de la planilla.
    public function retirarOpcionA(Request $request): JsonResponse
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        $validated = $request->validate([
            'contrato_ids'    => 'required|array|min:1',
            'contrato_ids.*'  => 'integer',
            'fecha_retiro'    => 'required|date',
            'mes_plano'       => 'required|integer|between:1,12',
            'anio_plano'      => 'required|integer|min:2020|max:2099',
            'n_plano'         => 'required|integer|min:1',
        ]);

        $fechaRetiro = $validated['fecha_retiro'];
        $mesPlan     = (int) $validated['mes_plano'];
        $anioPlan    = (int) $validated['anio_plano'];
        $nPlano      = (int) $validated['n_plano'];

        $procesados = [];
        $errores    = [];

        DB::transaction(function () use (
            $validated, $aliadoId, $usuarioId, $fechaRetiro, $mesPlan, $anioPlan, $nPlano,
            &$procesados, &$errores
        ) {
            $contratos = Contrato::where('aliado_id', $aliadoId)
                ->whereIn('id', $validated['contrato_ids'])
                ->where('estado', 'vigente')
                ->with(['eps', 'pension', 'arl', 'caja', 'razonSocial', 'cliente'])
                ->get();

            foreach ($contratos as $contrato) {
                try {
                    // Buscar el último plano de planilla de este contrato
                    $ultimoPlano = DB::table('planos')
                        ->where('contrato_id', $contrato->id)
                        ->where('aliado_id', $aliadoId)
                        ->whereIn('tipo_reg', ['planilla', 'retiro'])
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first();

                    $cliente  = $contrato->cliente;
                    $eps      = $contrato->eps;
                    $afp      = $contrato->pension;
                    $arl      = $contrato->arl;
                    $caja     = $contrato->caja;
                    $rs       = $contrato->razonSocial;

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

                    // Nueva factura de retiro con numero_factura=0, costo=0
                    $facturaRet = Factura::create([
                        'aliado_id'        => $aliadoId,
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
                        'dias_cotizados'   => $ultimoPlano?->num_dias ?? 30,
                        'v_eps'   => 0, 'v_arl'  => 0, 'v_afp'  => 0, 'v_caja' => 0,
                        'total_ss'=> 0, 'admon'  => 0, 'admin_asesor' => 0,
                        'otros_admon' => 0, 'seguro' => 0, 'afiliacion' => 0,
                        'mensajeria' => 0, 'otros' => 0, 'mora' => 0, 'iva' => 0,
                        'total'        => 0,
                        'saldo_proximo'=> 0,
                        'n_plano'      => $nPlano,
                        'usuario_id'   => $usuarioId,
                        'observacion'  => "Corrección planilla: retiro por traslado RS. Fecha retiro: {$fechaRetiro}.",
                    ]);

                    // Plano de retiro: copia del último plano con fecha_ret
                    Plano::create([
                        'factura_id'        => $facturaRet->id,
                        'contrato_id'       => $contrato->id,
                        'aliado_id'         => $aliadoId,
                        'numero_factura'    => 0,
                        'tipo_reg'          => 'retiro',
                        'tipo_doc'          => $ultimoPlano?->tipo_doc ?? (strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC'),
                        'no_identifi'       => $contrato->cedula,
                        'primer_ape'        => strtoupper($partsApe[0] ?? ''),
                        'segundo_ape'       => strtoupper($partsApe[1] ?? ''),
                        'primer_nombre'     => strtoupper($partsNom[0] ?? ''),
                        'segundo_nombre'    => strtoupper($partsNom[1] ?? ''),
                        'fecha_ing'         => null,
                        'fecha_ret'         => Carbon::parse($fechaRetiro)->toDateString(),
                        'num_dias'          => $ultimoPlano?->num_dias ?? 30,
                        'cod_eps'           => $ultimoPlano?->cod_eps  ?? ($eps?->nit  ?? $eps?->cod_eps  ?? null),
                        'nombre_eps'        => $ultimoPlano?->nombre_eps ?? $eps?->nombre ?? null,
                        'cod_afp'           => $ultimoPlano?->cod_afp  ?? ($afp?->nit  ?? $afp?->cod_afp  ?? null),
                        'nombre_afp'        => $ultimoPlano?->nombre_afp ?? $afp?->razon_social ?? null,
                        'cod_arl'           => $ultimoPlano?->cod_arl  ?? $codArl,
                        'nombre_arl'        => $ultimoPlano?->nombre_arl ?? $nombreArl,
                        'cod_caja'          => $ultimoPlano?->cod_caja ?? ($caja?->nit ?? $caja?->cod_caja ?? null),
                        'nombre_caja'       => $ultimoPlano?->nombre_caja ?? $caja?->nombre ?? null,
                        'nivel_riesgo'      => $ultimoPlano?->nivel_riesgo ?? $contrato->n_arl ?? 1,
                        'salario_basico'    => $ultimoPlano?->salario_basico ?? (int)($contrato->salario ?? 0),
                        'n_plano'           => $nPlano,
                        'mes_plano'         => $mesPlan,
                        'anio_plano'        => $anioPlan,
                        'razon_social'      => $rs?->razon_social ?? null,
                        'tipo_p'            => 16,
                        'tipo_modalidad_id' => $contrato->tipo_modalidad_id,
                        'usuario_id'        => $usuarioId,
                    ]);

                    // Marcar contrato anterior como retirado
                    $contrato->update([
                        'estado'       => 'retirado',
                        'fecha_retiro' => Carbon::parse($fechaRetiro)->toDateString(),
                    ]);

                    $procesados[] = [
                        'cedula'      => $contrato->cedula,
                        'contrato_id' => $contrato->id,
                        'factura_id'  => $facturaRet->id,
                    ];

                } catch (\Throwable $e) {
                    $errores[] = ['cedula' => $contrato->cedula, 'mensaje' => $e->getMessage()];
                }
            }
        });

        return response()->json([
            'ok'         => count($procesados) > 0,
            'procesados' => $procesados,
            'errores'    => $errores,
            'mensaje'    => count($procesados) . ' retiro(s) aplicado(s) en planilla anterior.',
        ]);
    }

    // ─── 4b. Retiro Opción B: crear plano de retiro en mes futuro ────────────
    public function retirarOpcionB(Request $request): JsonResponse
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        $validated = $request->validate([
            'contrato_ids'           => 'required|array|min:1',
            'contrato_ids.*'         => 'integer',
            'mes_retiro'             => 'required|integer|between:1,12',
            'anio_retiro'            => 'required|integer|min:2020|max:2099',
            'n_plano'                => 'required|integer|min:1',
            'fecha_ingreso_nuevo'    => 'required|date',  // 1 del mes actual (para calcular fecha_ret)
        ]);

        $mesRetiro         = (int) $validated['mes_retiro'];
        $anioRetiro        = (int) $validated['anio_retiro'];
        $nPlano            = (int) $validated['n_plano'];
        $fechaIngresoNuevo = Carbon::parse($validated['fecha_ingreso_nuevo']); // 1 del mes actual

        $procesados = [];
        $errores    = [];

        DB::transaction(function () use (
            $validated, $aliadoId, $usuarioId, $mesRetiro, $anioRetiro, $nPlano,
            $fechaIngresoNuevo, &$procesados, &$errores
        ) {
            $contratos = Contrato::where('aliado_id', $aliadoId)
                ->whereIn('id', $validated['contrato_ids'])
                ->where('estado', 'vigente')
                ->with(['eps', 'pension', 'arl', 'caja', 'razonSocial', 'cliente'])
                ->get();

            foreach ($contratos as $contrato) {
                try {
                    $cliente  = $contrato->cliente;
                    $eps      = $contrato->eps;
                    $afp      = $contrato->pension;
                    $arl      = $contrato->arl;
                    $caja     = $contrato->caja;
                    $rs       = $contrato->razonSocial;

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

                    // ── Regla fecha_ret (Opción B) ─────────────────────────────
                    // Si fecha_ingreso_nuevo (1-mes_actual) está en el MISMO mes de retiro
                    // → fecha_ret = fecha_ingreso_nuevo
                    // Si no → fecha_ret = 1 del mes de retiro
                    // Mes de retiro = mes anterior al mes del plano de retiro
                    // (el plano de retiro se genera en el mes de PAGO que es mesRetiro)
                    // El mes_plano del retiro = mesRetiro - 1 (mes vencido)
                    $mesPlanoRetiro  = $mesRetiro > 1 ? $mesRetiro - 1 : 12;
                    $anioPlanoRetiro = $mesRetiro > 1 ? $anioRetiro    : $anioRetiro - 1;

                    $mismoMes = ($fechaIngresoNuevo->month === $mesPlanoRetiro &&
                                 $fechaIngresoNuevo->year  === $anioPlanoRetiro);

                    if ($mismoMes) {
                        $fechaRet = $fechaIngresoNuevo->toDateString();
                    } else {
                        $fechaRet = Carbon::createFromDate($anioPlanoRetiro, $mesPlanoRetiro, 1)->toDateString();
                    }

                    // Nueva factura de retiro (numero_factura=0, costo=0)
                    $facturaRet = Factura::create([
                        'aliado_id'        => $aliadoId,
                        'numero_factura'   => 0,
                        'tipo'             => 'planilla',
                        'cedula'           => $contrato->cedula,
                        'contrato_id'      => $contrato->id,
                        'razon_social_id'  => $contrato->razon_social_id,
                        'empresa_id'       => null,
                        'mes'              => $mesRetiro,
                        'anio'             => $anioRetiro,
                        'fecha_pago'       => now()->toDateString(),
                        'estado'           => 'pagada',
                        'forma_pago'       => 'efectivo',
                        'valor_efectivo'   => 0,
                        'valor_consignado' => 0,
                        'valor_prestamo'   => 0,
                        'dias_cotizados'   => 1,
                        'v_eps'   => 0, 'v_arl'  => 0, 'v_afp'  => 0, 'v_caja' => 0,
                        'total_ss'=> 0, 'admon'  => 0, 'admin_asesor' => 0,
                        'otros_admon' => 0, 'seguro' => 0, 'afiliacion' => 0,
                        'mensajeria' => 0, 'otros' => 0, 'mora' => 0, 'iva' => 0,
                        'total'        => 0,
                        'saldo_proximo'=> 0,
                        'n_plano'      => $nPlano,
                        'usuario_id'   => $usuarioId,
                        'observacion'  => "Plano de retiro por traslado RS. Fecha retiro: {$fechaRet}.",
                    ]);

                    // Plano de retiro futuro
                    Plano::create([
                        'factura_id'        => $facturaRet->id,
                        'contrato_id'       => $contrato->id,
                        'aliado_id'         => $aliadoId,
                        'numero_factura'    => 0,
                        'tipo_reg'          => 'retiro',
                        'tipo_doc'          => strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC',
                        'no_identifi'       => $contrato->cedula,
                        'primer_ape'        => strtoupper($partsApe[0] ?? ''),
                        'segundo_ape'       => strtoupper($partsApe[1] ?? ''),
                        'primer_nombre'     => strtoupper($partsNom[0] ?? ''),
                        'segundo_nombre'    => strtoupper($partsNom[1] ?? ''),
                        'fecha_ing'         => null,
                        'fecha_ret'         => $fechaRet,
                        'num_dias'          => 1,
                        'cod_eps'           => $eps?->nit  ?? $eps?->cod_eps  ?? null,
                        'nombre_eps'        => $eps?->nombre ?? null,
                        'cod_afp'           => $afp?->nit  ?? $afp?->cod_afp  ?? null,
                        'nombre_afp'        => $afp?->razon_social ?? null,
                        'cod_arl'           => $codArl,
                        'nombre_arl'        => $nombreArl,
                        'cod_caja'          => $caja?->nit ?? $caja?->cod_caja ?? null,
                        'nombre_caja'       => $caja?->nombre ?? null,
                        'nivel_riesgo'      => $contrato->n_arl ?? 1,
                        'salario_basico'    => (int)($contrato->salario ?? 0),
                        'n_plano'           => $nPlano,
                        'mes_plano'         => $mesPlanoRetiro,
                        'anio_plano'        => $anioPlanoRetiro,
                        'razon_social'      => $rs?->razon_social ?? null,
                        'tipo_p'            => 16,
                        'tipo_modalidad_id' => $contrato->tipo_modalidad_id,
                        'usuario_id'        => $usuarioId,
                    ]);

                    // Marcar contrato anterior como retirado
                    $contrato->update([
                        'estado'       => 'retirado',
                        'fecha_retiro' => $fechaRet,
                    ]);

                    $procesados[] = [
                        'cedula'      => $contrato->cedula,
                        'contrato_id' => $contrato->id,
                        'fecha_ret'   => $fechaRet,
                        'factura_id'  => $facturaRet->id,
                    ];

                } catch (\Throwable $e) {
                    $errores[] = ['cedula' => $contrato->cedula, 'mensaje' => $e->getMessage()];
                }
            }
        });

        return response()->json([
            'ok'         => count($procesados) > 0,
            'procesados' => $procesados,
            'errores'    => $errores,
            'mensaje'    => count($procesados) . ' plano(s) de retiro creado(s) para el mes ' . $mesRetiro . '/' . $anioRetiro . '.',
        ]);
    }

    // ─── 5. Descarga TXT MiPlanilla con novedades ING+RET (solo Opción A) ────
    public function descargarPlano(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $razonSocialId  = $request->input('razon_social_id');
        $mes            = (int) $request->input('mes',  now()->month);
        $anio           = (int) $request->input('anio', now()->year);
        $nPlano         = (int) $request->input('n_plano', 1);
        $tiposModalidad = array_map('intval', (array) $request->input('tipos_modalidad', []));
        $operadorId     = $request->input('operador_id');

        if (!$razonSocialId) {
            abort(400, 'Debe especificar la Razón Social.');
        }

        $codigoOperador = '88';
        if ($operadorId) {
            $codigoOperador = DB::table('operadores_planilla')
                ->where('id', $operadorId)
                ->value('codigo_ni') ?: '88';
        }

        try {
            $service = new PlanoPilaTxtService();
            return $service->generar([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $razonSocialId,
                'mes'             => $mes,
                'anio'            => $anio,
                'n_plano'         => $nPlano,
                'tipos_modalidad' => $tiposModalidad,
                'codigo_operador' => $codigoOperador,
                'ignorar_mes_vencido' => true,
                'tipo_planilla' => 'N',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            abort(500, 'Error de base de datos al generar el TXT.');
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'Error al generar el TXT: ' . $e->getMessage());
        }
    }

    // ─── 6. Descarga Excel / CSV MiPlanilla con novedades ING+RET ─────────────────
    public function descargarExcel(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $razonSocialId = $request->input('razon_social_id');
        $mes           = (int) $request->input('mes',  now()->month);
        $anio          = (int) $request->input('anio', now()->year);
        $nPlano        = (int) $request->input('n_plano', 1);
        $formato       = $request->input('formato', 'xlsx'); // 'xlsx' | 'csv'

        if (!$razonSocialId) {
            abort(400, 'Debe especificar la Razón Social.');
        }

        $rsNombre = 'SIN_RS';
        $rs = RazonSocial::find($razonSocialId);
        if ($rs) {
            $rsNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rs->razon_social);
        }

        $ext = $formato === 'csv' ? 'csv' : 'xlsx';
        $filename = "MiPlanilla_Traslado_{$rsNombre}_{$mes}_{$anio}_P{$nPlano}.{$ext}";

        try {
            $service     = new \App\Services\ExcelMiPlanillaService();
            $spreadsheet = $service->generar([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $razonSocialId,
                'mes'             => $mes,
                'anio'            => $anio,
                'n_plano'         => $nPlano,
            ]);

            if ($formato === 'csv') {
                return $service->respuestaCsv($spreadsheet, $filename);
            }
            return $service->respuesta($spreadsheet, $filename);
        } catch (\Illuminate\Database\QueryException $e) {
            abort(500, 'Error de base de datos al generar la planilla.');
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'Error al generar la planilla: ' . $e->getMessage());
        }
    }

    // ─── API: Lista de n_plano disponibles de una RS ──────────────────────────
    public function apiNPlanosRs(int $id): JsonResponse
    {
        $aliadoId = session('aliado_id_activo');
        $rs = RazonSocial::where('aliado_id', $aliadoId)->find($id);
        if (!$rs) {
            return response()->json(['ok' => false, 'mensaje' => 'No encontrada.'], 404);
        }

        // Obtener los n_plano distintos que existen en planos activos de la RS
        $nPlanos = DB::table('planos')
            ->where('razon_social_id', $id)
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->where('n_plano', '>', 0)
            ->distinct()
            ->orderBy('n_plano')
            ->pluck('n_plano');

        return response()->json([
            'ok'              => true,
            'n_plano_actual'  => $rs->n_plano,
            'n_planos'        => $nPlanos,
            'razon_social'    => $rs->razon_social,
        ]);
    }
}
