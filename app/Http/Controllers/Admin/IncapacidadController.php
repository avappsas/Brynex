<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\GestionIncapacidad;
use App\Models\Incapacidad;
use App\Models\Radicado;
use App\Models\User;
use App\Services\CompresorDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IncapacidadController extends Controller
{
    /**
     * Estados en los que la incapacidad ya no requiere gestión.
     *
     * Se usa para no contarlas como activas ni pedirles seguimiento. Incluye
     * las variantes de pago ('cierre_exitoso' y los pagos parciales) y 'negada',
     * que es una resolución final de la entidad.
     *
     * Antes cada punto del módulo repetía su propia lista y no coincidían: el
     * contador de activas solo excluía ['pagada','rechazado'], así que al
     * normalizar las migradas a 'cierre_exitoso' las 1.617 pagadas de GiMave
     * volvieron a contarse como activas.
     */
    public const ESTADOS_FINALES = [
        'pagada',                 // legacy
        'pagado_afiliado',        // legacy (ortografía vieja de 'pagada_afiliado')
        'pagada_afiliado',
        'pagada_razon_social',
        'cierre_exitoso',
        'rechazado',
        'negada',
    ];

    /**
     * Estados que cuentan como "pago completo" para el KPI de Pagadas.
     *
     * 'pagada' (legacy) y 'cierre_exitoso' significan lo mismo — el ciclo de pago
     * quedó cerrado — solo que con nombres distintos según cuándo se generó.
     * 'pagada_afiliado' y 'pagada_razon_social' quedan afuera A PROPÓSITO: son
     * pago parcial (ej. el aliado ya le pagó al afiliado pero todavía no le
     * reembolsa la entidad), así que la incapacidad sigue abierta hasta que
     * ambos lados queden saldados (cierre_exitoso).
     */
    public const ESTADOS_PAGADA_COMPLETA = ['pagada', 'cierre_exitoso'];

    /**
     * Estados en los que ya no queda plata por cobrar, para la columna
     * "Valor Esperado" (que muestra el pendiente de la familia). Incluye los
     * pagos parciales: si ya se pagó a la razón social o al afiliado, ese valor
     * salió del pendiente aunque la incapacidad siga abierta.
     */
    public const ESTADOS_SIN_PENDIENTE = [
        'pagada',
        'pagado_afiliado',        // legacy
        'pagada_afiliado',
        'pagada_razon_social',
        'cierre_exitoso',
    ];

    /**
     * Disco donde se guardan los documentos de incapacidades.
     *
     * 'local' (storage/app) NO se sirve por el servidor web. Antes era 'public',
     * lo que dejaba historias clínicas, epicrisis y cédulas accesibles en
     * https://brynex.co/storage/incapacidades/{aliado}/{cedula}/{id}/... sin
     * sesión y con la cédula en la ruta, es decir enumerables. Son datos
     * sensibles de salud (Ley 1581 de 2012). Se sirven por verDocumento() /
     * descargarDocumento(), que validan el aliado.
     */
    private const DISCO_DOCUMENTOS = 'local';

    /**
     * Query base restringida al aliado en sesión.
     *
     * Todo acceso a una incapacidad por id debe pasar por aquí. Sin este filtro,
     * cualquier usuario autenticado podía ver, modificar, abonar y generar el
     * link público de subida de incapacidades de OTROS aliados con solo cambiar
     * el id en la URL. Mismo criterio que ContratoController y FacturacionController.
     */
    private function incapacidadesDelAliado()
    {
        return Incapacidad::where('aliado_id', session('aliado_id_activo') ?? Auth::user()->aliado_id);
    }

    /** Incapacidad del aliado en sesión, o 404. */
    private function incapacidadDelAliado(int $id): Incapacidad
    {
        return $this->incapacidadesDelAliado()->findOrFail($id);
    }

    /**
     * Normaliza un valor para compararlo y guardarlo en bitácora.
     *
     * Las fechas del modelo vienen como Carbon (cast 'date'), así que compararlas
     * con === daría siempre distinto y el JSON quedaría con el objeto completo.
     */
    private function valorAuditable($valor): string|int|float|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if (is_bool($valor)) {
            return (int) $valor;
        }
        if (is_numeric($valor)) {
            return $valor + 0;
        }

        return (string) $valor;
    }

    // ── INDEX ────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Solo mostramos las incapacidades PADRE (raíz) en la lista principal
        $query = Incapacidad::with([
            'quienRecibe:id,nombre',
            'latestGestion',
            'prorrogas:id,incapacidad_padre_id,estado,valor_esperado', // para calcular valor pendiente
        ])
            ->withCount('prorrogas')
            ->where('aliado_id', $alidoId)
            ->whereNull('incapacidad_padre_id');

        // ── Filtros ─────────────────────────────────────────────────────────
        $busqueda = trim($request->busqueda ?? $request->cedula ?? '');
        $hayBusqueda = strlen($busqueda) > 0;

        if ($hayBusqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('cedula_usuario', 'like', '%'.$busqueda.'%')
                    ->orWhereIn('cedula_usuario', function ($subquery) use ($busqueda) {
                        $subquery->select('cedula')
                            ->from('clientes')
                            ->where(function ($sub) use ($busqueda) {
                                $sub->where('cedula', 'like', '%'.$busqueda.'%')
                                    ->orWhere(DB::raw("CONCAT(primer_nombre,' ',primer_apellido)"), 'like', '%'.$busqueda.'%')
                                    ->orWhere(DB::raw("CONCAT(primer_nombre,' ',segundo_nombre,' ',primer_apellido,' ',segundo_apellido)"), 'like', '%'.$busqueda.'%');
                            });
                    });
            });
        }

        if ($request->filled('tipo_entidad')) {
            $query->where('tipo_entidad', $request->tipo_entidad);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo_incapacidad')) {
            $query->where('tipo_incapacidad', $request->tipo_incapacidad);
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
        // Sin búsqueda: ocultar estados finales/cerrados por defecto.
        // Excepción: si el usuario eligió un estado final desde el filtro de la
        // columna Estado, ocultarlo dejaría la tabla vacía sin explicación.
        $estadosInactivosDefault = self::ESTADOS_FINALES;
        $pidioEstadoFinal = $request->filled('estado')
            && in_array($request->get('estado'), $estadosInactivosDefault, true);
        if (! $hayBusqueda && ! $request->boolean('con_cerradas') && ! $pidioEstadoFinal) {
            $query->whereNotIn('estado', $estadosInactivosDefault);
        }

        $vista = $request->get('vista', 'agrupada'); // agrupada | plana

        // ── Orden ───────────────────────────────────────────────────────────
        // Con clic en un encabezado manda el criterio del usuario; sin él, el
        // de siempre: primero las que aún requieren gestión, luego por fecha.
        $orden = $request->get('orden');
        $dir = strtolower($request->get('dir')) === 'desc' ? 'desc' : 'asc';

        if ($orden === 'cliente') {
            // El nombre no vive en incapacidades: subconsulta correlacionada a
            // clientes. limit(1) porque una cédula puede tener más de una ficha.
            $query->orderBy(
                DB::table('clientes')
                    ->select('primer_apellido')
                    ->whereColumn('clientes.cedula', 'incapacidades.cedula_usuario')
                    ->where('clientes.aliado_id', $alidoId)
                    ->limit(1),
                $dir
            )->orderBy('incapacidades.id', 'desc');
        } elseif ($orden === 'valor') {
            if ($vista === 'agrupada') {
                // La columna muestra el pendiente de TODA la familia (padre +
                // prórrogas sin pagar), así que se ordena por ese mismo total y
                // no por el valor_esperado suelto del padre.
                $pagados = "'".implode("','", self::ESTADOS_SIN_PENDIENTE)."'";
                $query->orderByRaw("(
                    SELECT ISNULL(SUM(CASE WHEN i2.estado NOT IN ($pagados)
                                           THEN ISNULL(i2.valor_esperado, 0) ELSE 0 END), 0)
                    FROM incapacidades i2
                    WHERE i2.deleted_at IS NULL
                      AND (i2.id = incapacidades.id OR i2.incapacidad_padre_id = incapacidades.id)
                ) ".($dir === 'desc' ? 'DESC' : 'ASC'))
                    ->orderBy('incapacidades.id', 'desc');
            } else {
                $query->orderBy('valor_esperado', $dir)->orderBy('incapacidades.id', 'desc');
            }
        } else {
            $query->orderByRaw("
                CASE WHEN estado IN ('pagada','pagado_afiliado','pagada_afiliado','pagada_razon_social','cierre_exitoso','rechazado','negada') THEN 99 ELSE 0 END ASC
            ")->orderByDesc('fecha_recibido');
        }

        $incapacidades = $query->paginate(40)->withQueryString();

        // ── Usar la colección interna del paginador para operaciones batch ────
        $items = $incapacidades->getCollection();

        // ── Cargar nombres de clientes en BATCH (una sola consulta) ──────────
        $cedulas = $items->pluck('cedula_usuario')->unique()->filter()->values();
        $clientesMap = $cedulas->isNotEmpty()
            ? DB::table('clientes')
                ->whereIn('cedula', $cedulas)
                ->select('cedula', 'tipo_doc', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido')
                ->get()
                ->keyBy('cedula')
            : collect();

        // ── Cargar total_dias_familia en BATCH ───────────────────────────────
        // Subquery compatible con SQL Server: agrupa por el padre calculado
        $padreIds = $items->pluck('id')->filter()->values()->toArray();
        $diasFamiliaMap = collect();
        if (! empty($padreIds)) {
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
                ? trim(($cl->primer_nombre ?? '').' '.($cl->segundo_nombre ?? '').' '.
                       ($cl->primer_apellido ?? '').' '.($cl->segundo_apellido ?? ''))
                : $inc->cedula_usuario;
            $inc->_tipo_doc_cache = $cl->tipo_doc ?? null;
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

        // Estados finales: ya no requieren gestión. Incluye las variantes de pagada
        // ('cierre_exitoso' y las parciales) y 'negada', que la entidad ya resolvió.
        $estadosInactivos = self::ESTADOS_FINALES;
        $totalActivas = $resumen->filter(fn ($v, $k) => ! in_array($k, $estadosInactivos))->sum();

        $totalPagadas = $resumen->filter(fn ($v, $k) => in_array($k, self::ESTADOS_PAGADA_COMPLETA))->sum();
        $totalNoPagadas = $resumen->get('rechazado', 0);

        $sinGestion7dias = DB::table('incapacidades as i')
            ->where('i.aliado_id', $alidoId)
            ->whereNull('i.deleted_at')
            ->whereNull('i.incapacidad_padre_id')
            ->whereNotIn('i.estado', self::ESTADOS_FINALES)
            ->whereNotExists(function ($sub) {
                $sub->from('gestiones_incapacidad as g')
                    ->whereColumn('g.incapacidad_id', 'i.id')
                    ->whereRaw('g.created_at >= DATEADD(day, -7, GETDATE())');
            })
            ->count();
        $sinGestion10dias = $sinGestion7dias; // backward compat alias

        // ── Listas estáticas cacheadas (cambian rara vez) ──────────────────
        $trabajadores = User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $epsList = cache()->remember('eps_list', 3600, fn () => DB::table('eps')->orderBy('nombre')->get(['id', 'nombre']));
        $arlList = cache()->remember('arl_list', 3600, fn () => DB::table('arls')->orderBy('nombre_arl')->get(['id', 'nombre_arl']));
        $pensionList = cache()->remember('pension_list', 3600, fn () => DB::table('pensiones')->orderBy('razon_social')->get(['id', 'razon_social']));
        $razonesSociales = DB::table('razones_sociales')
            ->where('aliado_id', $alidoId)
            ->where('estado', 'Activa')
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);
        $smmlv = $this->getSmmlv();

        // ── Opciones para los filtros de encabezado de columna ─────────────
        // Solo los valores que este aliado realmente tiene cargados: un menú
        // con 17 estados de los cuales usa 4 no ayuda a nadie.
        $opcionesColumna = $this->opcionesFiltroColumna($alidoId);

        return view('admin.incapacidades.index', compact(
            'incapacidades', 'resumen', 'totalActivas', 'totalPagadas', 'totalNoPagadas',
            'sinGestion10dias', 'sinGestion7dias',
            'trabajadores', 'epsList', 'arlList', 'pensionList', 'razonesSociales',
            'smmlv', 'vista', 'busqueda', 'opcionesColumna'
        ));
    }

    /**
     * Valores realmente presentes en las incapacidades raíz del aliado, para
     * poblar los menús desplegables de los encabezados de columna.
     *
     * @return array{entidad: array, estado: array, tipo: array}
     */
    private function opcionesFiltroColumna(int $alidoId): array
    {
        $distinct = function (string $columna) use ($alidoId) {
            return DB::table('incapacidades')
                ->where('aliado_id', $alidoId)
                ->whereNull('deleted_at')
                ->whereNull('incapacidad_padre_id')
                ->whereNotNull($columna)
                ->where($columna, '!=', '')
                ->distinct()
                ->orderBy($columna)
                ->pluck($columna)
                ->all();
        };

        $etiquetar = function (array $valores, array $catalogo, ?callable $fallback = null) {
            $out = [];
            foreach ($valores as $v) {
                $label = $catalogo[$v] ?? null;
                if (is_array($label)) {
                    $label = $label['label'] ?? null;
                }
                $out[$v] = $label ?? ($fallback ? $fallback($v) : ucfirst((string) $v));
            }

            return $out;
        };

        return [
            'entidad' => $etiquetar($distinct('tipo_entidad'), Incapacidad::TIPOS_ENTIDAD, fn ($v) => strtoupper($v)),
            'estado' => $etiquetar($distinct('estado'), Incapacidad::ESTADOS),
            'tipo' => $etiquetar($distinct('tipo_incapacidad'), Incapacidad::TIPOS_INCAPACIDAD),
        ];
    }

    // ── STORE ────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'cedula_usuario' => 'required|string|max:20',
            'tipo_incapacidad' => 'required|string',
            'tipo_entidad' => 'required|in:eps,arl,afp',
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date',
            'fecha_recibido' => 'required|date',
            'quien_recibe_id' => 'required|exists:users,id',
        ]);

        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Determinar número de prórroga
        $numProroga = 0;
        $padreId = $request->incapacidad_padre_id ?: null;

        if ($padreId) {
            $numProroga = Incapacidad::where('incapacidad_padre_id', $padreId)->count() + 1;
        }

        // Fecha terminación automática si no se proporciona
        $fechaTerminacion = $request->fecha_terminacion;
        if (! $fechaTerminacion && $request->fecha_inicio) {
            $fechaTerminacion = \Carbon\Carbon::parse($request->fecha_inicio)
                ->addDays((int) $request->dias_incapacidad - 1)
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
        $contratoId = $request->contrato_id ?: null;
        if ($contratoId) {
            $sal = DB::table('contratos')->where('id', $contratoId)->value('salario');
            $salarioBase = is_numeric($sal) && $sal > 0 ? (float) $sal : null;
        }

        $incapacidad = Incapacidad::create([
            'aliado_id' => $alidoId,
            'incapacidad_padre_id' => $padreId,
            'numero_proroga' => $numProroga,
            'contrato_id' => $contratoId,
            'cedula_usuario' => $request->cedula_usuario,
            'quien_remite' => $quienRemite,
            'quien_recibe_id' => $request->quien_recibe_id,
            'tipo_incapacidad' => $request->tipo_incapacidad,
            'dias_incapacidad' => $request->dias_incapacidad,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_terminacion' => $fechaTerminacion,
            'fecha_recibido' => $request->fecha_recibido,
            'prorroga' => $request->boolean('prorroga'),
            'tipo_entidad' => $request->tipo_entidad,
            'entidad_responsable_id' => $request->entidad_responsable_id ?: null,
            'entidad_nombre' => $entidadNombre,
            'razon_social_id' => $request->razon_social_id ?: null,
            'razon_social_nombre' => $rsNombre,
            'numero_radicado' => $request->numero_radicado,
            'fecha_radicado' => $request->fecha_radicado ?: null,
            'transcripcion_requerida' => $request->boolean('transcripcion_requerida'),
            'diagnostico' => $request->diagnostico,
            'concepto_rehabilitacion' => $request->concepto_rehabilitacion,
            'observacion' => $request->observacion,
            'descripcion_cliente' => $request->descripcion_cliente,
            'salario_base' => $salarioBase,
            'estado' => 'recibido',
            'estado_pago' => 'pendiente',
            'created_by' => Auth::id(),
        ]);

        // Calcular y guardar valor_esperado usando salario_base
        $incapacidad->calcularValorEsperado(persistir: true);

        // Gestión inicial automática con texto descriptivo
        $asesor = Auth::user();
        $fechaTexto = now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        GestionIncapacidad::create([
            'incapacidad_id' => $incapacidad->id,
            'user_id' => Auth::id(),
            'aplica_a_familia' => false,
            'tipo' => 'otro',
            'tramite' => "Incapacidad registrada en BryNex el {$fechaTexto}. Asesor: {$asesor->nombre}. Pdte. radicación en entidad.",
            'estado_resultado' => 'recibido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Bitacora::registrar(
            accion: 'created',
            modelo: 'Incapacidad',
            registroId: $incapacidad->id,
            descripcion: "Incapacidad registrada para la cédula {$incapacidad->cedula_usuario}: {$incapacidad->dias_incapacidad} días desde "
                .$incapacidad->fecha_inicio?->format('Y-m-d').' ante '.strtoupper($incapacidad->tipo_entidad).'.',
            detalle: [
                'cedula_usuario' => $incapacidad->cedula_usuario,
                'contrato_id' => $incapacidad->contrato_id,
                'tipo_incapacidad' => $incapacidad->tipo_incapacidad,
                'tipo_entidad' => $incapacidad->tipo_entidad,
                'entidad' => $incapacidad->entidad_nombre,
                'dias' => $incapacidad->dias_incapacidad,
                'fecha_inicio' => $incapacidad->fecha_inicio?->format('Y-m-d'),
                'fecha_terminacion' => $incapacidad->fecha_terminacion?->format('Y-m-d'),
                'valor_esperado' => $incapacidad->valor_esperado,
                'prorroga_de' => $padreId,
            ],
            alidoId: $alidoId
        );

        // Si la petición es AJAX/JSON, retornar JSON para que el frontend abra el modal de documentos
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'ok' => true,
                'incapacidad_id' => $incapacidad->id,
                'message' => 'Incapacidad registrada correctamente.',
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
            'tipo_entidad' => 'required|in:eps,arl,afp',
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date',
            'fecha_recibido' => 'required|date',
        ]);

        $inc = $this->incapacidadDelAliado($id);

        $entidadNombre = $this->resolverNombreEntidad(
            $request->tipo_entidad,
            $request->entidad_responsable_id
        );

        $rsNombre = $inc->razon_social_nombre;
        if ($request->razon_social_id && $request->razon_social_id != $inc->razon_social_id) {
            $rs = DB::table('razones_sociales')->find($request->razon_social_id);
            $rsNombre = $rs?->razon_social;
        }

        $contratoId = $request->contrato_id ?: $inc->contrato_id;
        $salarioBase = $inc->salario_base;
        if ($contratoId && $contratoId != $inc->contrato_id) {
            $sal = DB::table('contratos')->where('id', $contratoId)->value('salario');
            $salarioBase = is_numeric($sal) && $sal > 0 ? (float) $sal : $inc->salario_base;
        }

        // Valores previos de los campos que interesa auditar (antes del update)
        $antes = collect(['contrato_id', 'tipo_incapacidad', 'dias_incapacidad', 'fecha_inicio',
            'fecha_terminacion', 'tipo_entidad', 'entidad_nombre', 'razon_social_id',
            'numero_radicado', 'diagnostico', 'salario_base'])
            ->mapWithKeys(fn ($c) => [$c => $this->valorAuditable($inc->$c)]);

        $inc->update([
            'contrato_id' => $contratoId,
            'quien_recibe_id' => $request->quien_recibe_id ?: $inc->quien_recibe_id,
            'tipo_incapacidad' => $request->tipo_incapacidad,
            'dias_incapacidad' => $request->dias_incapacidad,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_terminacion' => $request->fecha_terminacion ?: $inc->fecha_terminacion,
            'fecha_recibido' => $request->fecha_recibido,
            'prorroga' => $request->boolean('prorroga'),
            'tipo_entidad' => $request->tipo_entidad,
            'entidad_responsable_id' => $request->entidad_responsable_id ?: $inc->entidad_responsable_id,
            'entidad_nombre' => $entidadNombre ?: $inc->entidad_nombre,
            'razon_social_id' => $request->razon_social_id ?: $inc->razon_social_id,
            'razon_social_nombre' => $rsNombre,
            'numero_radicado' => $request->numero_radicado,
            'fecha_radicado' => $request->fecha_radicado ?: $inc->fecha_radicado,
            'transcripcion_requerida' => $request->boolean('transcripcion_requerida'),
            'transcripcion_completada' => $request->boolean('transcripcion_completada'),
            'diagnostico' => $request->diagnostico,
            'concepto_rehabilitacion' => $request->concepto_rehabilitacion,
            'observacion' => $request->observacion,
            'salario_base' => $salarioBase,
        ]);

        // Recalcular valor esperado
        $smmlv = $this->getSmmlv();
        $inc->update(['valor_esperado' => $inc->calcularValorEsperado($smmlv)]);

        // Solo se registra si algo cambió de verdad: abrir y guardar el modal
        // sin tocar nada no debe ensuciar la bitácora.
        $fresco = $inc->fresh();
        $cambios = [];
        foreach ($antes as $campo => $valorViejo) {
            $valorNuevo = $this->valorAuditable($fresco->$campo);
            if ($valorViejo !== $valorNuevo) {
                $cambios[$campo] = ['old' => $valorViejo, 'new' => $valorNuevo];
            }
        }

        if (! empty($cambios)) {
            Bitacora::registrar(
                accion: 'updated',
                modelo: 'Incapacidad',
                registroId: $inc->id,
                descripcion: "Incapacidad #{$inc->id} modificada (Cédula: {$inc->cedula_usuario}). Campos: ".implode(', ', array_keys($cambios)).'.',
                detalle: ['cambios' => $cambios],
                alidoId: $inc->aliado_id
            );
        }

        return response()->json(['ok' => true, 'message' => 'Incapacidad actualizada.']);
    }

    // ── SHOW (JSON para modal de detalle) ────────────────────────────────────
    public function show(int $id)
    {
        $inc = $this->incapacidadesDelAliado()->with([
            'quienRecibe', 'creadoPor', 'razonSocial',
            'gestiones.user',
            'documentos.user',
            'prorrogas.documentos',
            'abonos.bancoCuenta',
        ])->findOrFail($id);

        // Datos del cliente
        $cliente = DB::table('clientes')
            ->where('cedula', $inc->cedula_usuario)
            ->select('cedula', 'primer_nombre', 'segundo_nombre',
                'primer_apellido', 'segundo_apellido',
                'celular', 'correo', 'cod_empresa', 'eps_id', 'pension_id')
            ->first();

        // Empresa del cliente
        $empresa = null;
        if ($cliente && $cliente->cod_empresa) {
            $empresa = DB::table('empresas')->where('id', $cliente->cod_empresa)->value('empresa');
        }

        // Calcular resumen de familia
        $familiaDias = $inc->totalDiasFamilia();
        $numProrrogas = $inc->numeroProrrogas();

        // Valor esperado total: original + todas las prórrogas
        $totalValorEsperado = (float) ($inc->valor_esperado ?? 0)
            + (float) $inc->prorrogas->sum('valor_esperado');

        // Total ya pagado (abonos tipo entrada_incapacidad en la incapacidad original)
        $totalPagado = DB::table('abonos_incapacidades')
            ->where('incapacidad_id', $inc->id)
            ->whereIn('tipo', ['entrada_incapacidad', 'pago_cliente'])
            ->sum('valor');

        // Contar prórrogas con estado NO final (pendientes de gestión)
        $prorrogasPendientes = $inc->prorrogas
            ->whereNotIn('estado', self::ESTADOS_FINALES)
            ->count();

        // ── Familia y sus gestiones ─────────────────────────────────────────
        // La pestaña Gestiones del modal muestra el timeline de toda la familia
        // con un filtro por miembro, así que se arma aquí (y no en la vista):
        // si el modal se abrió sobre una prórroga, `prorrogas` viene vacío y
        // sin esto no habría forma de ver las gestiones de sus hermanas.
        $padreId = $inc->incapacidad_padre_id ?? $inc->id;

        $familia = Incapacidad::where(function ($q) use ($padreId) {
            $q->where('id', $padreId)->orWhere('incapacidad_padre_id', $padreId);
        })
            ->orderBy('numero_proroga')
            ->get(['id', 'incapacidad_padre_id', 'numero_proroga', 'fecha_inicio',
                'fecha_terminacion', 'dias_incapacidad', 'estado']);

        $etiqueta = $familia->mapWithKeys(fn ($m) => [
            $m->id => is_null($m->incapacidad_padre_id) ? 'Original' : "Prórroga {$m->numero_proroga}",
        ]);

        $gestionesFamilia = GestionIncapacidad::with('user:id,nombre')
            ->whereIn('incapacidad_id', $familia->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->each(fn ($g) => $g->origen_label = $etiqueta[$g->incapacidad_id] ?? '—');

        $miembrosFamilia = $familia->map(fn ($m) => [
            'id' => $m->id,
            'label' => $etiqueta[$m->id],
            'es_padre' => is_null($m->incapacidad_padre_id),
            'numero_proroga' => $m->numero_proroga,
            'fecha_inicio' => $m->fecha_inicio,
            'fecha_terminacion' => $m->fecha_terminacion,
            'dias_incapacidad' => $m->dias_incapacidad,
            'estado' => $m->estado,
        ])->values();

        return response()->json([
            'incapacidad' => $inc,
            'cliente' => $cliente,
            'empresa' => $empresa,
            'semaforo' => $inc->colorSemaforo(),
            'icono' => $inc->iconoSemaforo(),
            'dias_gestion' => $inc->diasDesdeUltimaGestion(),
            'familia_dias' => $familiaDias,
            'num_prorrogas' => $numProrrogas,
            'alerta_180' => $inc->alertaDias180(),
            'total_valor_esperado' => $totalValorEsperado,
            'total_pagado' => (float) $totalPagado,
            'prorrogas_pendientes' => $prorrogasPendientes,
            // Única gestión que se puede deshacer hoy (null si no hay). La regla
            // vive en el controlador para que la vista no la duplique.
            'gestion_reversable_id' => $this->gestionReversable($inc)?->id,
            // Timeline completo de la familia + los miembros para los chips.
            'gestiones_familia' => $gestionesFamilia,
            'familia_miembros' => $miembrosFamilia,
        ]);
    }

    // ── REGISTRAR GESTIÓN ────────────────────────────────────────────────────
    public function storeGestion(Request $request, int $id)
    {
        $request->validate([
            'tipo' => 'required|string|in:llamada,correo,whatsapp,portal,otro',
            'tramite' => 'required|string',
            'alcance' => 'nullable|string|in:esta_incapacidad,toda_la_familia', // default: esta_incapacidad

            // Opcionales para pago al afiliado (estado pagada_afiliado)
            'forma_pago' => 'nullable|string|in:transferencia_bancaria,efectivo',
            'banco_cuenta_id' => 'nullable|integer',
            'valor_pago_afiliado' => 'nullable|numeric|min:0',
            'fecha_pago_afiliado' => 'nullable|date',
            'descuento_admon' => 'nullable|numeric|min:0',
            'descuento_4x1000' => 'nullable|numeric|min:0',
            'descuento_otros' => 'nullable|numeric|min:0',
        ]);

        $inc = $this->incapacidadDelAliado($id);
        $alcance = $request->input('alcance', 'esta_incapacidad');
        $esFamilia = ($alcance === 'toda_la_familia');

        // ── Validar cambio de estado manual ─────────────────────────────────
        $nuevoEstado = $request->estado_nuevo ?: null;

        // cierre_exitoso requiere que la incapacidad tenga pagada_razon_social Y pagada_afiliado
        if ($nuevoEstado === 'cierre_exitoso') {
            $tieneRS = in_array($inc->estado, ['pagada_razon_social', 'cierre_exitoso']);
            // 'pagado_afiliado' es la ortografía vieja que dejó la migración del legacy
            $tieneAf = in_array($inc->estado, ['pagada_afiliado', 'pagado_afiliado', 'cierre_exitoso']);
            // También revisar si previamente se marcó alguno de los dos estados.
            // Las gestiones reversadas no cuentan: su pago fue anulado, así que
            // dejarlas aquí permitiría cerrar una incapacidad que ya no se pagó.
            $historial = GestionIncapacidad::where('incapacidad_id', $inc->id)
                ->whereIn('estado_nuevo', ['pagada_razon_social', 'pagada_afiliado'])
                ->whereNull('revertida_at')
                ->pluck('estado_nuevo');
            $tieneRS = $tieneRS || $historial->contains('pagada_razon_social');
            $tieneAf = $tieneAf || $historial->contains('pagada_afiliado');

            // Pago directo de la entidad al afiliado (típico tras derecho de petición
            // o tutela): la plata nunca entró a la razón social, así que no existe ni
            // puede existir un 'pagada_razon_social'. Se reconoce porque no hay
            // ninguna entrada de dinero registrada para esta incapacidad.
            $huboEntradaRS = DB::table('abonos_incapacidades')
                ->where('incapacidad_id', $inc->id)
                ->where('tipo', 'entrada_incapacidad')
                ->exists();

            if (! $tieneAf || (! $tieneRS && $huboEntradaRS)) {
                return response()->json([
                    'ok' => false,
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

        $incActualizar = $nuevoEstado ? Incapacidad::find($incGestionId) : null;

        // Pago directo: se pasa a 'pagada_afiliado' sin pasar por 'pagada_razon_social'
        // porque la entidad le consignó al afiliado, no al aliado (pasa sobre todo
        // cuando el pago sale de un derecho de petición o una tutela). No se toca el
        // efectivo del aliado — ni gasto, ni consignación, ni descuentos —, solo se
        // deja constancia de que la incapacidad ya no tiene plata por entrar.
        // Se exige que sea una transición real: si la incapacidad ya está en
        // 'pagada_afiliado' y solo se está registrando otra gestión sin cambiar el
        // estado, no se vuelve a abonar.
        $esPagoDirectoAfiliado = $incActualizar
            && $nuevoEstado === 'pagada_afiliado'
            && ! in_array($incActualizar->estado, ['pagada_razon_social', 'pagada_afiliado', 'pagado_afiliado', 'cierre_exitoso'], true);

        // Se valida antes de crear la gestión: si se validara después quedaría una
        // gestión diciendo que la incapacidad pasó a pagada, con la incapacidad sin tocar.
        if ($esPagoDirectoAfiliado && (! $request->filled('valor_pago_afiliado') || (float) $request->valor_pago_afiliado <= 0)) {
            return response()->json([
                'ok' => false,
                'message' => 'Ingresa el valor que la entidad le pagó directamente al afiliado.',
            ], 422);
        }

        $gestion = GestionIncapacidad::create([
            'incapacidad_id' => $incGestionId,
            'user_id' => Auth::id(),
            'aplica_a_familia' => $esFamilia,
            'tipo' => $request->tipo,
            'tramite' => $request->tramite,
            'respuesta' => $request->respuesta,
            'estado_resultado' => $nuevoEstado,
            'cambia_estado' => (bool) $nuevoEstado,
            'estado_nuevo' => $nuevoEstado,
            // Se guarda de dónde venía para poder devolverla ahí si hay que
            // reversar. Sin esto habría que adivinar mirando la gestión anterior.
            'estado_anterior' => $nuevoEstado ? ($incActualizar?->estado ?? $inc->estado) : null,
            'created_at' => now(),
        ]);

        // ── Aplicar cambio de estado si se especificó ────────────────────────
        if ($nuevoEstado) {
            if ($incActualizar) {
                $incActualizar->estado = $nuevoEstado;
                // Si cierre_exitoso, mantener ese estado (ya no mapear a 'pagada' legacy)
                if ($nuevoEstado === 'cierre_exitoso') {
                    $incActualizar->estado = 'cierre_exitoso';
                }
                // Si radicada, guardar número y fecha
                if ($nuevoEstado === 'radicada') {
                    if ($request->filled('numero_radicado')) {
                        $incActualizar->numero_radicado = $request->numero_radicado;
                    }
                    if ($request->filled('fecha_radicado')) {
                        $incActualizar->fecha_radicado = $request->fecha_radicado;
                    }
                }
                // Si pagada_razon_social, registrar abono + consignación
                if ($nuevoEstado === 'pagada_razon_social' && $request->filled('valor_pago_rs')) {
                    if ($request->forma_pago_rs === 'transferencia') {
                        if (! $request->banco_cuenta_id) {
                            return response()->json([
                                'ok' => false,
                                'message' => 'Debes seleccionar la cuenta bancaria de destino para el pago por transferencia.',
                            ], 422);
                        }
                        $cuenta = DB::table('banco_cuentas')->where('id', $request->banco_cuenta_id)->first();
                        if (! $cuenta || empty(trim((string) $cuenta->nit))) {
                            return response()->json([
                                'ok' => false,
                                'message' => 'La cuenta bancaria de destino seleccionada debe tener un NIT configurado.',
                            ], 422);
                        }
                    }

                    $formaLabel = match ($request->forma_pago_rs ?? 'otro') {
                        'transferencia' => 'Transferencia/Consignación bancaria',
                        'opi' => 'OPI (Orden de Pago Inmediata - ARL)',
                        'odi' => 'ODI (Orden de la entidad)',
                        'cheque' => 'Cheque',
                        'directo' => 'Pago directo al cliente',
                        default => 'Otro medio de pago',
                    };
                    $refLabel = $request->filled('ref_pago_rs') ? ' · Ref: '.$request->ref_pago_rs : '';
                    $obsAbono = "{$formaLabel}{$refLabel} — Incapacidad #{$incActualizar->id}";

                    // Solo crear consignación bancaria si es transferencia y hay cuenta
                    $consignacionId = null;
                    if ($request->forma_pago_rs === 'transferencia') {
                        $consignacionId = DB::table('consignaciones')->insertGetId([
                            'aliado_id' => $incActualizar->aliado_id,
                            'fecha' => $request->fecha_pago_rs ?? now()->toDateString(),
                            'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                            'valor' => $request->valor_pago_rs,
                            'referencia' => $request->ref_pago_rs ?: ('Pago incapacidad #'.$incActualizar->id),
                            'observacion' => $obsAbono,
                            'tipo' => 'incapacidad',
                            'incapacidad_id' => $incActualizar->id,
                            'gestion_incapacidad_id' => $gestion->id,
                            'confirmado' => 0,
                            'usuario_id' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $rsIdAbono = $incActualizar->razon_social_id;
                    if (! $rsIdAbono && $incActualizar->contrato_id) {
                        $rsIdAbono = DB::table('contratos')->where('id', $incActualizar->contrato_id)->value('razon_social_id') ?: null;
                    }

                    DB::table('abonos_incapacidades')->insert([
                        'aliado_id' => $incActualizar->aliado_id,
                        'incapacidad_id' => $incActualizar->id,
                        'razon_social_id' => $rsIdAbono,
                        'gestion_incapacidad_id' => $gestion->id,
                        'tipo' => 'entrada_incapacidad',
                        'valor' => $request->valor_pago_rs,
                        'fecha' => $request->fecha_pago_rs ?? now()->toDateString(),
                        'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                        'consignacion_id' => $consignacionId,
                        'usuario_id' => Auth::id(),
                        'observacion' => $obsAbono,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Pago directo de la entidad al afiliado: sin gasto, sin consignación
                // y sin descuentos. Se registra solo el abono para que la incapacidad
                // deje de figurar con saldo por entrar.
                if ($esPagoDirectoAfiliado) {
                    $valorDirecto = (float) $request->valor_pago_afiliado;
                    $fechaPago = $request->fecha_pago_afiliado ?? now()->toDateString();

                    $rsIdAbono = $incActualizar->razon_social_id;
                    if (! $rsIdAbono && $incActualizar->contrato_id) {
                        $rsIdAbono = DB::table('contratos')->where('id', $incActualizar->contrato_id)->value('razon_social_id') ?: null;
                    }

                    $obsAbono = 'Pago directo de la entidad al afiliado — no ingresó a la razón social'
                        ." — Incapacidad #{$incActualizar->id}";

                    DB::table('abonos_incapacidades')->insert([
                        'aliado_id' => $incActualizar->aliado_id,
                        'incapacidad_id' => $incActualizar->id,
                        'razon_social_id' => $rsIdAbono,
                        'gestion_incapacidad_id' => $gestion->id,
                        'tipo' => 'pago_cliente',
                        'valor' => $valorDirecto,
                        'fecha' => $fechaPago,
                        'banco_cuenta_id' => null,
                        'usuario_id' => Auth::id(),
                        'observacion' => $obsAbono,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $incActualizar->estado_pago = 'pagado_afiliado';
                    $incActualizar->valor_pago = $valorDirecto;
                    $incActualizar->fecha_pago = $fechaPago;
                    $incActualizar->pagado_a = 'cliente';
                    $incActualizar->detalle_pago = $obsAbono;
                }

                // Si pagada_afiliado, registrar abono pago_cliente + gasto admin tipo pago_incapacidad
                if ($nuevoEstado === 'pagada_afiliado' && ! $esPagoDirectoAfiliado && $request->filled('valor_pago_afiliado')) {
                    $valorNeto = $request->valor_pago_afiliado;
                    $fechaPago = $request->fecha_pago_afiliado ?? now()->toDateString();
                    $formaPago = $request->forma_pago ?? 'transferencia_bancaria';
                    $bancoId = $request->banco_cuenta_id ?: null;

                    $admon = $request->input('descuento_admon', 0);
                    $x1000 = $request->input('descuento_4x1000', 0);
                    $otros = $request->input('descuento_otros', 0);
                    $valorBruto = (float) $valorNeto + (float) $admon + (float) $x1000 + (float) $otros;

                    $obsAbono = "Pago al afiliado (Neto: \${$valorNeto} · Admon: \${$admon} · 4x1000: \${$x1000} · Otros: \${$otros}) — Incapacidad #{$incActualizar->id}";

                    // 1. Guardar abono tipo pago_cliente
                    $rsIdAbono = $incActualizar->razon_social_id;
                    if (! $rsIdAbono && $incActualizar->contrato_id) {
                        $rsIdAbono = DB::table('contratos')->where('id', $incActualizar->contrato_id)->value('razon_social_id') ?: null;
                    }

                    DB::table('abonos_incapacidades')->insert([
                        'aliado_id' => $incActualizar->aliado_id,
                        'incapacidad_id' => $incActualizar->id,
                        'razon_social_id' => $rsIdAbono,
                        'gestion_incapacidad_id' => $gestion->id,
                        'tipo' => 'pago_cliente',
                        'valor' => $valorNeto,
                        'fecha' => $fechaPago,
                        'banco_cuenta_id' => $bancoId,
                        'usuario_id' => Auth::id(),
                        'observacion' => $obsAbono,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // 2. Obtener el nombre del cliente/afiliado para el gasto
                    $clienteObj = DB::table('clientes')->where('cedula', $incActualizar->cedula_usuario)->first();
                    $nombreCliente = $clienteObj
                        ? trim("{$clienteObj->primer_nombre} ".($clienteObj->segundo_nombre ? "{$clienteObj->segundo_nombre} " : '')."{$clienteObj->primer_apellido}")
                        : $incActualizar->cedula_usuario;

                    // 3. Crear Gasto tipo pago_incapacidad (neto al afiliado — Canal 5)
                    DB::table('gastos')->insert([
                        'aliado_id' => $incActualizar->aliado_id,
                        'usuario_id' => Auth::id(),
                        'cuadre_id' => null,
                        'fecha' => $fechaPago,
                        'tipo' => 'pago_incapacidad',
                        'incapacidad_id' => $incActualizar->id,
                        'gestion_incapacidad_id' => $gestion->id,
                        'descripcion' => "Pago incapacidad #{$incActualizar->id} al afiliado (Neto: \${$valorNeto})",
                        'pagado_a' => $nombreCliente,
                        'cc_pagado_a' => $incActualizar->cedula_usuario,
                        'forma_pago' => $formaPago,
                        'banco_origen_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                        'valor' => $valorNeto,
                        'recibo_caja' => null,
                        'lugar' => null,
                        'observacion' => $obsAbono,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // 3b. Gasto tipo cuatropormil_incapacidad (Canal 5)
                    if ((float) $x1000 > 0) {
                        DB::table('gastos')->insert([
                            'aliado_id' => $incActualizar->aliado_id,
                            'usuario_id' => Auth::id(),
                            'cuadre_id' => null,
                            'fecha' => $fechaPago,
                            'tipo' => 'cuatropormil_incapacidad',
                            'incapacidad_id' => $incActualizar->id,
                            'gestion_incapacidad_id' => $gestion->id,
                            'descripcion' => "4x1000 incapacidad #{$incActualizar->id}",
                            'pagado_a' => 'DIAN',
                            'cc_pagado_a' => null,
                            'forma_pago' => $formaPago,
                            'banco_origen_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                            'valor' => (int) $x1000,
                            'recibo_caja' => null,
                            'lugar' => null,
                            'observacion' => "4x1000 cobrado en pago de incapacidad #{$incActualizar->id}",
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 3c. Gasto tipo otros_incapacidad (Canal 5)
                    if ((float) $otros > 0) {
                        DB::table('gastos')->insert([
                            'aliado_id' => $incActualizar->aliado_id,
                            'usuario_id' => Auth::id(),
                            'cuadre_id' => null,
                            'fecha' => $fechaPago,
                            'tipo' => 'otros_incapacidad',
                            'incapacidad_id' => $incActualizar->id,
                            'gestion_incapacidad_id' => $gestion->id,
                            'descripcion' => "Otros descuentos incapacidad #{$incActualizar->id}",
                            'pagado_a' => $nombreCliente,
                            'cc_pagado_a' => $incActualizar->cedula_usuario,
                            'forma_pago' => $formaPago,
                            'banco_origen_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                            'valor' => (int) $otros,
                            'recibo_caja' => null,
                            'lugar' => null,
                            'observacion' => "Otros descuentos pago incapacidad #{$incActualizar->id}",
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 3d. Gasto tipo admon_incapacidad (ganancia → Canal 1)
                    if ((float) $admon > 0) {
                        DB::table('gastos')->insert([
                            'aliado_id' => $incActualizar->aliado_id,
                            'usuario_id' => Auth::id(),
                            'cuadre_id' => null,
                            'fecha' => $fechaPago,
                            'tipo' => 'admon_incapacidad',
                            'incapacidad_id' => $incActualizar->id,
                            'gestion_incapacidad_id' => $gestion->id,
                            'descripcion' => "Ganancia admon incapacidad #{$incActualizar->id}",
                            'pagado_a' => null,
                            'cc_pagado_a' => null,
                            'forma_pago' => 'interno',
                            'banco_origen_id' => null,
                            'valor' => (int) $admon,
                            'recibo_caja' => null,
                            'lugar' => null,
                            'observacion' => "Comisión admon incapacidad #{$incActualizar->id} — pasa a Canal 1",
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // 4. Actualizar la incapacidad con la fecha de pago y estado de pago
                    $incActualizar->estado_pago = 'pagado_afiliado';
                    $incActualizar->valor_pago = $valorNeto;
                    $incActualizar->fecha_pago = $fechaPago;
                    $incActualizar->pagado_a = 'cliente';
                    $incActualizar->detalle_pago = $obsAbono;
                }

                $incActualizar->saveQuietly();
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Gestión registrada.',
            'estado' => $inc->fresh()->estado,
            'alcance' => $alcance,
            'consignacion_id' => $consignacionId ?? null,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // REVERSAR UN CAMBIO DE ESTADO
    //
    // Un estado mal puesto no es solo una etiqueta: "Pagada al Afiliado" crea
    // el gasto del pago, el 4x1000, la ganancia de administración y el abono
    // que le quita el saldo pendiente a la incapacidad. Deshacerlo a mano en la
    // BD es justo lo que no se debe hacer, así que se deshace desde la interfaz,
    // con motivo obligatorio y bitácora.
    //
    // Solo se puede reversar la ÚLTIMA gestión que cambió el estado, y solo si
    // la incapacidad sigue en ese estado. Reversar una del medio dejaría
    // combinaciones imposibles (pagada al afiliado sin la plata que la respalda).
    // ════════════════════════════════════════════════════════════════════════

    /**
     * La única gestión reversable de esta incapacidad, o null.
     *
     * Excluye las gestiones de reversión: deshacer una reversión sería un
     * "rehacer" que no puede reconstruir los gastos ya borrados.
     */
    private function gestionReversable(Incapacidad $inc): ?GestionIncapacidad
    {
        $g = GestionIncapacidad::where('incapacidad_id', $inc->id)
            ->where('cambia_estado', true)
            ->where('es_reversion', false)
            ->whereNull('revertida_at')
            ->orderByDesc('id')
            ->first();

        if (! $g || ! $g->estado_nuevo) {
            return null;
        }

        // La incapacidad tiene que seguir donde esta gestión la dejó. Si alguien
        // movió el estado por otro camino (abono, edición), esta ya no manda.
        $equivalentes = ['pagada_afiliado', 'pagado_afiliado'];
        $mismoEstado = $g->estado_nuevo === $inc->estado
            || (in_array($g->estado_nuevo, $equivalentes, true) && in_array($inc->estado, $equivalentes, true));

        return $mismoEstado ? $g : null;
    }

    /** Estado al que vuelve la incapacidad si se reversa esta gestión. */
    private function estadoDestinoReversion(Incapacidad $inc, GestionIncapacidad $g): string
    {
        if ($g->estado_anterior) {
            return $g->estado_anterior;
        }

        // Gestiones anteriores a esta funcionalidad no guardaron de dónde venían:
        // se deduce del último cambio de estado que hubo antes.
        $previa = GestionIncapacidad::where('incapacidad_id', $inc->id)
            ->where('cambia_estado', true)
            ->whereNull('revertida_at')
            ->where('id', '<', $g->id)
            ->orderByDesc('id')
            ->value('estado_nuevo');

        return $previa ?: 'recibido';
    }

    /**
     * Qué se va a deshacer: movimientos a borrar y razones para no dejar hacerlo.
     *
     * Los movimientos se buscan primero por el vínculo directo con la gestión.
     * Los registros anteriores a esa columna no lo tienen, así que se cae al
     * respaldo: mismo tipo, misma incapacidad y la fecha de pago que quedó
     * guardada en la incapacidad.
     */
    private function analizarReversion(Incapacidad $inc, GestionIncapacidad $g): array
    {
        $estadoDestino = $this->estadoDestinoReversion($inc, $g);
        $fechaPago = $inc->fecha_pago?->format('Y-m-d');

        $gastos = collect();
        $abonos = collect();
        $consignaciones = collect();

        $esPagoAfiliado = in_array($g->estado_nuevo, ['pagada_afiliado', 'pagado_afiliado'], true);
        $esPagoRS = $g->estado_nuevo === 'pagada_razon_social';

        if ($esPagoAfiliado) {
            $gastos = DB::table('gastos')
                ->where('aliado_id', $inc->aliado_id)
                ->whereIn('tipo', \App\Models\Gasto::TIPOS_INCAPACIDAD)
                ->where('gestion_incapacidad_id', $g->id)
                ->get();

            if ($gastos->isEmpty()) {
                $q = DB::table('gastos')
                    ->where('aliado_id', $inc->aliado_id)
                    ->whereIn('tipo', \App\Models\Gasto::TIPOS_INCAPACIDAD)
                    ->where('incapacidad_id', $inc->id)
                    ->whereNull('gestion_incapacidad_id');
                if ($fechaPago) {
                    $q->whereDate('fecha', $fechaPago);
                }
                $gastos = $q->get();
            }

            $abonos = DB::table('abonos_incapacidades')
                ->where('incapacidad_id', $inc->id)
                ->where('tipo', 'pago_cliente')
                ->where('gestion_incapacidad_id', $g->id)
                ->get();

            if ($abonos->isEmpty()) {
                $q = DB::table('abonos_incapacidades')
                    ->where('incapacidad_id', $inc->id)
                    ->where('tipo', 'pago_cliente')
                    ->whereNull('gestion_incapacidad_id');
                if ($fechaPago) {
                    $q->whereDate('fecha', $fechaPago);
                }
                $abonos = $q->get();
            }
        }

        if ($esPagoRS) {
            $abonos = DB::table('abonos_incapacidades')
                ->where('incapacidad_id', $inc->id)
                ->where('tipo', 'entrada_incapacidad')
                ->where('gestion_incapacidad_id', $g->id)
                ->get();

            if ($abonos->isEmpty()) {
                // Sin vínculo: la entrada más reciente es la que dejó esta gestión.
                $abonos = DB::table('abonos_incapacidades')
                    ->where('incapacidad_id', $inc->id)
                    ->where('tipo', 'entrada_incapacidad')
                    ->whereNull('gestion_incapacidad_id')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->get();
            }

            $idsConsignacion = $abonos->pluck('consignacion_id')->filter()->all();
            if ($idsConsignacion) {
                $consignaciones = DB::table('consignaciones')
                    ->whereIn('id', $idsConsignacion)
                    ->whereNull('deleted_at')
                    ->get();
            }
        }

        // ── Bloqueos: lo que ya salió del módulo y afecta a terceros ─────────
        $bloqueos = [];

        $cuadresIds = $gastos->pluck('cuadre_id')->filter()->unique();
        if ($cuadresIds->isNotEmpty()) {
            $fechas = DB::table('cuadres')->whereIn('id', $cuadresIds)->pluck('fecha_fin', 'id');
            foreach ($gastos->whereNotNull('cuadre_id') as $ga) {
                $fecha = $fechas[$ga->cuadre_id] ?? null;
                $fecha = $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'sin fecha';
                $bloqueos[] = 'El gasto #'.$ga->id.' de $'.number_format((float) $ga->valor, 0, ',', '.')
                    ." ya quedó dentro del cuadre del {$fecha}: borrarlo cambia el cierre de ese día.";
            }
        }

        foreach ($consignaciones as $c) {
            if ($c->confirmado) {
                $bloqueos[] = 'La consignación #'.$c->id.' de $'.number_format((float) $c->valor, 0, ',', '.')
                    .' ya fue confirmada en banco.';
            }
        }

        return [
            'estado_destino' => $estadoDestino,
            'gastos' => $gastos,
            'abonos' => $abonos,
            'consignaciones' => $consignaciones,
            'bloqueos' => $bloqueos,
        ];
    }

    /** ¿Este usuario puede pasar por encima de un bloqueo? */
    private function puedeForzarReversion(): bool
    {
        $user = Auth::user();

        return $user && $user->es_brynex && $user->hasRole('superadmin');
    }

    /** Resumen legible de un movimiento, para la pantalla de confirmación. */
    private function etiquetaMovimiento(string $prefijo, $fila, string $detalle): array
    {
        return [
            'id' => $fila->id,
            'etiqueta' => $prefijo,
            'detalle' => $detalle,
            'valor' => (float) $fila->valor,
            'fecha' => $fila->fecha ? \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') : null,
        ];
    }

    // ── PREVIEW: qué pasaría si se reversa ───────────────────────────────────
    public function previewReversion(int $id)
    {
        $inc = $this->incapacidadDelAliado($id);
        $g = $this->gestionReversable($inc);

        if (! $g) {
            return response()->json([
                'ok' => false,
                'message' => 'Esta incapacidad no tiene un cambio de estado reversable. '
                    .'Solo se puede deshacer el último cambio, y únicamente si la incapacidad sigue en ese estado.',
            ], 422);
        }

        $analisis = $this->analizarReversion($inc, $g);

        $movimientos = [];
        foreach ($analisis['gastos'] as $ga) {
            $movimientos[] = $this->etiquetaMovimiento('🧾 Gasto', $ga, \App\Models\Gasto::TIPOS[$ga->tipo] ?? $ga->tipo);
        }
        foreach ($analisis['abonos'] as $ab) {
            $etiqueta = $ab->tipo === 'entrada_incapacidad' ? '💵 Entrada de la entidad' : '💸 Abono pago al afiliado';
            $movimientos[] = $this->etiquetaMovimiento($etiqueta, $ab, $ab->observacion ?? '');
        }
        foreach ($analisis['consignaciones'] as $c) {
            $movimientos[] = $this->etiquetaMovimiento('🏦 Consignación', $c, $c->referencia ?? '');
        }

        return response()->json([
            'ok' => true,
            'gestion_id' => $g->id,
            'gestion_fecha' => $g->created_at?->format('d/m/Y H:i'),
            'gestion_usuario' => optional(User::find($g->user_id))->nombre,
            'incapacidad_id' => $inc->id,
            'es_prorroga' => (bool) $inc->incapacidad_padre_id,
            'numero_proroga' => $inc->numero_proroga,
            'estado_actual' => $g->estado_nuevo,
            'estado_actual_label' => Incapacidad::ESTADOS[$g->estado_nuevo]['label'] ?? $g->estado_nuevo,
            'estado_destino' => $analisis['estado_destino'],
            'estado_destino_label' => Incapacidad::ESTADOS[$analisis['estado_destino']]['label'] ?? $analisis['estado_destino'],
            'movimientos' => $movimientos,
            // Solo los gastos suman: el abono es el mismo dinero visto desde la
            // incapacidad, sumarlo daría el doble de lo que realmente se mueve.
            'total_gastos' => (float) $analisis['gastos']->sum('valor'),
            'bloqueos' => $analisis['bloqueos'],
            'puede_forzar' => $this->puedeForzarReversion(),
        ]);
    }

    // ── EJECUTAR LA REVERSIÓN ────────────────────────────────────────────────
    public function revertirGestion(Request $request, int $id)
    {
        $request->validate([
            'motivo' => 'required|string|min:5|max:500',
            'gestion_id' => 'nullable|integer',
        ]);

        $inc = $this->incapacidadDelAliado($id);
        $g = $this->gestionReversable($inc);

        if (! $g) {
            return response()->json([
                'ok' => false,
                'message' => 'Esta incapacidad no tiene un cambio de estado reversable.',
            ], 422);
        }

        // La pantalla pudo quedar vieja: si entre que se abrió y se confirmó
        // alguien registró otra gestión, se reversaría algo distinto a lo que
        // el usuario vio en el resumen.
        if ($request->filled('gestion_id') && (int) $request->gestion_id !== (int) $g->id) {
            return response()->json([
                'ok' => false,
                'message' => 'La incapacidad cambió mientras revisabas el resumen. Vuelve a abrir el detalle.',
            ], 409);
        }

        $analisis = $this->analizarReversion($inc, $g);
        $forzar = $this->puedeForzarReversion();

        if ($analisis['bloqueos'] && ! $forzar) {
            return response()->json([
                'ok' => false,
                'message' => 'No se puede reversar: '.implode(' ', $analisis['bloqueos'])
                    .' Solo un superadmin de BryNex puede forzarlo.',
                'bloqueos' => $analisis['bloqueos'],
            ], 403);
        }

        $motivo = trim($request->motivo);
        $estadoAnteriorReal = $inc->estado;
        $estadoDestino = $analisis['estado_destino'];

        // Snapshot ANTES de borrar: es lo único que queda de estos movimientos.
        $snapshot = [
            'gestion' => $g->toArray(),
            'estado_previo' => $estadoAnteriorReal,
            'estado_destino' => $estadoDestino,
            'gastos' => $analisis['gastos']->map(fn ($x) => (array) $x)->all(),
            'abonos' => $analisis['abonos']->map(fn ($x) => (array) $x)->all(),
            'consignaciones' => $analisis['consignaciones']->map(fn ($x) => (array) $x)->all(),
            'bloqueos_forzados' => $forzar ? $analisis['bloqueos'] : [],
            'motivo' => $motivo,
        ];

        DB::transaction(function () use ($inc, $g, $analisis, $motivo, $estadoDestino) {
            if ($analisis['gastos']->isNotEmpty()) {
                DB::table('gastos')->whereIn('id', $analisis['gastos']->pluck('id'))->delete();
            }
            if ($analisis['abonos']->isNotEmpty()) {
                DB::table('abonos_incapacidades')->whereIn('id', $analisis['abonos']->pluck('id'))->delete();
            }
            foreach ($analisis['consignaciones'] as $c) {
                // Soft delete: la consignación es un documento del banco, no se
                // borra de verdad, se anula.
                \App\Models\Consignacion::where('id', $c->id)->update([
                    'observacion' => trim((string) $c->observacion).' — ANULADA por reversión de incapacidad #'.$inc->id,
                    'updated_at' => now(),
                ]);
                \App\Models\Consignacion::where('id', $c->id)->delete();
            }

            // Si lo que se deshace es el pago, la incapacidad vuelve a quedar
            // sin pagar. El número de radicado NO se toca: sigue siendo cierto
            // que se radicó, aunque el estado retroceda.
            if (in_array($g->estado_nuevo, ['pagada_afiliado', 'pagado_afiliado'], true)) {
                $inc->estado_pago = 'pendiente';
                $inc->valor_pago = null;
                $inc->fecha_pago = null;
                $inc->pagado_a = null;
                $inc->detalle_pago = null;
            }

            $inc->estado = $estadoDestino;
            $inc->saveQuietly();

            $g->revertida_at = now();
            $g->revertida_por = Auth::id();
            $g->revertida_motivo = $motivo;
            $g->save();

            $labelDesde = Incapacidad::ESTADOS[$g->estado_nuevo]['label'] ?? $g->estado_nuevo;
            $labelHasta = Incapacidad::ESTADOS[$estadoDestino]['label'] ?? $estadoDestino;

            GestionIncapacidad::create([
                'incapacidad_id' => $inc->id,
                'user_id' => Auth::id(),
                'aplica_a_familia' => false,
                'tipo' => 'otro',
                'tramite' => "↩️ Reversión: {$labelDesde} → {$labelHasta}",
                'respuesta' => $motivo,
                'estado_resultado' => $estadoDestino,
                'cambia_estado' => true,
                'estado_nuevo' => $estadoDestino,
                'estado_anterior' => $g->estado_nuevo,
                'es_reversion' => true,
                'created_at' => now(),
            ]);
        });

        Bitacora::registrar(
            accion: 'deleted',
            modelo: 'Incapacidad',
            registroId: $inc->id,
            descripcion: "Estado reversado en incapacidad #{$inc->id} (Cédula: {$inc->cedula_usuario}): "
                .($estadoAnteriorReal)." → {$estadoDestino}. Se anularon "
                .$analisis['gastos']->count().' gasto(s), '
                .$analisis['abonos']->count().' abono(s) y '
                .$analisis['consignaciones']->count()." consignación(es). Motivo: {$motivo}",
            detalle: $snapshot,
            alidoId: $inc->aliado_id
        );

        return response()->json([
            'ok' => true,
            'message' => 'Estado reversado. Se deshicieron los movimientos que había generado.',
            'estado' => $estadoDestino,
            'estado_label' => Incapacidad::ESTADOS[$estadoDestino]['label'] ?? $estadoDestino,
        ]);
    }

    // ── CUENTAS BANCARIAS DE LA RAZÓN SOCIAL ────────────────────────────────
    public function cuentasRazonSocial(int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $inc = $this->incapacidadDelAliado($id);

        // Obtener NIT y razon_social_id de la RS de la incapacidad
        $rsId = $inc->razon_social_id;
        $nit = null;
        $rs = null;

        // Fallback 1: Si no tiene razon_social_id pero tiene contrato, intentar obtener razon_social_id del contrato
        if (! $rsId && $inc->contrato_id) {
            $rsId = DB::table('contratos')->where('id', $inc->contrato_id)->value('razon_social_id');
        }

        // Obtener datos de la razón social si tenemos un ID
        if ($rsId) {
            $rs = DB::table('razones_sociales')->where('id', $rsId)->first(['id', 'nit', 'razon_social']);
            $nit = $rs?->nit ? trim((string) $rs->nit) : null;
        }

        // Fallback 2: Si no se pudo obtener el NIT pero razon_social_nombre parece ser un NIT
        if (! $nit && $inc->razon_social_nombre) {
            $cleanNombre = trim((string) $inc->razon_social_nombre);
            // Si tiene formato de NIT (solo dígitos, puntos, espacios, guiones)
            if (preg_match('/^[0-9\.\s-]+$/', $cleanNombre)) {
                $nit = $cleanNombre;
                // Intentar buscar la razón social por este NIT para tener el ID y nombre real
                if (! $rsId) {
                    $cleanNit = preg_replace('/[\.\s-]/', '', $nit);
                    $rsMatch = DB::table('razones_sociales')
                        ->whereRaw("REPLACE(REPLACE(REPLACE(nit, '.', ''), ' ', ''), '-', '') = ?", [$cleanNit])
                        ->first(['id', 'nit', 'razon_social']);
                    if ($rsMatch) {
                        $rsId = $rsMatch->id;
                        $rs = $rsMatch;
                    }
                }
            }
        }

        $base = DB::table('banco_cuentas')
            ->where('aliado_id', $inc->aliado_id)
            ->where('activo', true);

        // Prioridad 1: cuentas que coincidan por NIT del titular
        $cuentas = collect();
        if ($nit) {
            $cuentas = (clone $base)
                ->where(function ($q) use ($nit) {
                    // Comparar NIT sin puntos, espacios ni guiones para evitar mismatch de formato
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(nit, '.', ''), ' ', ''), '-', '') = ?", [preg_replace('/[\.\s-]/', '', $nit)]);
                })
                ->orderBy('banco')
                ->get(['id', 'nombre', 'banco', 'tipo_cuenta', 'numero_cuenta', 'nit']);
        }

        // Prioridad 2: si no hay por NIT, buscar por razon_social_id
        if ($cuentas->isEmpty() && $rsId) {
            $cuentas = (clone $base)
                ->where('razon_social_id', $rsId)
                ->orderBy('banco')
                ->get(['id', 'nombre', 'banco', 'tipo_cuenta', 'numero_cuenta', 'nit']);
        }

        return response()->json([
            'ok' => true,
            'cuentas' => $cuentas,
            'nit_rs' => $nit,
            'rs_nombre' => $rs?->razon_social ?? $inc->razon_social_nombre ?? null,
            'buscado_por' => $cuentas->isNotEmpty()
                ? ($nit ? "NIT: {$nit}" : "razon_social_id: {$rsId}")
                : 'sin coincidencia',
        ]);
    }

    // ── GENERAR LINK DE SUBIDA ───────────────────────────────────────────────
    public function generarLink(int $id)
    {
        $inc = $this->incapacidadDelAliado($id);
        $link = $inc->link_subida; // genera token si no existe
        $wa = $inc->mensaje_whatsapp_subida;

        return response()->json(['ok' => true, 'link' => $link, 'whatsapp' => $wa]);
    }

    // ── STORE ABONO (préstamo / pago EPS / pago cliente) ────────────────────
    public function storeAbono(Request $request, int $id)
    {
        $request->validate([
            'tipo' => 'required|in:abono,entrada_incapacidad,pago_cliente',
            'valor' => 'required|numeric|min:1',
            'fecha' => 'required|date',
        ]);

        $inc = $this->incapacidadDelAliado($id);
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Guardar imagen si viene (I/O de disco, no participa de la transacción de BD)
        $imagenPath = null;
        if ($request->hasFile('imagen')) {
            $imagenPath = app(CompresorDocumentoService::class)->guardar(
                $request->file('imagen'),
                "incapacidades/{$alidoId}/{$inc->cedula_usuario}/{$id}/pagos",
                self::DISCO_DOCUMENTOS
            );
        }

        // La entrada bancaria (consignaciones) y el abono deben registrarse juntos:
        // sin transacción, un fallo entre las dos escrituras dejaba una consignación
        // (dinero marcado como entrado al banco) sin ningún AbonoIncapacidad que la
        // respalde — un fantasma contable. Ver docs/auditoria-calidad.md, hallazgo E-1.
        DB::transaction(function () use ($request, $inc, $alidoId, $imagenPath) {
            // Si es entrada_incapacidad → crear también en consignaciones (Canal 5 entrada bancaria)
            $consignacionId = null;
            if ($request->tipo === 'entrada_incapacidad' && $request->banco_cuenta_id) {
                $consignacionId = DB::table('consignaciones')->insertGetId([
                    'aliado_id' => $alidoId,
                    'incapacidad_id' => $inc->id,
                    'banco_cuenta_id' => $request->banco_cuenta_id,
                    'fecha' => $request->fecha,
                    'valor' => $request->valor,
                    'referencia' => $request->referencia,
                    'confirmado' => true,
                    'observacion' => $request->observacion,
                    'usuario_id' => Auth::id(),
                    'tipo' => 'ingreso',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \App\Models\AbonoIncapacidad::create([
                'aliado_id' => $alidoId,
                'incapacidad_id' => $inc->id,
                'razon_social_id' => $request->razon_social_id ?: $inc->razon_social_id,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'fecha' => $request->fecha,
                'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                'consignacion_id' => $consignacionId,
                'usuario_id' => Auth::id(),
                'observacion' => $request->observacion,
                'imagen_path' => $imagenPath,
            ]);

            // Si es pago al cliente → marcar estado_pago
            if ($request->tipo === 'pago_cliente') {
                $saldo = $inc->fresh()->saldo_pendiente;
                if ($saldo <= 0) {
                    $inc->update(['estado_pago' => 'pagado_afiliado', 'estado' => 'pagada']);
                }
            }
        });

        $inc->refresh()->load('abonos');

        // Se registra después del commit: si la transacción falla, DB::transaction
        // relanza y nunca se llega aquí.
        $etiquetaTipo = match ($request->tipo) {
            'entrada_incapacidad' => 'Entrada de la entidad',
            'pago_cliente' => 'Pago al afiliado',
            default => 'Abono',
        };
        Bitacora::registrar(
            accion: 'created',
            modelo: 'Incapacidad',
            registroId: $inc->id,
            descripcion: "{$etiquetaTipo} de $".number_format((float) $request->valor, 0, ',', '.')
                ." en incapacidad #{$inc->id} (Cédula: {$inc->cedula_usuario}). Saldo pendiente: $"
                .number_format((float) $inc->saldo_pendiente, 0, ',', '.').'.',
            detalle: [
                'tipo' => $request->tipo,
                'valor' => (float) $request->valor,
                'fecha' => $request->fecha,
                'banco_cuenta_id' => $request->banco_cuenta_id ?: null,
                'referencia' => $request->referencia,
                'observacion' => $request->observacion,
                'saldo_pendiente' => $inc->saldo_pendiente,
                'estado_pago' => $inc->estado_pago,
            ],
            alidoId: $alidoId
        );

        return response()->json([
            'ok' => true,
            'message' => 'Registrado correctamente.',
            'saldo_pendiente' => $inc->saldo_pendiente,
            'total_prestado' => $inc->total_prestado,
        ]);
    }

    // ── CREAR PRÓRROGA ───────────────────────────────────────────────────────
    public function storeProrroga(Request $request, int $padreId)
    {
        $request->validate([
            'dias_incapacidad' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date',
            'tipo_entidad' => 'required|in:eps,arl,afp',
        ]);

        $padre = $this->incapacidadDelAliado($padreId);
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;
        $numProrroga = Incapacidad::where('incapacidad_padre_id', $padreId)->count() + 1;

        $fechaTerminacion = $request->fecha_terminacion
            ?: \Carbon\Carbon::parse($request->fecha_inicio)
                ->addDays($request->dias_incapacidad - 1)->toDateString();

        $entidadNombre = $this->resolverNombreEntidad($request->tipo_entidad, $request->entidad_responsable_id);

        // Salario base: heredar del padre o releer del contrato
        $salarioBase = $padre->salario_base;
        if (! $salarioBase && $padre->contrato_id) {
            $sal = DB::table('contratos')->where('id', $padre->contrato_id)->value('salario');
            $salarioBase = is_numeric($sal) && $sal > 0 ? (float) $sal : null;
        }

        $prorroga = Incapacidad::create([
            'aliado_id' => $alidoId,
            'incapacidad_padre_id' => $padreId,
            'numero_proroga' => $numProrroga,
            'contrato_id' => $padre->contrato_id,
            'cedula_usuario' => $padre->cedula_usuario,
            'quien_remite' => $padre->quien_remite,
            'quien_recibe_id' => $padre->quien_recibe_id,
            'tipo_incapacidad' => $padre->tipo_incapacidad,
            'dias_incapacidad' => $request->dias_incapacidad,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_terminacion' => $fechaTerminacion,
            'fecha_recibido' => $request->fecha_recibido ?? now()->toDateString(),
            'prorroga' => true,
            'tipo_entidad' => $request->tipo_entidad,
            'entidad_responsable_id' => $request->entidad_responsable_id ?: $padre->entidad_responsable_id,
            'entidad_nombre' => $entidadNombre ?: $padre->entidad_nombre,
            'razon_social_id' => $padre->razon_social_id,
            'razon_social_nombre' => $padre->razon_social_nombre,
            'salario_base' => $salarioBase,
            'diagnostico' => $padre->diagnostico,
            'observacion' => $request->observacion,
            'estado' => 'recibido',
            'estado_pago' => 'pendiente',
            'created_by' => Auth::id(),
        ]);

        $prorroga->calcularValorEsperado(persistir: true);

        Bitacora::registrar(
            accion: 'created',
            modelo: 'Incapacidad',
            registroId: $prorroga->id,
            descripcion: "Prórroga {$numProrroga} creada sobre la incapacidad #{$padreId} (Cédula: {$prorroga->cedula_usuario}): "
                ."{$prorroga->dias_incapacidad} días desde ".$prorroga->fecha_inicio?->format('Y-m-d').'.',
            detalle: [
                'incapacidad_padre_id' => $padreId,
                'numero_proroga' => $numProrroga,
                'cedula_usuario' => $prorroga->cedula_usuario,
                'dias' => $prorroga->dias_incapacidad,
                'fecha_inicio' => $prorroga->fecha_inicio?->format('Y-m-d'),
                'fecha_terminacion' => $prorroga->fecha_terminacion?->format('Y-m-d'),
                'tipo_entidad' => $prorroga->tipo_entidad,
                'valor_esperado' => $prorroga->valor_esperado,
            ],
            alidoId: $alidoId
        );

        return response()->json([
            'ok' => true,
            'message' => "Prórroga {$numProrroga} creada.",
            'prorroga' => $prorroga->only(['id', 'numero_proroga', 'dias_incapacidad', 'fecha_inicio', 'fecha_terminacion', 'estado', 'valor_esperado']),
        ]);
    }

    // ── SUBIR DOCUMENTO (reutiliza tabla radicados) ──────────────────────────
    public function storeDocumento(Request $request, int $id)
    {
        $request->validate([
            // mimes obligatorio: sin él se podía subir .html/.svg/.php a un
            // directorio servido por el servidor web (XSS almacenado / RCE).
            'archivo' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp', // 10MB
            'tipo_documento' => 'required|string',
        ], [
            'archivo.max' => 'El archivo no puede superar 10 MB. Reduce la calidad del escaneo o súbelo por páginas.',
            'archivo.mimes' => 'Solo se aceptan archivos PDF, JPG o PNG.',
        ]);

        $inc = $this->incapacidadDelAliado($id);
        $file = $request->file('archivo');
        $cedula = $inc->cedula_usuario;

        // Se comprime antes de guardar: los soportes llegan como fotos de celular
        // o escaneos de CamScanner de varios MB por página. Si el servidor no
        // puede comprimir, se guarda tal cual. Ver CompresorDocumentoService.
        $ruta = app(CompresorDocumentoService::class)->guardar(
            $file,
            "incapacidades/{$inc->aliado_id}/{$cedula}/{$id}",
            self::DISCO_DOCUMENTOS
        );

        // Guardar en tabla radicados reutilizando incapacidad_id
        Radicado::create([
            'incapacidad_id' => $inc->id,
            'aliado_id' => $inc->aliado_id,
            'contrato_id' => $inc->contrato_id ?? 0,
            'tipo' => 'incapacidad',
            'tipo_documento' => $request->tipo_documento,
            'estado' => 'ok',
            'observacion' => $request->observacion,
            'ruta_pdf' => $ruta,
            'user_id' => Auth::id(),
        ]);

        // Si es soporte de pago firmado → actualizarlo en la incapacidad
        if ($request->tipo_documento === 'soporte_pago') {
            $inc->update(['ruta_soporte_pago' => $ruta]);
        }

        return response()->json(['ok' => true, 'message' => 'Documento subido.', 'ruta' => $ruta]);
    }

    // ── DESCARGAR / VER DOCUMENTO ────────────────────────────────────────────

    /**
     * Radicado de incapacidad del aliado en sesión, o 404.
     * Antes no filtraba por aliado: cualquier usuario autenticado podía
     * descargar el documento de una incapacidad ajena por su docId.
     */
    private function documentoDelAliado(int $docId): Radicado
    {
        return Radicado::where('tipo', 'incapacidad')
            ->where('aliado_id', session('aliado_id_activo') ?? Auth::user()->aliado_id)
            ->findOrFail($docId);
    }

    /**
     * Resuelve en qué disco vive el archivo.
     *
     * Los documentos nuevos van al disco privado; los subidos antes de esta
     * corrección siguen en el disco público del servidor. Se consultan ambos
     * para no romper el historial mientras se migran (ver el comando
     * incapacidades:migrar-documentos).
     */
    private function discoDelDocumento(string $ruta): ?string
    {
        foreach ([self::DISCO_DOCUMENTOS, 'public'] as $disco) {
            if (Storage::disk($disco)->exists($ruta)) {
                return $disco;
            }
        }

        return null;
    }

    public function descargarDocumento(int $docId)
    {
        $doc = $this->documentoDelAliado($docId);
        $disco = $this->discoDelDocumento($doc->ruta_pdf);

        abort_if($disco === null, 404, 'El archivo no existe.');

        $ext = strtolower(pathinfo($doc->ruta_pdf, PATHINFO_EXTENSION)) ?: 'pdf';

        return Storage::disk($disco)->download($doc->ruta_pdf, "{$doc->tipo_documento}.{$ext}");
    }

    /**
     * Muestra el documento en el navegador (visor PDF / imagen) sin descargarlo.
     * Sustituye a los enlaces directos a /storage/..., que servían historias
     * clínicas y cédulas sin pasar por autenticación.
     */
    public function verDocumento(int $docId)
    {
        $doc = $this->documentoDelAliado($docId);
        $disco = $this->discoDelDocumento($doc->ruta_pdf);

        abort_if($disco === null, 404, 'El archivo no existe.');

        return Storage::disk($disco)->response($doc->ruta_pdf);
    }

    // ── DOCUMENTOS DE TODA LA FAMILIA (padre + prórrogas) ───────────────────
    public function documentosFamilia(int $id)
    {
        $inc = $this->incapacidadDelAliado($id);

        // Obtener el id padre real (si es una prórroga, subir al padre)
        $padreId = $inc->incapacidad_padre_id ?? $inc->id;

        // Obtener todos los miembros de la familia
        $familia = Incapacidad::where(function ($q) use ($padreId) {
            $q->where('id', $padreId)->orWhere('incapacidad_padre_id', $padreId);
        })->whereNull('deleted_at')
            ->orderBy('numero_proroga')
            ->get(['id', 'numero_proroga', 'incapacidad_padre_id', 'fecha_inicio', 'fecha_terminacion',
                'dias_incapacidad', 'numero_radicado', 'fecha_radicado', 'estado', 'estado_pago', 'valor_esperado']);

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
                'incapacidad_id' => $miembro->id,
                'es_padre' => is_null($miembro->incapacidad_padre_id),
                'numero_proroga' => $miembro->numero_proroga,
                'label' => is_null($miembro->incapacidad_padre_id) ? 'Original' : "Prórroga {$miembro->numero_proroga}",
                'fecha_inicio' => $miembro->fecha_inicio,
                'fecha_terminacion' => $miembro->fecha_terminacion,
                'dias_incapacidad' => $miembro->dias_incapacidad,
                'numero_radicado' => $miembro->numero_radicado,
                'fecha_radicado' => $miembro->fecha_radicado,
                'estado' => $miembro->estado,
                'estado_pago' => $miembro->estado_pago,
                'valor_esperado' => $miembro->valor_esperado,
                'documentos' => $docs->map(function ($d) {
                    $ext = strtolower(pathinfo($d->ruta_pdf, PATHINFO_EXTENSION));

                    return [
                        'id' => $d->id,
                        'tipo_documento' => $d->tipo_documento,
                        'observacion' => $d->observacion,
                        'ruta_pdf' => $d->ruta_pdf,
                        'url_ver' => route('admin.incapacidades.documento.ver', $d->id),
                        'url_descargar' => route('admin.incapacidades.documento.download', $d->id),
                        'es_pdf' => $ext === 'pdf',
                        'extension' => $ext,
                        'subido_por' => $d->subido_por ?? 'Cliente',
                        'fecha' => $d->created_at,
                    ];
                })->values(),
                'total_docs' => $docs->count(),
            ];
        });

        // ── Documentos globales del cliente (cédula, carta laboral, etc.) ────
        $alidoId = $inc->aliado_id;
        $cedula = $inc->cedula_usuario;
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
                        'cedula' => '🪪 Cédula',
                        'carta_laboral' => '📋 Carta Laboral',
                        'registro_civil' => '📜 Registro Civil',
                        'tarjeta_identidad' => '🪪 Tarjeta Identidad',
                        'decl_juramentada' => '⚖️ Decl. Juramentada',
                        'acta_matrimonio' => '💍 Acta Matrimonio',
                        'otro' => '📎 Otro',
                    ];

                    return [
                        'id' => $d->id,
                        'tipo_documento' => $d->tipo_documento,
                        'tipo_label' => $tiposLabel[$d->tipo_documento] ?? ucfirst($d->tipo_documento),
                        'nombre' => $d->nombre_archivo,
                        'ruta' => $d->ruta,
                        'url_ver' => $d->ruta ? route('admin.documentos.download', $d->id) : null,
                        'url_descargar' => $d->ruta ? route('admin.documentos.download', $d->id) : null,
                        'es_pdf' => $ext === 'pdf',
                        'extension' => $ext,
                        'subido_por' => $d->subido_por ?? 'Sistema',
                        'fecha' => $d->created_at,
                    ];
                });
        } catch (\Exception $e) {
            // Si la tabla no existe o hay error, continuar sin docs globales
            $docsGlobales = collect();
        }

        return response()->json([
            'ok' => true,
            'familia' => $resultado,
            'total_documentos' => $documentos->count(),
            'docs_globales' => $docsGlobales->values(),
        ]);
    }

    // ── REGISTRAR PAGO AL AFILIADO (ANTICIPO / PRÉSTAMO) ─────────────────────
    public function registrarPago(Request $request, int $id)
    {
        $request->validate([
            'valor_pago' => 'required|numeric|min:0',
            'fecha_pago' => 'required|date',
            'pagado_a' => 'required|in:cliente,empresa',
            'forma_pago' => 'nullable|in:efectivo,transferencia_bancaria',
            'banco_cuenta_id' => 'nullable|integer',
            'detalle_pago' => 'nullable|string',
        ]);

        $inc = $this->incapacidadDelAliado($id);

        $aliadoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;
        $usuarioId = Auth::id();

        // 1. Calcular saldos antes de aplicar este abono
        $saldoPendiente = $inc->saldo_pendiente;
        $nuevoSaldo = max(0, $saldoPendiente - $request->valor_pago);
        $cubreTotal = $nuevoSaldo <= 0;

        $formaPago = $request->forma_pago ?: 'transferencia_bancaria';
        $bancoId = $request->banco_cuenta_id ?: null;
        $obsAbono = $request->detalle_pago ?: "Anticipo / Préstamo al afiliado — Incapacidad #{$inc->id}";

        DB::beginTransaction();
        try {
            // 2. Registrar el Abono (tipo pago_cliente)
            DB::table('abonos_incapacidades')->insert([
                'aliado_id' => $aliadoId,
                'incapacidad_id' => $inc->id,
                'razon_social_id' => $inc->razon_social_id ?? null,
                'tipo' => 'pago_cliente',
                'valor' => $request->valor_pago,
                'fecha' => $request->fecha_pago,
                'banco_cuenta_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                'usuario_id' => $usuarioId,
                'observacion' => $obsAbono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Obtener el nombre del cliente/afiliado para el gasto
            $clienteObj = DB::table('clientes')->where('cedula', $inc->cedula_usuario)->first();
            $nombreCliente = $clienteObj
                ? trim("{$clienteObj->primer_nombre} ".($clienteObj->segundo_nombre ? "{$clienteObj->segundo_nombre} " : '')."{$clienteObj->primer_apellido}")
                : $inc->cedula_usuario;

            // 4. Obtener cuadre abierto
            $cuadre = \App\Models\Cuadre::where('aliado_id', $aliadoId)
                ->where('usuario_id', $usuarioId)
                ->where('estado', 'abierto')
                ->latest('fecha_inicio')
                ->first();
            $cuadreId = $cuadre ? $cuadre->id : null;

            // 5. Registrar el Gasto (tipo pago_incapacidad)
            DB::table('gastos')->insert([
                'aliado_id' => $aliadoId,
                'usuario_id' => $usuarioId,
                'cuadre_id' => $cuadreId,
                'fecha' => $request->fecha_pago,
                'tipo' => 'pago_incapacidad',
                'descripcion' => "Anticipo/Préstamo incapacidad #{$inc->id} al afiliado (Neto: \${$request->valor_pago})",
                'pagado_a' => $nombreCliente,
                'cc_pagado_a' => $inc->cedula_usuario,
                'forma_pago' => $formaPago,
                'banco_origen_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                'valor' => $request->valor_pago,
                'recibo_caja' => null,
                'lugar' => null,
                'observacion' => $obsAbono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Actualizar incapacidad
            $updateData = [
                'valor_pago' => (float) $inc->total_pago_cliente + $request->valor_pago,
                'fecha_pago' => $request->fecha_pago,
                'pagado_a' => $request->pagado_a,
                'detalle_pago' => $obsAbono,
            ];

            if ($cubreTotal) {
                $updateData['estado_pago'] = 'pagado_afiliado';
                $updateData['estado'] = 'pagada_afiliado';
            }

            $inc->update($updateData);

            // 7. Registrar Gestión automática
            GestionIncapacidad::create([
                'incapacidad_id' => $inc->id,
                'user_id' => $usuarioId,
                'aplica_a_familia' => false,
                'tipo' => 'pago_afiliado',
                'tramite' => '💰 Anticipo/Préstamo registrado al '.($request->pagado_a === 'cliente' ? 'cliente afiliado' : 'empresa'),
                'respuesta' => 'Valor: $'.number_format($request->valor_pago, 0, ',', '.'),
                'estado_resultado' => $cubreTotal ? 'pagada_afiliado' : $inc->estado,
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['ok' => false, 'message' => 'Error al registrar el anticipo: '.$e->getMessage()], 500);
        }

        Bitacora::registrar(
            accion: 'created',
            modelo: 'Incapacidad',
            registroId: $inc->id,
            descripcion: 'Anticipo/Préstamo de $'.number_format((float) $request->valor_pago, 0, ',', '.')
                ." pagado a {$request->pagado_a} por incapacidad #{$inc->id} (Cédula: {$inc->cedula_usuario})."
                .($cubreTotal ? ' Cubre el saldo total.' : ' Saldo restante: $'.number_format($nuevoSaldo, 0, ',', '.').'.'),
            detalle: [
                'valor_pago' => (float) $request->valor_pago,
                'fecha_pago' => $request->fecha_pago,
                'pagado_a' => $request->pagado_a,
                'forma_pago' => $formaPago,
                'banco_cuenta_id' => $formaPago === 'transferencia_bancaria' ? $bancoId : null,
                'cuadre_id' => $cuadreId,
                'saldo_anterior' => $saldoPendiente,
                'saldo_resultante' => $nuevoSaldo,
                'cubre_total' => $cubreTotal,
                'detalle_pago' => $obsAbono,
            ],
            alidoId: $aliadoId
        );

        return response()->json(['ok' => true, 'message' => 'Anticipo/Préstamo al afiliado registrado correctamente.']);
    }

    // ── CALCULAR VALOR ESPERADO (API) ────────────────────────────────────────
    public function calcularValor(int $id)
    {
        $inc = $this->incapacidadDelAliado($id);
        $valor = $inc->calcularValorEsperado(persistir: true);

        // Opcional: asegurarnos explícitamente (redundante pero seguro)
        $inc->update(['valor_esperado' => $valor]);

        return response()->json([
            'ok' => true,
            'valor_esperado' => $valor,
            'valor_formato' => '$'.number_format($valor, 0, ',', '.'),
            'alerta_180' => $inc->alertaDias180(),
            'total_dias' => $inc->totalDiasFamilia(),
        ]);
    }

    // ── DESTROY (soft delete) ────────────────────────────────────────────────
    public function destroy(int $id)
    {
        $inc = $this->incapacidadDelAliado($id);

        // Antes del delete: después el registro queda con deleted_at y ya no
        // se puede reconstruir qué se borró desde el listado normal.
        Bitacora::registrar(
            accion: 'deleted',
            modelo: 'Incapacidad',
            registroId: $inc->id,
            descripcion: "Incapacidad #{$inc->id} eliminada (Cédula: {$inc->cedula_usuario}): {$inc->dias_incapacidad} días desde "
                .$inc->fecha_inicio?->format('Y-m-d').' ante '.strtoupper((string) $inc->tipo_entidad).'.',
            detalle: [
                'cedula_usuario' => $inc->cedula_usuario,
                'contrato_id' => $inc->contrato_id,
                'dias' => $inc->dias_incapacidad,
                'fecha_inicio' => $inc->fecha_inicio?->format('Y-m-d'),
                'fecha_terminacion' => $inc->fecha_terminacion?->format('Y-m-d'),
                'tipo_entidad' => $inc->tipo_entidad,
                'entidad' => $inc->entidad_nombre,
                'estado' => $inc->estado,
                'estado_pago' => $inc->estado_pago,
                'valor_esperado' => $inc->valor_esperado,
                'numero_proroga' => $inc->numero_proroga,
            ],
            alidoId: $inc->aliado_id
        );

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
            ->leftJoin('empresas as e', 'e.id', '=', 'c.cod_empresa')
            ->where('c.aliado_id', $alidoId)
            ->where('c.cedula', 'like', '%'.$cedula.'%')
            ->select('c.cedula', 'c.primer_nombre', 'c.segundo_nombre',
                'c.primer_apellido', 'c.segundo_apellido',
                'c.celular', 'c.cod_empresa', 'c.eps_id', 'c.pension_id',
                'e.empresa as empresa_nombre')
            ->distinct()
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }

    // ── API: contratos por cédula ────────────────────────────────────────────
    public function apiContratos(Request $request)
    {
        $cedula = $request->get('cedula');
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $contratos = DB::table('contratos as c')
            ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.cedula', $cedula)
            ->where('c.aliado_id', $alidoId)
            ->orderByRaw("CASE WHEN c.estado='vigente' THEN 0 ELSE 1 END")
            ->orderByDesc('c.fecha_ingreso')
            ->get(['c.id', 'c.cedula', 'c.fecha_ingreso', 'c.estado',
                'c.razon_social_id', 'rs.razon_social as razon_social_nombre',
                'rs.nit as razon_social_nit',
                'c.eps_id', 'c.arl_id', 'c.pension_id', 'c.salario']);

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
        if (! $entidadId) {
            return null;
        }

        return match ($tipo) {
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
        if ($overrideRemite) {
            return $overrideRemite;
        }

        $cliente = DB::table('clientes')->where('cedula', $cedula)->first();
        if ($cliente && $cliente->cod_empresa) {
            $empresa = DB::table('empresas')->where('id', $cliente->cod_empresa)->value('empresa');
            if ($empresa) {
                return $empresa;
            }
        }

        return $cedula; // independiente
    }
}
