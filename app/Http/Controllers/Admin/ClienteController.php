<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Búsqueda inteligente: cada palabra del término debe aparecer en algún campo de nombre
        if ($buscar) {
            // Coincidencia directa en cédula o celular
            $query->where(function ($q) use ($buscar) {
                $q->where('cedula', 'LIKE', "%{$buscar}%")
                  ->orWhere('celular', 'LIKE', "%{$buscar}%");
            });

            // Si no es puramente numérico, también buscar por nombre tokenizado
            if (!ctype_digit(str_replace(' ', '', $buscar))) {
                $palabras = array_filter(explode(' ', trim($buscar)));
                $query->orWhere(function ($q) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        // Cada palabra debe matchear en ALGUNO de los 4 campos de nombre
                        $q->where(function ($sub) use ($palabra) {
                            $sub->where('primer_nombre',    'LIKE', "%{$palabra}%")
                                ->orWhere('segundo_nombre',  'LIKE', "%{$palabra}%")
                                ->orWhere('primer_apellido', 'LIKE', "%{$palabra}%")
                                ->orWhere('segundo_apellido','LIKE', "%{$palabra}%");
                        });
                    }
                });
            }
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
            // Subquery: último contrato por cédula (por ID descendente)
            $subs = DB::table('contratos as c')
                ->join(DB::raw('(SELECT cedula, MAX(id) AS max_id FROM contratos WHERE aliado_id = ? GROUP BY cedula) as ult'), function ($j) {
                    $j->on('c.cedula', '=', 'ult.cedula')->on('c.id', '=', 'ult.max_id');
                })
                ->addBinding($aliadoId, 'join')
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

        return view('admin.clientes.index', compact('clientes', 'buscar', 'filtroEmpresa', 'empresas', 'ultimosContratos'));
    }

    // ─── Crear nuevo cliente ──────────────────────────────────────────
    public function create()
    {
        $cliente = new Cliente();
        $lookups = $this->getLookups();
        $contratos = collect();

        return view('admin.clientes.form', compact('cliente', 'lookups', 'contratos'));
    }

    // ─── Guardar nuevo ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->validarCliente($request);
        $data = $this->limpiarDatos($data);

        // Obtener siguiente ID (la tabla no es autoincrement)
        $maxId = DB::table('clientes')->max('id') ?? 0;
        $data['id'] = $maxId + 1;
        $data['aliado_id'] = session('aliado_id_activo');

        Cliente::create($data);

        return redirect()->route('admin.clientes.edit', $data['id'])
            ->with('success', 'Cliente creado exitosamente.');
    }

    // ─── Editar cliente existente ─────────────────────────────────────
    public function edit(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $lookups = $this->getLookups();
        $contratos = DB::table('contratos as ct')
            ->leftJoin('tipo_modalidad as tm', 'tm.id', '=', 'ct.tipo_modalidad_id')
            ->leftJoin('planes_contrato as pc', 'pc.id', '=', 'ct.plan_id')
            ->where('ct.cedula', $cliente->cedula)
            ->where('ct.aliado_id', session('aliado_id_activo'))
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

        $bancos = \App\Models\BancoCuenta::activas(session('aliado_id_activo'));

        return view('admin.clientes.form', compact('cliente', 'lookups', 'contratos', 'razonesMap', 'resumen', 'bancos'));
    }

    // ─── Actualizar ───────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $data = $this->validarCliente($request, $id);
        $data = $this->limpiarDatos($data);

        $cliente->update($data);

        return redirect()->route('admin.clientes.edit', $id)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    // ─── Buscar cliente por cédula (AJAX) ─────────────────────────────
    public function buscarPorCedula(Request $request)
    {
        $cedula = $request->get('cedula');
        if (!$cedula) return response()->json(null);

        $cliente = Cliente::where('cedula', $cedula)->first();

        if ($cliente) {
            return response()->json([
                'encontrado' => true,
                'id'         => $cliente->id,
                'nombre'     => $cliente->nombre_completo,
            ]);
        }

        return response()->json(['encontrado' => false]);
    }

    // ─── Helpers Privados ─────────────────────────────────────────────

    private function validarCliente(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'tipo_doc'            => 'nullable|string|max:10',
            'cod_empresa'         => 'nullable|integer',
            'cedula'              => 'required|numeric',
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
            'sisben'              => 'nullable|string|max:50',
            'ips'                 => 'nullable|string|max:100',
            'urgencias'           => 'nullable|string|max:100',
            'iva'                 => 'nullable|string|max:20',
            'observacion'         => 'nullable|string',
        ], [
            'cedula.required'          => 'La cédula es obligatoria.',
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
                'RC'  => 'RC - Registro Civil',
                'NIT' => 'NIT - Número de Identificación Tributaria',
                'PP'  => 'PP - Permiso de Protección Temporal',
                'CD'  => 'CD - Carné Diplomático',
                'SC'  => 'SC - Salvoconducto',
                'PE'  => 'PE - Permiso Especial de Permanencia',
                'AS'  => 'AS - Adulto Sin Identificación',
                'MS'  => 'MS - Menor Sin Identificación',
            ],
            'generos'       => ['M' => 'Masculino', 'F' => 'Femenino'],
            'rh'            => ['O+' => 'O+', 'O-' => 'O-', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-'],
            'sisben'        => ['NC' => 'NC - Sin Sisben', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
        ];
    }
}
