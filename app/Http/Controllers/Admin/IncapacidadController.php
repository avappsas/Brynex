<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incapacidad;
use App\Models\GestionIncapacidad;
use App\Models\Radicado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IncapacidadController extends Controller
{
    // ── INDEX ────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Solo mostramos las incapacidades PADRE (raíz) en la lista principal
        $query = Incapacidad::with([
                'quienRecibe:id,nombre',   // solo columnas necesarias
                'latestGestion',            // eager: una sola query para semáforo
            ])
            ->withCount('prorrogas')
            ->where('aliado_id', $alidoId)
            ->whereNull('incapacidad_padre_id');

        // ── Filtros ─────────────────────────────────────────────────────────
        $busqueda = trim($request->busqueda ?? $request->cedula ?? '');
        $hayBusqueda = strlen($busqueda) > 0;

        if ($hayBusqueda) {
            // Buscar por cédula directa o por nombre en tabla clientes
            $cedulasPorNombre = DB::table('clientes')
                ->where(function($q) use ($busqueda) {
                    $q->where('cedula', 'like', '%'.$busqueda.'%')
                      ->orWhere(DB::raw("CONCAT(primer_nombre,' ',primer_apellido)"), 'like', '%'.$busqueda.'%')
                      ->orWhere(DB::raw("CONCAT(primer_nombre,' ',segundo_nombre,' ',primer_apellido,' ',segundo_apellido)"), 'like', '%'.$busqueda.'%');
                })->pluck('cedula');

            $query->where(function($q) use ($busqueda, $cedulasPorNombre) {
                $q->where('cedula_usuario', 'like', '%'.$busqueda.'%')
                  ->orWhereIn('cedula_usuario', $cedulasPorNombre);
            });
        }

        if ($request->filled('tipo_entidad')) {
            $query->where('tipo_entidad', $request->tipo_entidad);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->estado_pago);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_recibido', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_recibido', '<=', $request->fecha_hasta);
        }

        // Si hay búsqueda: mostrar TODAS (pagadas, rechazadas, activas)
        // Sin búsqueda: ocultar estados finales/cerrados por defecto
        $estadosInactivosDefault = ['pagada', 'rechazado', 'cierre_exitoso'];
        if (!$hayBusqueda && !$request->boolean('con_cerradas')) {
            $query->whereNotIn('estado', $estadosInactivosDefault);
        }

        $vista = $request->get('vista', 'agrupada'); // agrupada | plana

        $query->orderByRaw("
            CASE WHEN estado IN ('pagada','rechazado','cierre_exitoso') THEN 99 ELSE 0 END ASC
        ")->orderByDesc('fecha_recibido');

        $incapacidades = $query->paginate(40)->withQueryString();

        // ── Usar la colección interna del paginador para operaciones batch ────
        $items = $incapacidades->getCollection();

        // ── Cargar nombres de clientes en BATCH (una sola consulta) ──────────
        $cedulas = $items->pluck('cedula_usuario')->unique()->filter()->values();
        $clientesMap = $cedulas->isNotEmpty()
            ? DB::table('clientes')
                ->whereIn('cedula', $cedulas)
                ->select('cedula', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido')
                ->get()
                ->keyBy('cedula')
            : collect();

        // ── Cargar total_dias_familia en BATCH ───────────────────────────────
        // Subquery compatible con SQL Server: agrupa por el padre calculado
        $padreIds = $items->pluck('id')->filter()->values()->toArray();
        $diasFamiliaMap = collect();
        if (!empty($padreIds)) {
            $diasFamiliaMap = DB::table('incapacidades as i')
                ->whereNull('i.deleted_at')
                ->where(function ($q) use ($padreIds) {
                    $q->whereIn('i.id', $padreIds)
                      ->orWhereIn('i.incapacidad_padre_id', $padreIds);
                })
                ->select(
                    DB::raw('CASE WHEN i.incapacidad_padre_id IS NULL THEN i.id ELSE i.incapacidad_padre_id END AS padre_id'),
                    DB::raw('SUM(i.dias_incapacidad) AS total_dias')
                )
                ->groupBy(DB::raw('CASE WHEN i.incapacidad_padre_id IS NULL THEN i.id ELSE i.incapacidad_padre_id END'))
                ->pluck('total_dias', 'padre_id');
        }

        // ── Inyectar datos pre-calculados en la colección del paginador ───────
        $items->transform(function ($inc) use ($clientesMap, $diasFamiliaMap) {
            $cl = $clientesMap->get($inc->cedula_usuario);
            $inc->_nombre_cliente_cache = $cl
                ? trim(($cl->primer_nombre ?? '') . ' ' . ($cl->segundo_nombre ?? '') . ' ' .
                       ($cl->primer_apellido ?? '') . ' ' . ($cl->segundo_apellido ?? ''))
                : $inc->cedula_usuario;
            $inc->_total_dias_familia_cache = (int) ($diasFamiliaMap->get($inc->id) ?? $inc->dias_incapacidad);
            $inc->_num_prorrogas_cache = $inc->prorrogas_count ?? 0;
            // Pre-calcular semáforo (PHP-only, sin DB gracias al eager-load)
            $inc->_dias_gestion_cache = $inc->diasDesdeUltimaGestion();
            $inc->_color_semaforo_cache = $inc->colorSemaforo();
            return $inc;
        });

        // Devolver la colección transformada al paginador
        $incapacidades->setCollection($items);

        // ── KPIs: una sola consulta GROUP BY en vez de tres queries ───────
        $resumen = DB::table('incapacidades')
            ->where('aliado_id', $alidoId)
            ->whereNull('deleted_at')
            ->whereNull('incapacidad_padre_id')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $estadosInactivos = ['pagada', 'rechazado'];
        $totalActivas = $resumen->filter(fn($v, $k) => !in_array($k, $estadosInactivos))->sum();

        $sinGestion7dias = DB::table('incapacidades as i')
            ->where('i.aliado_id', $alidoId)
            ->whereNull('i.deleted_at')
            ->whereNull('i.incapacidad_padre_id')
            ->whereNotIn('i.estado', ['pagada', 'rechazado'])
            ->whereNotExists(function ($sub) {
                $sub->from('gestiones_incapacidad as g')
                    ->whereColumn('g.incapacidad_id', 'i.id')
                    ->whereRaw("g.created_at >= DATEADD(day, -7, GETDATE())");
            })
            ->count();
        $sinGestion10dias = $sinGestion7dias; // backward compat alias

        // ── Listas estáticas cacheadas (cambian rara vez) ──────────────────
        $trabajadores    = User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $epsList         = cache()->remember('eps_list', 3600, fn() => DB::table('eps')->orderBy('nombre')->get(['id', 'nombre']));
        $arlList         = cache()->remember('arl_list', 3600, fn() => DB::table('arls')->orderBy('nombre_arl')->get(['id', 'nombre_arl']));
        $pensionList     = cache()->remember('pension_list', 3600, fn() => DB::table('pensiones')->orderBy('razon_social')->get(['id', 'razon_social']));
        $razonesSociales = DB::table('razones_sociales')
                            ->where('aliado_id', $alidoId)
                            ->where('estado', 'Activa')
                            ->orderBy('razon_social')
                            ->get(['id', 'razon_social']);
        $smmlv = $this->getSmmlv();

        return view('admin.incapacidades.index', compact(
            'incapacidades', 'resumen', 'totalActivas', 'sinGestion10dias', 'sinGestion7dias',
            'trabajadores', 'epsList', 'arlList', 'pensionList', 'razonesSociales',
            'smmlv', 'vista', 'busqueda'
        ));
    }

    // ── STORE ────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'cedula_usuario'   => 'required|string|max:20',
            'tipo_incapacidad' => 'required|string',
            'tipo_entidad'     => 'required|in:eps,arl,afp',
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio'     => 'required|date',
            'fecha_recibido'   => 'required|date',
            'quien_recibe_id'  => 'required|exists:users,id',
        ]);

        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Determinar número de prórroga
        $numProroga = 0;
        $padreId    = $request->incapacidad_padre_id ?: null;

        if ($padreId) {
            $numProroga = Incapacidad::where('incapacidad_padre_id', $padreId)->count() + 1;
        }

        // Fecha terminación automática si no se proporciona
        $fechaTerminacion = $request->fecha_terminacion;
        if (!$fechaTerminacion && $request->fecha_inicio) {
            $fechaTerminacion = \Carbon\Carbon::parse($request->fecha_inicio)
                ->addDays((int)$request->dias_incapacidad - 1)
                ->toDateString();
        }

        // Guardar nombre de la entidad responsable
        $entidadNombre = $this->resolverNombreEntidad(
            $request->tipo_entidad,
            $request->entidad_responsable_id
        );

        // Guardar nombre de razón social
        $rsNombre = null;
        if ($request->razon_social_id) {
            $rs = DB::table('razones_sociales')->find($request->razon_social_id);
            $rsNombre = $rs?->razon_social;
        }

        // Calcular quien_remite: empresa del cliente o cedula_usuario
        $quienRemite = $this->resolverQuienRemite($request->cedula_usuario, $request->quien_remite);

        // Resolver salario_base del contrato activo al momento de la incapacidad
        $salarioBase = null;
        $contratoId  = $request->contrato_id ?: null;
        if ($contratoId) {
            $sal = DB::table('contratos')->where('id', $contratoId)->value('salario');
            $salarioBase = is_numeric($sal) && $sal > 0 ? (float)$sal : null;
        }

        $incapacidad = Incapacidad::create([
            'aliado_id'               => $alidoId,
            'incapacidad_padre_id'    => $padreId,
            'numero_proroga'          => $numProroga,
            'contrato_id'             => $contratoId,
            'cedula_usuario'          => $request->cedula_usuario,
            'quien_remite'            => $quienRemite,
            'quien_recibe_id'         => $request->quien_recibe_id,
            'tipo_incapacidad'        => $request->tipo_incapacidad,
            'dias_incapacidad'        => $request->dias_incapacidad,
            'fecha_inicio'            => $request->fecha_inicio,
            'fecha_terminacion'       => $fechaTerminacion,
            'fecha_recibido'          => $request->fecha_recibido,
            'prorroga'                => $request->boolean('prorroga'),
            'tipo_entidad'            => $request->tipo_entidad,
            'entidad_responsable_id'  => $request->entidad_responsable_id ?: null,
            'entidad_nombre'          => $entidadNombre,
            'razon_social_id'         => $request->razon_social_id ?: null,
            'razon_social_nombre'     => $rsNombre,
            'numero_radicado'         => $request->numero_radicado,
            'fecha_radicado'          => $request->fecha_radicado ?: null,
            'transcripcion_requerida' => $request->boolean('transcripcion_requerida'),
            'diagnostico'             => $request->diagnostico,
            'concepto_rehabilitacion' => $request->concepto_rehabilitacion,
            'observacion'             => $request->observacion,
            'descripcion_cliente'     => $request->descripcion_cliente,
            'salario_base'            => $salarioBase,
            'estado'                  => 'recibido',
            'estado_pago'             => 'pendiente',
            'created_by'              => Auth::id(),
        ]);

        // Calcular y guardar valor_esperado usando salario_base
        $incapacidad->calcularValorEsperado(persistir: true);

        // Gestión inicial automática con texto descriptivo
        $asesor = Auth::user();
        $fechaTexto = now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        GestionIncapacidad::create([
            'incapacidad_id'   => $incapacidad->id,
            'user_id'          => Auth::id(),
            'aplica_a_familia' => false,
            'tipo'             => 'otro',
            'tramite'          => "Incapacidad registrada en BryNex el {$fechaTexto}. Asesor: {$asesor->nombre}. Pdte. radicación en entidad.",
            'estado_resultado' => 'recibido',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Si la petición es AJAX/JSON, retornar JSON para que el frontend abra el modal de documentos
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'ok'             => true,
                'incapacidad_id' => $incapacidad->id,
                'message'        => 'Incapacidad registrada correctamente.',
            ]);
        }

        return redirect()->route('admin.incapacidades.index')
            ->with('success', 'Incapacidad registrada correctamente.');
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'tipo_incapacidad' => 'required|string',
            'tipo_entidad'     => 'required|in:eps,arl,afp',
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio'     => 'required|date',
            'fecha_recibido'   => 'required|date',
        ]);

        $inc = Incapacidad::findOrFail($id);

        $entidadNombre = $this->resolverNombreEntidad(
            $request->tipo_entidad,
            $request->entidad_responsable_id
        );

        $rsNombre = $inc->razon_social_nombre;
        if ($request->razon_social_id && $request->razon_social_id != $inc->razon_social_id) {
            $rs = DB::table('razones_sociales')->find($request->razon_social_id);
            $rsNombre = $rs?->razon_social;
        }

        $inc->update([
            'contrato_id'             => $request->contrato_id ?: $inc->contrato_id,
            'quien_recibe_id'         => $request->quien_recibe_id ?: $inc->quien_recibe_id,
            'tipo_incapacidad'        => $request->tipo_incapacidad,
            'dias_incapacidad'        => $request->dias_incapacidad,
            'fecha_inicio'            => $request->fecha_inicio,
            'fecha_terminacion'       => $request->fecha_terminacion ?: $inc->fecha_terminacion,
            'fecha_recibido'          => $request->fecha_recibido,
            'prorroga'                => $request->boolean('prorroga'),
            'tipo_entidad'            => $request->tipo_entidad,
            'entidad_responsable_id'  => $request->entidad_responsable_id ?: $inc->entidad_responsable_id,
            'entidad_nombre'          => $entidadNombre ?: $inc->entidad_nombre,
            'razon_social_id'         => $request->razon_social_id ?: $inc->razon_social_id,
            'razon_social_nombre'     => $rsNombre,
            'numero_radicado'         => $request->numero_radicado,
            'fecha_radicado'          => $request->fecha_radicado ?: $inc->fecha_radicado,
            'transcripcion_requerida' => $request->boolean('transcripcion_requerida'),
            'transcripcion_completada'=> $request->boolean('transcripcion_completada'),
            'diagnostico'             => $request->diagnostico,
            'concepto_rehabilitacion' => $request->concepto_rehabilitacion,
            'observacion'             => $request->observacion,
        ]);

        // Recalcular valor esperado
        $smmlv = $this->getSmmlv();
        $inc->update(['valor_esperado' => $inc->calcularValorEsperado($smmlv)]);

        return response()->json(['ok' => true, 'message' => 'Incapacidad actualizada.']);
    }

    // ── SHOW (JSON para modal de detalle) ────────────────────────────────────
    public function show(int $id)
    {
        $inc = Incapacidad::with([
            'quienRecibe', 'creadoPor', 'razonSocial',
            'gestiones.user',
            'documentos.user',
            'prorrogas.gestiones.user',
            'prorrogas.documentos',
        ])->findOrFail($id);

        // Datos del cliente
        $cliente = DB::table('clientes')
            ->where('cedula', $inc->cedula_usuario)
            ->select('cedula', 'primer_nombre', 'segundo_nombre',
                     'primer_apellido', 'segundo_apellido',
                     'celular', 'correo', 'cod_empresa')
            ->first();

        // Empresa del cliente
        $empresa = null;
        if ($cliente && $cliente->cod_empresa) {
            $empresa = DB::table('empresas')->where('id', $cliente->cod_empresa)->value('empresa');
        }

        // Calcular resumen de familia
        $familiaDias = $inc->totalDiasFamilia();
        $numProrrogas = $inc->numeroProrrogas();

        return response()->json([
            'incapacidad'  => $inc,
            'cliente'      => $cliente,
            'empresa'      => $empresa,
            'semaforo'     => $inc->colorSemaforo(),
            'icono'        => $inc->iconoSemaforo(),
            'dias_gestion' => $inc->diasDesdeUltimaGestion(),
            'familia_dias' => $familiaDias,
            'num_prorrogas'=> $numProrrogas,
            'alerta_180'   => $inc->alertaDias180(),
        ]);
    }

    // ── REGISTRAR GESTIÓN ────────────────────────────────────────────────────
    public function storeGestion(Request $request, int $id)
    {
        $request->validate([
            'tipo'    => 'required|string|in:llamada,correo,whatsapp,portal,otro',
            'tramite' => 'required|string',
            'alcance' => 'nullable|string|in:esta_incapacidad,toda_la_familia', // default: esta_incapacidad
        ]);

        $inc    = Incapacidad::findOrFail($id);
        $alcance = $request->input('alcance', 'esta_incapacidad');
        $esFamilia = ($alcance === 'toda_la_familia');

        // ── Validar cambio de estado manual ─────────────────────────────────
        $nuevoEstado = $request->estado_nuevo ?: null;

        // cierre_exitoso requiere que la incapacidad tenga pagada_razon_social Y pagada_afiliado
        if ($nuevoEstado === 'cierre_exitoso') {
            $tieneRS  = in_array($inc->estado, ['pagada_razon_social', 'cierre_exitoso']);
            $tieneAf  = in_array($inc->estado, ['pagada_afiliado', 'cierre_exitoso']);
            // También revisar si previamente se marcó alguno de los dos estados
            $historial = GestionIncapacidad::where('incapacidad_id', $inc->id)
                ->whereIn('estado_nuevo', ['pagada_razon_social', 'pagada_afiliado'])
                ->pluck('estado_nuevo');
            $tieneRS = $tieneRS || $historial->contains('pagada_razon_social');
            $tieneAf = $tieneAf || $historial->contains('pagada_afiliado');

            if (!$tieneRS || !$tieneAf) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Para el cierre exitoso se requiere haber registrado primero "Pagada a Razón Social" y "Pagada al Afiliado".',
                ], 422);
            }
        }

        // ── Registrar gestión ────────────────────────────────────────────────
        // Si es de familia, se guarda en el padre (o en la incapacidad actual marcada como familia)
        $incGestionId = $inc->id;
        if ($esFamilia) {
            // Guardar en el padre de la familia
            $incGestionId = $inc->incapacidad_padre_id ?? $inc->id;
        }

        $gestion = GestionIncapacidad::create([
            'incapacidad_id'   => $incGestionId,
            'user_id'          => Auth::id(),
            'aplica_a_familia' => $esFamilia,
            'tipo'             => $request->tipo,
            'tramite'          => $request->tramite,
            'respuesta'        => $request->respuesta,
            'estado_resultado' => $nuevoEstado,
            'cambia_estado'    => (bool) $nuevoEstado,
            'estado_nuevo'     => $nuevoEstado,
            'created_at'       => now(),
        ]);

        // ── Aplicar cambio de estado si se especificó ────────────────────────
        if ($nuevoEstado) {
            $incActualizar = Incapacidad::find($incGestionId);
            if ($incActualizar) {
                $incActualizar->estado = $nuevoEstado;
                // Si cierre_exitoso, mantener ese estado (ya no mapear a 'pagada' legacy)
                if ($nuevoEstado === 'cierre_exitoso') {
                    $incActualizar->estado = 'cierre_exitoso';
                }
                // Si radicada, guardar número y fecha
                if ($nuevoEstado === 'radicada') {
                    if ($request->filled('numero_radicado')) $incActualizar->numero_radicado = $request->numero_radicado;
                    if ($request->filled('fecha_radicado'))  $incActualizar->fecha_radicado  = $request->fecha_radicado;
                }
                // Si pagada_razon_social, registrar abono + consignación
                if ($nuevoEstado === 'pagada_razon_social' && $request->filled('valor_pago_rs')) {
                    $formaLabel = match($request->forma_pago_rs ?? 'otro') {
                        'transferencia' => 'Transferencia/Consignación bancaria',
                        'opi'           => 'OPI (Orden de Pago Inmediata - ARL)',
                        'odi'           => 'ODI (Orden de la entidad)',
                        'cheque'        => 'Cheque',
                        'directo'       => 'Pago directo al cliente',
                        default         => 'Otro medio de pago',
                    };
                    $refLabel = $request->filled('ref_pago_rs') ? ' · Ref: ' . $request->ref_pago_rs : '';
                    $obsAbono = "{$formaLabel}{$refLabel} — Incapacidad #{$incActualizar->id}";

                    // Solo crear consignación bancaria si es transferencia y hay cuenta
                    $consignacionId = null;
                    if ($request->forma_pago_rs === 'transferencia') {
                        $consignacionId = DB::table('consignaciones')->insertGetId([
                            'aliado_id'       => $incActualizar->aliado_id,
                            'fecha'           => $request->fecha_pago_rs ?? now()->toDateString(),
                            'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                            'valor'           => $request->valor_pago_rs,
                            'referencia'      => $request->ref_pago_rs ?: ('Pago incapacidad #' . $incActualizar->id),
                            'observacion'     => $obsAbono,
                            'tipo'            => 'incapacidad',
                            'incapacidad_id'  => $incActualizar->id,
                            'confirmado'      => 0,
                            'usuario_id'      => Auth::id(),
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }

                    DB::table('abonos_incapacidades')->insert([
                        'aliado_id'       => $incActualizar->aliado_id,
                        'incapacidad_id'  => $incActualizar->id,
                        'razon_social_id' => $incActualizar->razon_social_id ?? null,
                        'tipo'            => 'pago_eps',
                        'valor'           => $request->valor_pago_rs,
                        'fecha'           => $request->fecha_pago_rs ?? now()->toDateString(),
                        'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                        'consignacion_id' => $consignacionId,
                        'usuario_id'      => Auth::id(),
                        'observacion'     => $obsAbono,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
                $incActualizar->saveQuietly();
            }
        }

        return response()->json([
            'ok'              => true,
            'message'         => 'Gestión registrada.',
            'estado'          => $inc->fresh()->estado,
            'alcance'         => $alcance,
            'consignacion_id' => $consignacionId ?? null,
        ]);
    }

    // ── CUENTAS BANCARIAS DE LA RAZÓN SOCIAL ────────────────────────────────
    public function cuentasRazonSocial(int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $inc = Incapacidad::findOrFail($id);

        // Obtener NIT y razon_social_id de la RS de la incapacidad
        $rsId = $inc->razon_social_id;
        $nit  = null;

        if ($rsId) {
            $rs  = DB::table('razones_sociales')->where('id', $rsId)->first(['nit', 'razon_social']);
            $nit = $rs?->nit ? trim((string)$rs->nit) : null;
        }

        $base = DB::table('banco_cuentas')
            ->where('aliado_id', $inc->aliado_id)
            ->where('activo', true);

        // Prioridad 1: cuentas que coincidan por NIT del titular
        $cuentas = collect();
        if ($nit) {
            $cuentas = (clone $base)
                ->where(function($q) use ($nit) {
                    // Comparar NIT sin puntos ni espacios para evitar mismatch de formato
                    $q->whereRaw("REPLACE(REPLACE(nit, '.', ''), ' ', '') = ?", [preg_replace('/[\.\s]/', '', $nit)]);
                })
                ->orderBy('banco')
                ->get(['id','nombre','banco','tipo_cuenta','numero_cuenta','nit']);
        }

        // Prioridad 2: si no hay por NIT, buscar por razon_social_id
        if ($cuentas->isEmpty() && $rsId) {
            $cuentas = (clone $base)
                ->where('razon_social_id', $rsId)
                ->orderBy('banco')
                ->get(['id','nombre','banco','tipo_cuenta','numero_cuenta','nit']);
        }

        return response()->json([
            'ok'        => true,
            'cuentas'   => $cuentas,
            'nit_rs'    => $nit,
            'rs_nombre' => $rs?->razon_social ?? $inc->razon_social_nombre ?? null,
            'buscado_por' => $cuentas->isNotEmpty()
                ? ($nit ? "NIT: {$nit}" : "razon_social_id: {$rsId}")
                : 'sin coincidencia',
        ]);
    }


    // ── GENERAR LINK DE SUBIDA ───────────────────────────────────────────────
    public function generarLink(int $id)
    {
        $inc  = Incapacidad::findOrFail($id);
        $link = $inc->link_subida; // genera token si no existe
        $wa   = $inc->mensaje_whatsapp_subida;
        return response()->json(['ok' => true, 'link' => $link, 'whatsapp' => $wa]);
    }

    // ── STORE ABONO (préstamo / pago EPS / pago cliente) ────────────────────
    public function storeAbono(Request $request, int $id)
    {
        $request->validate([
            'tipo'  => 'required|in:abono,pago_eps,pago_cliente',
            'valor' => 'required|numeric|min:1',
            'fecha' => 'required|date',
        ]);

        $inc     = Incapacidad::findOrFail($id);
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Si es pago_eps → crear también en consignaciones
        $consignacionId = null;
        if ($request->tipo === 'pago_eps' && $request->banco_cuenta_id) {
            $cons = DB::table('consignaciones')->insertGetId([
                'aliado_id'      => $alidoId,
                'incapacidad_id' => $inc->id,
                'banco_cuenta_id'=> $request->banco_cuenta_id,
                'fecha'          => $request->fecha,
                'valor'          => $request->valor,
                'referencia'     => $request->referencia,
                'confirmado'     => true,
                'observacion'    => $request->observacion,
                'usuario_id'     => Auth::id(),
                'tipo'           => 'ingreso',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $consignacionId = $cons;
        }

        // Guardar imagen si viene
        $imagenPath = null;
        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->store(
                "incapacidades/{$alidoId}/{$inc->cedula_usuario}/{$id}/pagos", 'public'
            );
        }

        \App\Models\AbonoIncapacidad::create([
            'aliado_id'       => $alidoId,
            'incapacidad_id'  => $inc->id,
            'razon_social_id' => $request->razon_social_id ?: $inc->razon_social_id,
            'tipo'            => $request->tipo,
            'valor'           => $request->valor,
            'fecha'           => $request->fecha,
            'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
            'consignacion_id' => $consignacionId,
            'usuario_id'      => Auth::id(),
            'observacion'     => $request->observacion,
            'imagen_path'     => $imagenPath,
        ]);

        // Si es pago al cliente → marcar estado_pago
        if ($request->tipo === 'pago_cliente') {
            $saldo = $inc->fresh()->saldo_pendiente;
            if ($saldo <= 0) {
                $inc->update(['estado_pago' => 'pagado_afiliado', 'estado' => 'pagada']);
            }
        }

        $inc->refresh()->load('abonos');
        return response()->json([
            'ok'              => true,
            'message'         => 'Registrado correctamente.',
            'saldo_pendiente' => $inc->saldo_pendiente,
            'total_prestado'  => $inc->total_prestado,
        ]);
    }

    // ── CREAR PRÓRROGA ───────────────────────────────────────────────────────
    public function storeProrroga(Request $request, int $padreId)
    {
        $request->validate([
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio'     => 'required|date',
            'tipo_entidad'     => 'required|in:eps,arl,afp',
        ]);

        $padre   = Incapacidad::findOrFail($padreId);
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;
        $numProrroga = Incapacidad::where('incapacidad_padre_id', $padreId)->count() + 1;

        $fechaTerminacion = $request->fecha_terminacion
            ?: \Carbon\Carbon::parse($request->fecha_inicio)
                ->addDays($request->dias_incapacidad - 1)->toDateString();

        $entidadNombre = $this->resolverNombreEntidad($request->tipo_entidad, $request->entidad_responsable_id);

        // Salario base: heredar del padre o releer del contrato
        $salarioBase = $padre->salario_base;
        if (!$salarioBase && $padre->contrato_id) {
            $sal = DB::table('contratos')->where('id', $padre->contrato_id)->value('salario');
            $salarioBase = is_numeric($sal) && $sal > 0 ? (float)$sal : null;
        }

        $prorroga = Incapacidad::create([
            'aliado_id'            => $alidoId,
            'incapacidad_padre_id' => $padreId,
            'numero_proroga'       => $numProrroga,
            'contrato_id'          => $padre->contrato_id,
            'cedula_usuario'       => $padre->cedula_usuario,
            'quien_remite'         => $padre->quien_remite,
            'quien_recibe_id'      => $padre->quien_recibe_id,
            'tipo_incapacidad'     => $padre->tipo_incapacidad,
            'dias_incapacidad'     => $request->dias_incapacidad,
            'fecha_inicio'         => $request->fecha_inicio,
            'fecha_terminacion'    => $fechaTerminacion,
            'fecha_recibido'       => $request->fecha_recibido ?? now()->toDateString(),
            'prorroga'             => true,
            'tipo_entidad'         => $request->tipo_entidad,
            'entidad_responsable_id' => $request->entidad_responsable_id ?: $padre->entidad_responsable_id,
            'entidad_nombre'       => $entidadNombre ?: $padre->entidad_nombre,
            'razon_social_id'      => $padre->razon_social_id,
            'razon_social_nombre'  => $padre->razon_social_nombre,
            'salario_base'         => $salarioBase,
            'diagnostico'          => $padre->diagnostico,
            'observacion'          => $request->observacion,
            'estado'               => 'recibido',
            'estado_pago'          => 'pendiente',
            'created_by'           => Auth::id(),
        ]);

        $prorroga->calcularValorEsperado(persistir: true);

        return response()->json([
            'ok'        => true,
            'message'   => "Prórroga {$numProrroga} creada.",
            'prorroga'  => $prorroga->only(['id','numero_proroga','dias_incapacidad','fecha_inicio','fecha_terminacion','estado','valor_esperado']),
        ]);
    }

    // ── SUBIR DOCUMENTO (reutiliza tabla radicados) ──────────────────────────
    public function storeDocumento(Request $request, int $id)
    {
        $request->validate([
            'archivo'        => 'required|file|max:15360', // 15MB
            'tipo_documento' => 'required|string',
        ]);

        $inc  = Incapacidad::findOrFail($id);
        $file = $request->file('archivo');
        $ext  = strtolower($file->getClientOriginalExtension());
        $cedula = $inc->cedula_usuario;

        $ruta = $file->store(
            "incapacidades/{$inc->aliado_id}/{$cedula}/{$id}",
            'public'
        );

        // Guardar en tabla radicados reutilizando incapacidad_id
        Radicado::create([
            'incapacidad_id'  => $inc->id,
            'aliado_id'       => $inc->aliado_id,
            'contrato_id'     => $inc->contrato_id ?? 0,
            'tipo'            => 'incapacidad',
            'tipo_documento'  => $request->tipo_documento,
            'estado'          => 'ok',
            'observacion'     => $request->observacion,
            'ruta_pdf'        => $ruta,
            'user_id'         => Auth::id(),
        ]);

        // Si es soporte de pago firmado → actualizarlo en la incapacidad
        if ($request->tipo_documento === 'soporte_pago') {
            $inc->update(['ruta_soporte_pago' => $ruta]);
        }

        return response()->json(['ok' => true, 'message' => 'Documento subido.', 'ruta' => $ruta]);
    }

    // ── DESCARGAR DOCUMENTO ──────────────────────────────────────────────────
    public function descargarDocumento(int $docId)
    {
        $doc = Radicado::where('tipo', 'incapacidad')->findOrFail($docId);
        return Storage::disk('public')->download($doc->ruta_pdf, $doc->tipo_documento . '.pdf');
    }

    // ── DOCUMENTOS DE TODA LA FAMILIA (padre + prórrogas) ───────────────────
    public function documentosFamilia(int $id)
    {
        $inc = Incapacidad::findOrFail($id);

        // Obtener el id padre real (si es una prórroga, subir al padre)
        $padreId = $inc->incapacidad_padre_id ?? $inc->id;

        // Obtener todos los miembros de la familia
        $familia = Incapacidad::where(function ($q) use ($padreId) {
            $q->where('id', $padreId)->orWhere('incapacidad_padre_id', $padreId);
        })->whereNull('deleted_at')
          ->orderBy('numero_proroga')
          ->get(['id', 'numero_proroga', 'incapacidad_padre_id', 'fecha_inicio', 'fecha_terminacion', 'dias_incapacidad']);

        $familiaIds = $familia->pluck('id')->toArray();

        // Obtener todos los documentos de la familia en una sola consulta
        $documentos = DB::table('radicados as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->whereIn('r.incapacidad_id', $familiaIds)
            ->where('r.tipo', 'incapacidad')
            ->orderBy('r.incapacidad_id')
            ->orderByDesc('r.id')
            ->select(
                'r.id', 'r.incapacidad_id', 'r.tipo_documento', 'r.observacion',
                'r.ruta_pdf', 'r.estado', 'r.created_at',
                'u.nombre as subido_por'
            )
            ->get();

        // Agrupar documentos por incapacidad_id
        $docsAgrupados = $documentos->groupBy('incapacidad_id');

        // Construir respuesta enriquecida
        $resultado = $familia->map(function ($miembro) use ($docsAgrupados) {
            $docs = $docsAgrupados->get($miembro->id, collect());
            return [
                'incapacidad_id'    => $miembro->id,
                'es_padre'          => is_null($miembro->incapacidad_padre_id),
                'numero_proroga'    => $miembro->numero_proroga,
                'label'             => is_null($miembro->incapacidad_padre_id) ? 'Original' : "Prórroga {$miembro->numero_proroga}",
                'fecha_inicio'      => $miembro->fecha_inicio,
                'fecha_terminacion' => $miembro->fecha_terminacion,
                'dias_incapacidad'  => $miembro->dias_incapacidad,
                'documentos'        => $docs->map(function ($d) {
                    $ext = strtolower(pathinfo($d->ruta_pdf, PATHINFO_EXTENSION));
                    return [
                        'id'            => $d->id,
                        'tipo_documento'=> $d->tipo_documento,
                        'observacion'   => $d->observacion,
                        'ruta_pdf'      => $d->ruta_pdf,
                        'url_ver'       => Storage::disk('public')->url($d->ruta_pdf),
                        'url_descargar' => route('admin.incapacidades.documento.download', $d->id),
                        'es_pdf'        => $ext === 'pdf',
                        'extension'     => $ext,
                        'subido_por'    => $d->subido_por ?? 'Cliente',
                        'fecha'         => $d->created_at,
                    ];
                })->values(),
                'total_docs' => $docs->count(),
            ];
        });

        // ── Documentos globales del cliente (cédula, carta laboral, etc.) ────
        $alidoId = $inc->aliado_id;
        $cedula  = $inc->cedula_usuario;
        $docsGlobales = collect();

        try {
            $docsGlobales = DB::table('documentos_cliente as dc')
                ->leftJoin('users as u', 'u.id', '=', 'dc.subido_por')
                ->where('dc.aliado_id', $alidoId)
                ->where('dc.cc_cliente', $cedula)
                ->whereNull('dc.doc_beneficiario') // solo del titular
                ->orderByDesc('dc.created_at')
                ->select(
                    'dc.id', 'dc.tipo_documento', 'dc.nombre_archivo',
                    'dc.ruta', 'dc.created_at',
                    'u.nombre as subido_por'
                )
                ->get()
                ->map(function ($d) {
                    $ext = strtolower(pathinfo($d->ruta ?? $d->nombre_archivo ?? '', PATHINFO_EXTENSION));
                    $tiposLabel = [
                        'cedula'            => '🪪 Cédula',
                        'carta_laboral'     => '📋 Carta Laboral',
                        'registro_civil'    => '📜 Registro Civil',
                        'tarjeta_identidad' => '🪪 Tarjeta Identidad',
                        'decl_juramentada'  => '⚖️ Decl. Juramentada',
                        'acta_matrimonio'   => '💍 Acta Matrimonio',
                        'otro'              => '📎 Otro',
                    ];
                    return [
                        'id'            => $d->id,
                        'tipo_documento'=> $d->tipo_documento,
                        'tipo_label'    => $tiposLabel[$d->tipo_documento] ?? ucfirst($d->tipo_documento),
                        'nombre'        => $d->nombre_archivo,
                        'ruta'          => $d->ruta,
                        'url_ver'       => $d->ruta ? route('admin.documentos.download', $d->id) : null,
                        'url_descargar' => $d->ruta ? route('admin.documentos.download', $d->id) : null,
                        'es_pdf'        => $ext === 'pdf',
                        'extension'     => $ext,
                        'subido_por'    => $d->subido_por ?? 'Sistema',
                        'fecha'         => $d->created_at,
                    ];
                });
        } catch (\Exception $e) {
            // Si la tabla no existe o hay error, continuar sin docs globales
            $docsGlobales = collect();
        }

        return response()->json([
            'ok'              => true,
            'familia'         => $resultado,
            'total_documentos'=> $documentos->count(),
            'docs_globales'   => $docsGlobales->values(),
        ]);
    }

    // ── REGISTRAR PAGO AL AFILIADO ───────────────────────────────────────────
    public function registrarPago(Request $request, int $id)
    {
        $request->validate([
            'valor_pago'  => 'required|numeric|min:0',
            'fecha_pago'  => 'required|date',
            'pagado_a'    => 'required|in:cliente,empresa',
        ]);

        $inc = Incapacidad::findOrFail($id);
        $inc->update([
            'estado_pago'  => 'pagado_afiliado',
            'valor_pago'   => $request->valor_pago,
            'fecha_pago'   => $request->fecha_pago,
            'pagado_a'     => $request->pagado_a,
            'detalle_pago' => $request->detalle_pago,
            'estado'       => 'pagado_afiliado',
        ]);

        // Gestión automática
        GestionIncapacidad::create([
            'incapacidad_id'   => $inc->id,
            'user_id'          => Auth::id(),
            'aplica_a_familia' => false,
            'tipo'             => 'pago_afiliado',
            'tramite'          => "💰 Pago registrado al " . ($request->pagado_a === 'cliente' ? 'cliente afiliado' : 'empresa'),
            'respuesta'        => 'Valor: $' . number_format($request->valor_pago, 0, ',', '.'),
            'estado_resultado' => 'pagado_afiliado',
            'created_at'       => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Pago al afiliado registrado.']);
    }

    // ── CALCULAR VALOR ESPERADO (API) ────────────────────────────────────────
    public function calcularValor(int $id)
    {
        $inc   = Incapacidad::findOrFail($id);
        $smmlv = $this->getSmmlv();
        $valor = $inc->calcularValorEsperado($smmlv);

        $inc->update(['valor_esperado' => $valor]);

        return response()->json([
            'ok'             => true,
            'valor_esperado' => $valor,
            'valor_formato'  => '$' . number_format($valor, 0, ',', '.'),
            'alerta_180'     => $inc->alertaDias180(),
            'total_dias'     => $inc->totalDiasFamilia(),
        ]);
    }

    // ── DESTROY (soft delete) ────────────────────────────────────────────────
    public function destroy(int $id)
    {
        $inc = Incapacidad::findOrFail($id);
        $inc->delete();
        return redirect()->route('admin.incapacidades.index')
            ->with('success', 'Incapacidad eliminada.');
    }

    // ── API: buscar clientes ─────────────────────────────────────────────────
    public function apiClientes(Request $request)
    {
        $cedula  = $request->get('cedula', '');
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $clientes = DB::table('clientes as c')
            ->leftJoin('empresas as e', 'e.id', '=', 'c.cod_empresa')
            ->where('c.aliado_id', $alidoId)
            ->where('c.cedula', 'like', '%' . $cedula . '%')
            ->select('c.cedula', 'c.primer_nombre', 'c.segundo_nombre',
                     'c.primer_apellido', 'c.segundo_apellido',
                     'c.celular', 'c.cod_empresa',
                     'e.empresa as empresa_nombre')
            ->distinct()
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }


    // ── API: contratos por cédula ────────────────────────────────────────────
    public function apiContratos(Request $request)
    {
        $cedula  = $request->get('cedula');
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $contratos = DB::table('contratos as c')
            ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.cedula', $cedula)
            ->where('c.aliado_id', $alidoId)
            ->orderByRaw("CASE WHEN c.estado='vigente' THEN 0 ELSE 1 END")
            ->orderByDesc('c.fecha_ingreso')
            ->get(['c.id', 'c.cedula', 'c.fecha_ingreso', 'c.estado',
                   'c.razon_social_id', 'rs.razon_social as razon_social_nombre',
                   'c.eps_id', 'c.arl_id', 'c.salario']);

        return response()->json($contratos);
    }

    // ── HELPERS PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Obtiene el SMMLV de la configuración o usa el valor actual por defecto.
     */
    private function getSmmlv(): float
    {
        $config = DB::table('configuracion_brynex')->first();
        return (float) ($config?->smmlv ?? 1423500);
    }

    /**
     * Resuelve el nombre de la entidad según tipo y ID.
     */
    private function resolverNombreEntidad(string $tipo, ?int $entidadId): ?string
    {
        if (!$entidadId) return null;

        return match($tipo) {
            'eps' => DB::table('eps')->where('id', $entidadId)->value('nombre'),
            'arl' => DB::table('arls')->where('id', $entidadId)->value('nombre_arl'),
            'afp' => DB::table('pensiones')->where('id', $entidadId)->value('razon_social'),
            default => null,
        };
    }

    /**
     * Determina quien_remite:
     * Si el cliente tiene cod_empresa → retorna nombre de la empresa
     * Si no → retorna la cédula del cliente (independiente)
     */
    private function resolverQuienRemite(string $cedula, ?string $overrideRemite): string
    {
        if ($overrideRemite) return $overrideRemite;

        $cliente = DB::table('clientes')->where('cedula', $cedula)->first();
        if ($cliente && $cliente->cod_empresa) {
            $empresa = DB::table('empresas')->where('id', $cliente->cod_empresa)->value('empresa');
            if ($empresa) return $empresa;
        }
        return $cedula; // independiente
    }
}
