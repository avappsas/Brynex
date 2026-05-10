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
        // Sin búsqueda: ocultar pagadas/cerradas por defecto
        $estadosInactivosDefault = ['pagada', 'rechazado'];
        if (!$hayBusqueda && !$request->boolean('con_cerradas')) {
            $query->whereNotIn('estado', $estadosInactivosDefault);
        }

        $vista = $request->get('vista', 'agrupada'); // agrupada | plana

        $query->orderByRaw("
            CASE WHEN estado IN ('pagada','rechazado') THEN 99 ELSE 0 END ASC
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

        // Gestión inicial automática
        GestionIncapacidad::create([
            'incapacidad_id'  => $incapacidad->id,
            'user_id'         => Auth::id(),
            'aplica_a_familia'=> false,
            'tipo'            => 'otro',
            'tramite'         => '📬 Incapacidad recibida y registrada en el sistema.',
            'estado_resultado' => 'recibido',
            'created_at'      => now(),
        ]);

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
            'tipo'    => 'required|string',
            'tramite' => 'required|string',
        ]);

        $inc = Incapacidad::findOrFail($id);
        $cfg = Incapacidad::TIPOS_GESTION[$request->tipo] ?? null;
        $cambia = $cfg['cambia_estado'] ?? false;

        $gestion = GestionIncapacidad::create([
            'incapacidad_id'   => $inc->id,
            'user_id'          => Auth::id(),
            'aplica_a_familia' => $request->boolean('aplica_a_familia'),
            'tipo'             => $request->tipo,
            'tramite'          => $request->tramite,
            'respuesta'        => $request->respuesta,
            'estado_resultado' => $cfg['nuevo_estado'] ?? null,
            'fecha_recordar'   => $request->fecha_recordar ?: null,
            'cambia_estado'    => $cambia,
            'estado_nuevo'     => $cfg['nuevo_estado'] ?? null,
            'created_at'       => now(),
        ]);

        // Aplicar cambio de estado si corresponde
        if ($cambia) {
            $gestion->aplicarCambioEstado();
        }

        // Si aplica a familia → registrar en todas las prórrogas (solo seguimiento)
        if ($request->boolean('aplica_a_familia') && !$cambia) {
            $padreId = $inc->incapacidad_padre_id ?? $inc->id;
            Incapacidad::where(function ($q) use ($padreId, $id) {
                $q->where('id', $padreId)->orWhere('incapacidad_padre_id', $padreId);
            })->where('id', '!=', $id)->each(function($miembro) use ($request, $cfg) {
                GestionIncapacidad::create([
                    'incapacidad_id'   => $miembro->id,
                    'user_id'          => Auth::id(),
                    'aplica_a_familia' => true,
                    'tipo'             => $request->tipo,
                    'tramite'          => '[Grupo] ' . $request->tramite,
                    'respuesta'        => $request->respuesta,
                    'cambia_estado'    => false,
                    'created_at'       => now(),
                ]);
            });
        }

        return response()->json([
            'ok'           => true,
            'message'      => 'Gestión registrada.',
            'estado'       => $inc->fresh()->estado,
            'cambia_estado'=> $cambia,
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
        $cedula = $request->get('cedula', '');
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $clientes = DB::table('clientes as c')
            ->join('contratos as ct', 'ct.cedula', '=', 'c.cedula')
            ->where('ct.aliado_id', $alidoId)
            ->where('c.cedula', 'like', '%' . $cedula . '%')
            ->select('c.cedula', 'c.primer_nombre', 'c.segundo_nombre',
                     'c.primer_apellido', 'c.segundo_apellido',
                     'c.celular', 'c.cod_empresa')
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

        $contratos = DB::table('contratos')
            ->where('cedula', $cedula)
            ->where('aliado_id', $alidoId)
            ->orderByDesc('fecha_ingreso')
            ->get(['id', 'cedula', 'fecha_ingreso', 'estado']);

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
