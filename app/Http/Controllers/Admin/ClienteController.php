<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    // ─── Listado de clientes ──────────────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $buscar   = $request->get('buscar');
        $filtroEmpresa = $request->get('empresa');

        $query = Cliente::with(['empresa'])
            ->where('clientes.aliado_id', $aliadoId)
            ->select('id', 'cedula', 'cod_empresa', 'primer_nombre', 'segundo_nombre',
                     'primer_apellido', 'segundo_apellido',
                     'celular', 'telefono', 'correo', 'municipio_id', 'eps_id', 'pension_id');

        // Búsqueda inteligente: toda la lógica de búsqueda se envuelve en UN SOLO where()
        // para que el orWhere de nombre NO escape el filtro aliado_id de la query raíz.
        // SQL resultante: WHERE aliado_id = X AND (cedula LIKE... OR (nombre1... AND nombre2...))
        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                // Coincidencia directa en cédula o celular
                $q->where(function ($inner) use ($buscar) {
                    $inner->where('cedula',  'LIKE', "%{$buscar}%")
                          ->orWhere('celular', 'LIKE', "%{$buscar}%");
                });

                // Si no es puramente numérico, también buscar por nombre tokenizado
                if (!ctype_digit(str_replace(' ', '', $buscar))) {
                    $palabras = array_filter(explode(' ', trim($buscar)));
                    $q->orWhere(function ($inner) use ($palabras) {
                        foreach ($palabras as $palabra) {
                            // Cada palabra debe matchear en ALGUNO de los 4 campos de nombre
                            $inner->where(function ($sub) use ($palabra) {
                                $sub->where('primer_nombre',    'LIKE', "%{$palabra}%")
                                    ->orWhere('segundo_nombre',  'LIKE', "%{$palabra}%")
                                    ->orWhere('primer_apellido', 'LIKE', "%{$palabra}%")
                                    ->orWhere('segundo_apellido','LIKE', "%{$palabra}%");
                            });
                        }
                    });
                }
            });
        }

        // Filtro por empresa
        if ($filtroEmpresa) {
            $query->where('cod_empresa', $filtroEmpresa);
        }

        $clientes = $query->orderByDesc('id')->paginate(30);

        // Cargar último contrato de cada cliente (por cédula) en una sola consulta
        $cedulas = $clientes->pluck('cedula')->filter()->values()->toArray();
        $ultimosContratos = [];
        if (!empty($cedulas)) {
            // Subquery con prioridad: primero contrato vigente/activo, luego el de mayor ID.
            // Evita mostrar "retirado" cuando el cliente tiene un contrato más antiguo pero vigente.
            // Lógica:
            //   - CASE: vigente=0, activo=1, cualquier otro=2 → ORDER BY prioridad ASC, id DESC
            //   - CROSS APPLY TOP 1 selecciona el contrato ganador por cédula.
            $subs = DB::table('contratos as c')
                ->join(
                    DB::raw("(
                        SELECT TOP 1 WITH TIES c2.cedula, c2.id AS pref_id
                        FROM contratos c2
                        WHERE c2.aliado_id = {$aliadoId}
                        ORDER BY ROW_NUMBER() OVER (
                            PARTITION BY c2.cedula
                            ORDER BY
                                CASE
                                    WHEN c2.estado IN ('vigente','activo') THEN 0
                                    ELSE 1
                                END ASC,
                                c2.id DESC
                        )
                    ) AS pref"),
                    fn($j) => $j->on('c.cedula', '=', 'pref.cedula')->on('c.id', '=', 'pref.pref_id')
                )
                ->leftJoin('tipo_modalidad as tm', 'tm.id', '=', 'c.tipo_modalidad_id')
                ->select('c.cedula', 'c.estado', 'c.fecha_ingreso', 'c.fecha_retiro',
                         DB::raw("COALESCE(tm.observacion, tm.tipo_modalidad) AS modalidad"))
                ->whereIn('c.cedula', $cedulas)
                ->get()
                ->keyBy('cedula');
            $ultimosContratos = $subs->toArray();
        }

        // Lista de empresas para filtro (del aliado activo)
        $empresas = \App\Models\Empresa::where('aliado_id', $aliadoId)
            ->orderBy('empresa')
            ->get(['id', 'empresa']);

        // El modal "Nuevo Cliente" deja escoger el tipo de documento antes de
        // consultar: BDUA/RUAF responde por tipo + número, y un tipo distinto
        // del real devuelve vacío (no error), que parece "no registrado".
        $tiposDoc = $this->getLookups()['tipos_doc'];

        return view('admin.clientes.index', compact('clientes', 'buscar', 'filtroEmpresa', 'empresas', 'ultimosContratos', 'tiposDoc'));
    }

    // ─── Crear nuevo cliente ──────────────────────────────────────────
    public function create()
    {
        $cliente = new Cliente();
        $lookups = $this->getLookups();
        $contratos = collect();

        // Pre-llenado desde el modal "Nuevo Cliente" del listado: ya consultó
        // BDUA/RUAF y el usuario confirmó que la persona es la correcta.
        // Deliberadamente no se toca "observacion" aquí.
        $campos = ['cedula', 'tipo_doc', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'eps_id', 'pension_id'];
        foreach (request()->only($campos) as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $cliente->{$campo} = $valor;
            }
        }

        return view('admin.clientes.form', compact('cliente', 'lookups', 'contratos'));
    }

    // ─── Guardar nuevo ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // Verificar duplicado: cédula ya registrada en este aliado
        $cedula = $request->input('cedula');
        $clienteExistente = Cliente::where('cedula', $cedula)
            ->where('aliado_id', $aliadoId)
            ->first();

        if ($clienteExistente) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'cedula' => "Ya existe un cliente con la cédula {$cedula} en este aliado. "
                              . "Puedes editarlo desde su perfil (ID #{$clienteExistente->id}).",
                ]);
        }

        $data = $this->validarCliente($request);
        $data = $this->limpiarDatos($data);

        // Obtener siguiente ID (la tabla no es autoincrement)
        $maxId = DB::table('clientes')->max('id') ?? 0;
        $data['id'] = $maxId + 1;
        $data['aliado_id'] = $aliadoId;

        Cliente::create($data);

        return redirect()->route('admin.clientes.edit', $data['id'])
            ->with('success', 'Cliente creado exitosamente.');
    }

    // ─── Editar cliente existente ─────────────────────────────────────
    public function edit(int $id, Request $request)
    {
        $isIframe = $request->boolean('iframe');
        $aliadoId = session('aliado_id_activo');
        $cliente = Cliente::where('aliado_id', $aliadoId)->findOrFail($id);
        $lookups = $this->getLookups();
        $contratos = DB::table('contratos as ct')
            ->leftJoin('tipo_modalidad as tm', 'tm.id', '=', 'ct.tipo_modalidad_id')
            ->leftJoin('planes_contrato as pc', 'pc.id', '=', 'ct.plan_id')
            ->where('ct.cedula', $cliente->cedula)
            ->where('ct.aliado_id', session('aliado_id_activo'))
            ->orderByRaw("CASE WHEN ct.estado = 'vigente' THEN 0 ELSE 1 END")
            ->orderByDesc('ct.id')
            ->select(
                'ct.*',
                DB::raw("COALESCE(tm.tipo_modalidad, '') AS tipo_mod"),
                DB::raw("COALESCE(pc.nombre, '') AS plan_nombre")
            )
            ->get();

        // Precargar razones_sociales para evitar N+1 en la vista
        $razonSocialIds = $contratos->pluck('razon_social_id')->filter()->unique()->values()->toArray();
        $razonesMap = [];
        if (!empty($razonSocialIds)) {
            $razonesMap = DB::table('razones_sociales')
                ->whereIn('id', $razonSocialIds)
                ->pluck('razon_social', 'id')
                ->toArray();
        }

        // ── Últimos 2 pagos (histórico corto bajo la tabla de contratos) ──
        // Una sola query, TOP 2: factura de planilla + su plano (N° planilla)
        // + el operador con el que se liquidó por API (si aplica).
        $ultimosPagos = DB::table('facturas as f')
            ->leftJoin('planos as p', function ($j) {
                $j->on('p.factura_id', '=', 'f.id')->whereNull('p.deleted_at');
            })
            ->leftJoin(DB::raw('(SELECT plano_id, MAX(id) AS max_id
                                   FROM operador_planillas_api
                                  WHERE deleted_at IS NULL AND plano_id IS NOT NULL
                               GROUP BY plano_id) AS opa_max'), 'opa_max.plano_id', '=', 'p.id')
            ->leftJoin('operador_planillas_api as opa', 'opa.id', '=', 'opa_max.max_id')
            ->leftJoin('operadores_planilla as op', 'op.id', '=', 'opa.operador_planilla_id')
            ->where('f.aliado_id', $aliadoId)
            ->where('f.cedula', $cliente->cedula)
            ->where('f.tipo', \App\Models\Factura::TIPO_PLANILLA)
            ->whereNull('f.deleted_at')
            ->orderByDesc('f.anio')->orderByDesc('f.mes')->orderByDesc('f.id')
            ->limit(2)
            ->select(
                'f.id', 'f.mes', 'f.anio', 'f.total', 'f.estado', 'f.numero_factura',
                DB::raw('COALESCE(p.numero_planilla, opa.numero_planilla) AS numero_planilla'),
                'op.nombre AS operador_nombre'
            )
            ->get();

        // Resumen del cliente para el card lateral
        $resumen = [
            'beneficiarios'   => DB::table('beneficiarios')->where('cc_cliente', $cliente->cedula)->count(),
            'incapacidades'    => DB::table('incapacidades')->where('cedula_usuario', $cliente->cedula)->count(),
            'contratos_vigent' => $contratos->where('estado', 'vigente')->count(),
            'claves'           => DB::table('clave_accesos')
                                    ->where('aliado_id', session('aliado_id_activo'))
                                    ->where('cedula', $cliente->cedula)
                                    ->where('activo', 1)
                                    ->count(),
        ];

        // ¿Tiene al menos un contrato con RS independiente?
        // Determina si se muestra el selector de operador en el formulario.
        $tieneContratoIndependiente = false;
        if ($contratos->count() > 0) {
            $rsIds = $contratos->pluck('razon_social_id')->filter()->unique()->values()->toArray();
            if (!empty($rsIds)) {
                $tieneContratoIndependiente = DB::table('razones_sociales')
                    ->whereIn('id', $rsIds)
                    ->where('es_independiente', true)
                    ->exists();
            }
        }

        // Todos los operadores de planilla (sin filtro de activos: el usuario puede asignar cualquiera)
        $operadoresPlanilla = DB::table('operadores_planilla')
            ->whereNull('aliado_id')   // solo globales (no del aliado específico)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo_ni']);

        $bancos = \App\Models\BancoCuenta::activas(session('aliado_id_activo'));

        return view('admin.clientes.form', compact(
            'cliente', 'lookups', 'contratos', 'razonesMap', 'resumen', 'bancos',
            'tieneContratoIndependiente', 'operadoresPlanilla', 'isIframe', 'ultimosPagos'
        ));
    }

    // ─── Actualizar ───────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $cliente = Cliente::where('aliado_id', $aliadoId)->findOrFail($id);
        $data = $this->validarCliente($request, $id);
        $data = $this->limpiarDatos($data);

        $cliente->update($data);

        $redirectUrl = route('admin.clientes.edit', $id);
        if ($request->input('iframe')) {
            $redirectUrl .= '?iframe=1';
        }

        return redirect($redirectUrl)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    // ─── Buscar cliente por cédula (AJAX) ─────────────────────────────
    public function buscarPorCedula(Request $request)
    {
        $cedula    = $request->get('cedula');
        $aliadoId  = session('aliado_id_activo');
        if (!$cedula) return response()->json(null);

        // El tipo llega del selector del modal. Se valida contra el catálogo
        // porque va directo en la URL del operador; cualquier cosa rara cae
        // a CC, que es el 94% de los clientes.
        $tipoDoc = strtoupper((string) $request->get('tipo_doc', 'CC'));
        if (!array_key_exists($tipoDoc, $this->getLookups()['tipos_doc'])) {
            $tipoDoc = 'CC';
        }

        // El duplicado se busca solo por número, a propósito: el mismo número
        // con otro tipo puede ser la misma persona que cambió de documento
        // (TI → CC al cumplir 18, PE → CC al cedularse), y crear un segundo
        // registro sería peor que avisar de más. Por eso se devuelve el
        // tipo_doc del que ya existe: si no coincide, el asesor lo ve.
        $cliente = Cliente::where('cedula', $cedula)
            ->where('aliado_id', $aliadoId)
            ->first();

        if ($cliente) {
            return response()->json([
                'encontrado'     => true,
                'id'             => $cliente->id,
                'nombre'         => $cliente->nombre_completo,
                'tipo_doc'       => $cliente->tipo_doc ?: 'CC',
                'url_editar'     => route('admin.clientes.edit', $cliente->id),
                'eps'            => $cliente->eps_nombre ?? null,
                'celular'        => $cliente->celular ?? null,
                'oficial'        => $this->consultarRegistroOficial($aliadoId, $cedula, $tipoDoc),
            ]);
        }

        return response()->json([
            'encontrado' => false,
            'oficial'    => $this->consultarRegistroOficial($aliadoId, $cedula, $tipoDoc),
        ]);
    }

    /**
     * Busca un operador (ARUS/Simple) con credenciales para consultar BDUA/RUAF.
     *
     * Consultar la afiliación de una cédula es una operación de solo lectura
     * sin autorización por aportante: cualquier cuenta de operador sirve para
     * cualquier persona. Por eso, si el aliado activo no tiene credenciales
     * propias, se cae a las del aliado BryNex — así la verificación funciona
     * en todos los aliados aunque no hayan configurado su propia cuenta.
     *
     * Esto NO aplica a liquidar planillas (PlanillaApiController), que sí
     * exige la autorización real del aliado sobre ese aportante específico.
     *
     * @return array{0: ?\App\Models\OperadorPlanilla, 1: ?\App\Models\OperadorCredencial}
     */
    private function credencialParaRuaf(int $aliadoId): array
    {
        $buscar = function (int $idParaOperadores, int $idParaCredencial) {
            $operador = \App\Models\OperadorPlanilla::paraAliado($idParaOperadores)
                ->whereIn('codigo', array_keys(\App\Services\SuaporteApiService::HOSTS))
                ->get()
                ->first(fn ($op) => \App\Models\OperadorCredencial::paraOperador($idParaCredencial, $op->id)->exists());

            if (!$operador) {
                return [null, null];
            }

            return [$operador, \App\Models\OperadorCredencial::paraOperador($idParaCredencial, $operador->id)->first()];
        };

        [$operador, $credencial] = $buscar($aliadoId, $aliadoId);
        if ($operador && $credencial) {
            return [$operador, $credencial];
        }

        $fallbackId = (int) config('services.suaporte.aliado_fallback_ruaf');
        if ($fallbackId && $fallbackId !== $aliadoId) {
            // Los operadores se buscan con el aliado real (respeta si él los
            // desactivó); solo la credencial cae al aliado de respaldo.
            return $buscar($aliadoId, $fallbackId);
        }

        return [null, null];
    }

    /**
     * Consulta BDUA/RUAF a través de la API del operador de planilla para
     * traer los datos oficiales de la persona: nombres, EPS, fondo de
     * pensión, régimen y estado.
     *
     * Devuelve null si el aliado no tiene credenciales de operador cargadas,
     * de modo que el formulario siga funcionando igual que siempre.
     *
     * El registro responde por tipo + número, y son espacios independientes:
     * el mismo número existe como CC de una persona y como CE de otra. Con el
     * tipo equivocado la respuesta llega vacía, sin error, e indistinguible de
     * "esta persona no está registrada" — por eso el tipo lo escoge el asesor
     * y no se asume.
     */
    private function consultarRegistroOficial(int $aliadoId, string $cedula, string $tipoDoc = 'CC'): ?array
    {
        $cedula = preg_replace('/\D/', '', $cedula);

        if (strlen($cedula) < 4) {
            return null;
        }

        // El asesor puede entrar y salir del campo varias veces: se cachea
        // para no repetir el login contra el operador en cada blur.
        //
        // La caché es una optimización, NO un requisito: se lee y se escribe
        // dentro de try/catch para que un fallo del driver no tumbe la
        // consulta. Con CACHE_DRIVER=file basta un directorio de
        // storage/framework/cache que Apache no pueda escribir (lo deja
        // cualquier artisan corrido como root) para que Cache::remember
        // lance ErrorException, y el modal muestre "No se pudo consultar"
        // aunque el operador haya respondido bien.
        //
        // Por eso NO se usa Cache::remember: si la escritura falla después de
        // consultar, se devuelve igual el resultado en vez de perderlo.
        //
        // El tipo entra en la llave: CC 104915 y CE 104915 son dos personas
        // distintas y no pueden compartir entrada de caché.
        $llave = "ruaf_{$aliadoId}_{$tipoDoc}_{$cedula}";

        try {
            $cacheado = Cache::get($llave);
        } catch (\Throwable $e) {
            Log::warning('RUAF: no se pudo leer la caché', ['llave' => $llave, 'message' => $e->getMessage()]);
            $cacheado = null;
        }

        if ($cacheado !== null) {
            return $cacheado;
        }

        $resultado = $this->consultarRegistroOficialEnOperador($aliadoId, $cedula, $tipoDoc);

        // Un null (sin credenciales, o el operador falló) no se cachea a
        // propósito: el siguiente intento vuelve a preguntar.
        if ($resultado !== null) {
            try {
                Cache::put($llave, $resultado, 600);
            } catch (\Throwable $e) {
                Log::warning('RUAF: no se pudo guardar en caché', ['llave' => $llave, 'message' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    /**
     * La consulta real al operador, sin caché. Separada de
     * consultarRegistroOficial() solo para que el manejo de la caché quede
     * legible; no llamar directamente.
     */
    private function consultarRegistroOficialEnOperador(int $aliadoId, string $cedula, string $tipoDoc = 'CC'): ?array
    {
        [$operador, $credencial] = $this->credencialParaRuaf($aliadoId);

        if (!$operador || !$credencial) {
            return null;
        }

        $api = new \App\Services\SuaporteApiService([
            'operador'      => $operador->codigo,
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        $resultado = $api->consultarAfiliacion($tipoDoc, $cedula);

        if (!$resultado['success']) {
            Log::warning('RUAF: el operador no respondió la consulta', [
                'aliado_id' => $aliadoId,
                'operador'  => $operador->codigo,
                'tipo_doc'  => $tipoDoc,
                'message'   => $resultado['message'] ?? null,
            ]);

            return null;
        }

        $d = $resultado['afiliacion'];

        // Los códigos que devuelve el registro son los mismos que usan
        // las tablas de referencia de Brynex.
        $epsId = !empty($d['administradoraBDUA'])
            ? DB::table('eps')->where('codigo', $d['administradoraBDUA'])->value('id')
            : null;

        $pensionId = !empty($d['administradoraRUAF'])
            ? DB::table('pensiones')->where('codigo', $d['administradoraRUAF'])->value('id')
            : null;

        return [
            'encontrado'       => $resultado['registrado'],
            'operador'         => $operador->nombre,
            'tipo_doc'         => $tipoDoc,
            'primer_nombre'    => $d['primerNombre']    ?? '',
            'segundo_nombre'   => $d['segundoNombre']   ?? '',
            'primer_apellido'  => $d['primerApellido']  ?? '',
            'segundo_apellido' => $d['segundoApellido'] ?? '',
            'eps_id'           => $epsId,
            'eps_nombre'       => $epsId ? DB::table('eps')->where('id', $epsId)->value('nombre') : null,
            'eps_codigo'       => $d['administradoraBDUA'] ?? null,
            'pension_id'       => $pensionId,
            'pension_nombre'   => $pensionId ? DB::table('pensiones')->where('id', $pensionId)->value('razon_social') : null,
            'pension_codigo'   => $d['administradoraRUAF'] ?? null,
            'estado'           => $d['estado']  ?? null,
            'regimen'          => $d['regimen'] ?? null,
            // Figurar en RUAF (aunque hoy no tenga fondo activo) es lo
            // que impide declarar el subtipo 03 "no obligado por edad".
            'en_ruaf'          => !empty($d['fechaAfiliacionRUAF']),
            'ruaf_desde'       => $d['fechaAfiliacionRUAF'] ?? null,
            // Payload crudo del operador, sin filtrar: incluye campos
            // que hoy no se usan (valorUPC, coincidencia, fechas sin
            // formatear) para que el modal pueda mostrarlos todos.
            'raw'              => $d,
        ];
    }

    // ─── Helpers Privados ─────────────────────────────────────────────

    private function validarCliente(Request $request, ?int $id = null): array
    {
        $aliadoId = session('aliado_id_activo');

        // Regla unique compuesta (cedula + aliado_id):
        // - En store ($id=null): la cédula no debe existir en este aliado.
        // - En update ($id!=null): ignorar el propio registro, pero no permitir
        //   cambiar a una cédula que ya usa OTRO cliente del mismo aliado.
        $reglaCedula = Rule::unique('clientes', 'cedula')
            ->where('aliado_id', $aliadoId)
            ->when($id !== null, fn($rule) => $rule->ignore($id));

        return $request->validate([
            'tipo_doc'            => 'nullable|string|max:10',
            'cod_empresa'         => 'nullable|integer',
            'cedula'              => ['required', 'numeric', $reglaCedula],
            'primer_nombre'       => 'required|string|max:55',
            'segundo_nombre'      => 'nullable|string|max:55',
            'primer_apellido'     => 'required|string|max:55',
            'segundo_apellido'    => 'nullable|string|max:55',
            'genero'              => 'nullable|string|max:10',
            'fecha_nacimiento'    => 'nullable|date',
            'fecha_expedicion'    => 'nullable|date',
            'telefono'            => 'nullable|string|max:20',
            'celular'             => 'nullable|string|max:20',
            'correo'              => 'nullable|string|max:100',
            'rh'                  => 'nullable|string|max:10',
            'departamento_id'     => 'nullable|integer',
            'municipio_id'        => 'nullable|integer',
            'direccion_vivienda'  => 'nullable|string|max:150',
            'direccion_cobro'     => 'nullable|string|max:150',
            'ocupacion'           => 'nullable|string|max:80',
            'referido'            => 'nullable|string|max:80',
            'eps_id'              => 'nullable|integer',
            'pension_id'          => 'nullable|integer',
            'operador_planilla_id' => 'nullable|integer',
            'sisben'              => 'nullable|string|max:50',
            'ips'                 => 'nullable|string|max:100',
            'iva'                 => 'nullable|string|max:20',
            'observacion'         => 'nullable|string',
        ], [
            'cedula.required'          => 'La cédula es obligatoria.',
            'cedula.unique'            => 'Ya existe un cliente con esta cédula registrado en este aliado.',
            'primer_nombre.required'   => 'El primer nombre es obligatorio.',
            'primer_apellido.required' => 'El primer apellido es obligatorio.',
        ]);
    }

    private function limpiarDatos(array $data): array
    {
        // Celular: limpiar caracteres
        if (isset($data['celular']) && $data['celular'] !== null) {
            $data['celular'] = (int) preg_replace('/[^0-9]/', '', $data['celular']) ?: null;
        }
        // EPS y Pensión: NULL si vacío
        foreach (['eps_id', 'pension_id'] as $campo) {
            if (empty($data[$campo])) {
                $data[$campo] = null;
            }
        }
        // Departamento/Municipio: NULL si vacío
        foreach (['departamento_id', 'municipio_id'] as $campo) {
            if (empty($data[$campo])) {
                $data[$campo] = null;
            }
        }
        // cod_empresa: NULL si vacío
        if (empty($data['cod_empresa'])) {
            $data['cod_empresa'] = null;
        }
        return $data;
    }

    private function getLookups(): array
    {
        $departamentos = DB::table('departamentos')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();

        $ciudades = DB::table('ciudades')
            ->orderBy('nombre')
            ->select('id', 'departamento_id', 'nombre')
            ->get();

        return [
            'eps'           => Cliente::listaEps(),
            'pension'       => Cliente::listaPension(),
            'arl'           => DB::table('arls')->orderBy('nombre_arl')->pluck('nombre_arl', 'id')->toArray(),
            'caja'          => DB::table('cajas')->orderBy('nombre')->pluck('nombre', 'id')->toArray(),
            'razon_social'  => Cliente::listaRazonSocial(),
            'asesores'      => Cliente::listaAsesores(),
            'empresas'      => \App\Models\Empresa::where('aliado_id', session('aliado_id_activo'))
                                ->orderBy('empresa')
                                ->get(['id', 'empresa']),
            'departamentos' => $departamentos,
            'ciudades'      => $ciudades,
            'tipos_doc'     => [
                'CC'  => 'CC - Cédula de Ciudadanía',
                'TI'  => 'TI - Tarjeta de Identidad',
                'CE'  => 'CE - Cédula de Extranjería',
                'PA'  => 'PA - Pasaporte',
                'PT'  => 'PT - Permiso de Protección Temporal',
                'PE'  => 'PE - Permiso Especial de Permanencia',
            ],
            'generos'       => ['M' => 'Masculino', 'F' => 'Femenino'],
            'rh'            => ['O+' => 'O+', 'O-' => 'O-', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-'],
            'sisben'        => ['NC' => 'NC - Sin Sisben', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
        ];
    }
}
