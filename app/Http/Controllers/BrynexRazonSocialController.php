<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\BrynexObligacion;
use App\Models\BrynexRazonSocial;
use App\Models\RazonSocialCredencial;
use App\Models\User;
use App\Services\BrynexRazonSocialService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Administración de razones sociales de BryNex.
 *
 * ── Por qué este módulo cruza aliados a propósito ────────────────────────
 *
 * Todo lo demás en BryNex filtra por `session('aliado_id_activo')`. Aquí no:
 * la pregunta que responde el módulo ("¿cuántas personas hay afiliadas a esta
 * empresa?") no tiene respuesta dentro de un solo aliado, porque la misma
 * razón social la usan varios. El aislamiento lo da el permiso, no el filtro:
 * `brynex_razones.*` está marcado `solo_brynex`, así que el Gate::before le
 * cierra la puerta a cualquier usuario que no sea de la casa.
 */
class BrynexRazonSocialController extends Controller
{
    public function __construct(private BrynexRazonSocialService $servicio)
    {
        $this->middleware(['auth']);
    }

    // ─── Listado: todas las razones sociales, agrupadas por NIT ───────

    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar'));
        $filtro = $request->get('filtro', 'todas'); // todas | seguidas | sin_seguir | propias
        $alidoId = $request->get('aliado');

        // 1) Las razones sociales de todos los aliados, agrupadas por NIT.
        //    Se descartan los NIT de menos de 9 dígitos: en la BD hay basura
        //    ('2', '5', '333333') de pruebas y de la migración legacy.
        $grupos = DB::table('razones_sociales')
            ->whereNotNull('nit')
            ->where('nit', '>=', 100000000)
            ->when($alidoId, fn ($q) => $q->where('aliado_id', $alidoId))
            ->groupBy('nit')
            ->get([
                'nit',
                DB::raw('MAX(razon_social) as razon_social'),
                DB::raw('COUNT(DISTINCT aliado_id) as n_aliados'),
                DB::raw('COUNT(*) as n_filas'),
                DB::raw("SUM(CASE WHEN estado = 'Activa' THEN 1 ELSE 0 END) as n_activas"),
            ]);

        // 2) Afiliados vigentes por NIT, en una sola consulta para las 249.
        $vigentes = DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.estado', 'vigente')
            ->whereNotNull('rs.nit')
            ->groupBy('rs.nit')
            // Con alias, no con pluck(DB::raw('COUNT(*)')): sqlsrv devuelve la
            // columna llamada literalmente «COUNT(*)» y pluck no la encuentra.
            ->get(['rs.nit', DB::raw('COUNT(*) as total')])
            ->pluck('total', 'nit');

        // 3) Las fichas que ya existen.
        $fichas = BrynexRazonSocial::all()->keyBy('nit');

        $filas = $grupos->map(function ($g) use ($vigentes, $fichas) {
            $ficha = $fichas->get($g->nit);

            return (object) [
                'nit' => $g->nit,
                'razon_social' => $ficha->razon_social ?? $g->razon_social,
                'n_aliados' => (int) $g->n_aliados,
                'n_activas' => (int) $g->n_activas,
                'afiliados' => (int) ($vigentes[$g->nit] ?? 0),
                'ficha' => $ficha,
                'seguida' => (bool) $ficha?->en_seguimiento,
                'propiedad' => $ficha->propiedad ?? null,
                'regimen' => $ficha->regimen ?? null,
            ];
        });

        if ($buscar !== '') {
            $filas = $filas->filter(
                fn ($f) => stripos($f->razon_social, $buscar) !== false
                    || str_contains((string) $f->nit, preg_replace('/\D/', '', $buscar) ?: '§')
            );
        }

        $filas = match ($filtro) {
            'seguidas' => $filas->filter(fn ($f) => $f->seguida),
            'sin_seguir' => $filas->filter(fn ($f) => ! $f->seguida),
            'propias' => $filas->filter(fn ($f) => $f->propiedad === 'brynex'),
            default => $filas,
        };

        $filas = $filas->sortByDesc('afiliados')->values();

        // Paginación en PHP: son ~200 NIT y ya vinieron todos agrupados; pedir
        // la página al servidor costaría otro viaje de 250 ms sin ganar nada.
        $porPagina = 30;
        $pagina = max(1, (int) $request->get('page', 1));
        $razones = new LengthAwarePaginator(
            $filas->forPage($pagina, $porPagina),
            $filas->count(),
            $porPagina,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('brynex.razones_sociales.index', [
            'razones' => $razones,
            'buscar' => $buscar,
            'filtro' => $filtro,
            'alidoId' => $alidoId,
            'aliados' => DB::table('aliados')->orderBy('nombre')->get(['id', 'nombre']),
            'resumen' => $this->resumenGeneral($filas),
        ]);
    }

    /** Las cifras de la cabecera del listado. */
    private function resumenGeneral($filas): array
    {
        return [
            'total' => $filas->count(),
            'seguidas' => $filas->where('seguida', true)->count(),
            'propias' => $filas->where('propiedad', 'brynex')->count(),
            'afiliados' => $filas->sum('afiliados'),
        ];
    }

    // ─── Poner una razón social en seguimiento ────────────────────────

    /**
     * Crea la ficha maestra. La fecha de constitución se pide aquí y es
     * obligatoria: marca desde qué año se genera el checklist, y está vacía en
     * 203 de las 249 filas de `razones_sociales`, así que no se puede heredar.
     */
    public function seguir(Request $request)
    {
        $datos = $request->validate([
            'nit' => 'required|numeric',
            'razon_social' => 'required|string|max:255',
            'dv' => 'nullable|integer|min:0|max:9',
            'propiedad' => 'required|in:brynex,tercero',
            'regimen' => 'required|in:RST,ORDINARIO',
            'periodicidad_iva' => 'required|in:no_responsable,bimestral,cuatrimestral,anual',
            'fecha_constitucion' => 'required|date|before_or_equal:today',
            'municipio_ica' => 'nullable|string|max:120',
            'periodicidad_ica' => 'nullable|in:bimestral,anual',
            'firma_electronica_vence' => 'nullable|date',
            'contador_id' => 'nullable|integer|exists:users,id',
        ], [
            'fecha_constitucion.required' => 'La fecha de constitución es obligatoria: define desde qué año se genera el checklist.',
        ]);

        if (BrynexRazonSocial::where('nit', $datos['nit'])->exists()) {
            return back()->with('error', 'Esa razón social ya tiene ficha.');
        }

        $datos['en_seguimiento'] = true;
        $datos['creado_por'] = auth()->id();

        $ficha = BrynexRazonSocial::create($datos);

        $vinculos = $this->servicio->sincronizarVinculos($ficha);
        $generadas = $this->servicio->generarObligaciones($ficha);

        Bitacora::registrar(
            'created', 'BrynexRazonSocial', $ficha->id,
            "Razón social {$ficha->razon_social} puesta en seguimiento",
            ['nit' => $ficha->nit, 'vinculos' => $vinculos, 'obligaciones' => $generadas]
        );

        return redirect()
            ->route('brynex.razones.show', $ficha->id)
            ->with('success', "Ficha creada: {$vinculos} razón(es) social(es) enlazada(s) y {$generadas} obligaciones generadas.");
    }

    // ─── Ficha ────────────────────────────────────────────────────────

    public function show(Request $request, int $id)
    {
        $ficha = BrynexRazonSocial::findOrFail($id);
        $anio = (int) $request->get('anio', now()->year);

        // Los vínculos se resincronizan al abrir: si un aliado creó ayer otra
        // fila con este NIT, aparece sin que nadie tenga que acordarse.
        $this->servicio->sincronizarVinculos($ficha);

        // El año de la pestaña es el del PLAZO, no el gravable.
        //
        // `anio` guarda el año gravable, que es lo correcto para cruzar contra
        // el calendario. Pero el contador trabaja por plazo: la declaración
        // anual consolidada del año gravable 2025 se presenta en abril de 2026,
        // y buscarla en la pestaña de 2025 no tiene sentido — en 2025 no había
        // nada que hacer con ella.
        //
        // Los renglones sin fecha (años viejos, sin calendario cargado) caen a
        // su año gravable, que es lo único que se sabe de ellos.
        $porPlazo = 'COALESCE(YEAR(fecha_vencimiento), anio)';

        $obligaciones = $ficha->obligaciones()
            ->with('documentos')
            ->whereRaw("{$porPlazo} = ?", [$anio])
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('obligacion_codigo')
            ->orderBy('periodo')
            ->get();

        $aniosConDatos = $ficha->obligaciones()
            ->selectRaw("DISTINCT {$porPlazo} as anio")
            ->orderByDesc('anio')
            ->pluck('anio');

        return view('brynex.razones_sociales.show', [
            'ficha' => $ficha,
            'anio' => $anio,
            'anios' => $aniosConDatos,
            'obligaciones' => $obligaciones,
            'catalogo' => \App\Models\BrynexObligacionCatalogo::orderBy('orden')->get()->keyBy('codigo'),
            'afiliados' => $this->servicio->afiliadosActivos($ficha),
            'movimientos' => $this->servicio->movimientos($ficha, $anio),
            'credenciales' => $ficha->credenciales()->where('activo', true)->orderBy('tipo')->get(),
            // Con join y no con with('aliado'): son dos consultas de 170 ms
            // para pintar una lista de etiquetas.
            'vinculos' => DB::table('brynex_razon_social_vinculos as v')
                ->leftJoin('aliados as a', 'a.id', '=', 'v.aliado_id')
                ->where('v.ficha_id', $ficha->id)
                ->orderBy('a.nombre')
                ->get(['v.razon_social_id', 'v.aliado_id', 'a.nombre as aliado']),
            'contadores' => User::where('es_brynex', true)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            // Se le pasan las que ya se cargaron arriba: son exactamente las
            // mismas (todo el año), y otra consulta son 250 ms regalados.
            'resumenChecklist' => $this->resumenChecklist($obligaciones),
        ]);
    }

    /** Semáforo del año: cuántas al día, cuántas por vencer, cuántas vencidas. */
    private function resumenChecklist($obligaciones): array
    {
        $conteo = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'gris' => 0];

        foreach ($obligaciones as $o) {
            $conteo[$o->semaforo()]++;
        }

        return $conteo + [
            'total' => $obligaciones->count(),
            'pagado' => (float) $obligaciones->sum('valor_pagado'),
        ];
    }

    // ─── Editar la ficha ──────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        $ficha = BrynexRazonSocial::findOrFail($id);

        $datos = $request->validate([
            'razon_social' => 'required|string|max:255',
            'dv' => 'nullable|integer|min:0|max:9',
            'propiedad' => 'required|in:brynex,tercero',
            'regimen' => 'required|in:RST,ORDINARIO',
            'periodicidad_iva' => 'required|in:no_responsable,bimestral,cuatrimestral,anual',
            'responsabilidades_rut' => 'nullable|array',
            'responsabilidades_rut.*' => 'string|max:4',
            'fecha_constitucion' => 'required|date|before_or_equal:today',
            'firma_electronica_vence' => 'nullable|date',
            'municipio_ica' => 'nullable|string|max:120',
            'periodicidad_ica' => 'nullable|in:bimestral,anual',
            'contador_id' => 'nullable|integer|exists:users,id',
            'estado' => 'required|in:activa,inactiva',
            'notas' => 'nullable|string|max:2000',
        ]);

        $antes = $ficha->only(['regimen', 'periodicidad_iva', 'periodicidad_ica', 'fecha_constitucion']);

        $datos['actualizado_por'] = auth()->id();
        $ficha->update($datos);

        // Si cambió el perfil tributario aparecen obligaciones que antes no
        // aplicaban. Se generan las que falten; las que ya estaban NO se tocan,
        // ni siquiera si dejaron de aplicar — borrar un renglón que el contador
        // ya marcó como pagada sería perder el rastro del pago.
        $generadas = 0;
        if ($ficha->only(array_keys($antes)) != $antes) {
            $generadas = $this->servicio->generarObligaciones($ficha);
        }

        Bitacora::registrar(
            'updated', 'BrynexRazonSocial', $ficha->id,
            "Ficha de {$ficha->razon_social} actualizada",
            ['antes' => $antes, 'obligaciones_nuevas' => $generadas]
        );

        $aviso = 'Ficha actualizada.';
        if ($generadas) {
            $aviso .= " Se generaron {$generadas} obligaciones nuevas por el cambio de régimen.";
        }

        return back()->with('success', $aviso);
    }

    public function dejarDeSeguir(int $id)
    {
        $ficha = BrynexRazonSocial::findOrFail($id);
        $ficha->update(['en_seguimiento' => false, 'actualizado_por' => auth()->id()]);

        Bitacora::registrar(
            'updated', 'BrynexRazonSocial', $ficha->id,
            "Se dejó de hacer seguimiento a {$ficha->razon_social}"
        );

        // No se borra nada: el checklist y los soportes se quedan. Volver a
        // seguirla la deja como estaba.
        return redirect()
            ->route('brynex.razones.index')
            ->with('success', 'Razón social fuera de seguimiento. El histórico se conserva.');
    }

    // ─── Tablero de vencimientos ──────────────────────────────────────

    /**
     * Lo que vence pronto y lo que ya se venció, de todas las razones sociales
     * seguidas. Es la pantalla de entrada del contador.
     */
    public function tablero(Request $request)
    {
        $dias = (int) $request->get('dias', 30);

        $base = BrynexObligacion::query()
            ->join('brynex_razones_sociales as f', 'f.id', '=', 'brynex_obligaciones.ficha_id')
            ->whereNull('f.deleted_at')
            ->where('f.en_seguimiento', true)
            ->where('f.estado', 'activa')
            ->select('brynex_obligaciones.*', 'f.razon_social', 'f.nit', 'f.contador_id');

        $vencidas = (clone $base)->vencidas()
            ->orderBy('fecha_vencimiento')
            ->get();

        $porVencer = (clone $base)->porVencer($dias)
            ->orderBy('fecha_vencimiento')
            ->get();

        // La firma electrónica no es una obligación, pero si caduca no se puede
        // declarar nada: va en el mismo tablero.
        $firmas = BrynexRazonSocial::seguidas()
            ->whereNotNull('firma_electronica_vence')
            ->where('firma_electronica_vence', '<=', now()->addDays(60)->toDateString())
            ->orderBy('firma_electronica_vence')
            ->get();

        return view('brynex.razones_sociales.tablero', [
            'vencidas' => $vencidas,
            'porVencer' => $porVencer,
            'firmas' => $firmas,
            'dias' => $dias,
            'catalogo' => \App\Models\BrynexObligacionCatalogo::orderBy('orden')->get()->keyBy('codigo'),
        ]);
    }

    // ─── Claves de portales ───────────────────────────────────────────

    /**
     * Revelar una contraseña. Deja rastro en la bitácora siempre.
     *
     * La del banco no la ve el contador: mueve plata de terceros, así que pide
     * el permiso restringido `brynex_razones.claves_banco`, que ningún rol
     * hereda — ni el superadmin.
     */
    public function revelarClave(int $id)
    {
        $credencial = RazonSocialCredencial::findOrFail($id);

        if ($credencial->tipo === 'BANCO' && ! auth()->user()->can('brynex_razones.claves_banco')) {
            return response()->json([
                'error' => 'La clave del banco necesita el permiso «Ver claves de banco», que se otorga usuario por usuario.',
            ], 403);
        }

        Bitacora::registrar(
            'clave_revelada', 'RazonSocialCredencial', $credencial->id,
            "Se reveló la clave de {$credencial->entidad} ({$credencial->tipo})",
            ['ficha_id' => $credencial->ficha_id, 'ip' => request()->ip()]
        );

        return response()->json(['contrasena' => $credencial->contrasena]);
    }

    public function guardarClave(Request $request, int $id)
    {
        $ficha = BrynexRazonSocial::findOrFail($id);

        $datos = $request->validate([
            'credencial_id' => 'nullable|integer',
            'tipo' => 'required|in:DIAN,BANCO,CAMARA_COMERCIO,OTRO',
            'entidad' => 'required|string|max:150',
            'usuario' => 'nullable|string|max:150',
            'contrasena' => 'nullable|string|max:200',
            'link_acceso' => 'nullable|url|max:350',
            'observacion' => 'nullable|string|max:500',
        ]);

        // `?? null` y no acceso directo: un campo `nullable` que no viene en la
        // petición tampoco viene en el array de validate().
        $credencialId = $datos['credencial_id'] ?? null;

        $credencial = $credencialId
            ? RazonSocialCredencial::where('ficha_id', $ficha->id)->findOrFail($credencialId)
            : new RazonSocialCredencial([
                'ficha_id' => $ficha->id,
                // Se conserva por auditoría: qué aliado registró la clave.
                // La lectura va por `ficha_id`, que es lo que la comparte.
                'aliado_id' => session('aliado_id_activo') ?? 1,
                'razon_social_id' => $ficha->vinculos()->value('razon_social_id'),
                'creado_por' => auth()->id(),
            ]);

        $credencial->fill([
            'tipo' => $datos['tipo'],
            'entidad' => $datos['entidad'],
            'usuario' => $datos['usuario'] ?? null,
            'link_acceso' => $datos['link_acceso'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'actualizado_por' => auth()->id(),
            'activo' => true,
        ]);

        // Dejar la contraseña vacía en el formulario significa "no la cambies",
        // no "bórrala": si no, editar el link de acceso borraría la clave.
        if (! empty($datos['contrasena'])) {
            $credencial->contrasena = $datos['contrasena'];
        }

        $credencial->save();

        Bitacora::registrar(
            $credencialId ? 'updated' : 'created',
            'RazonSocialCredencial', $credencial->id,
            "Clave de {$credencial->entidad} en {$ficha->razon_social}",
            ['cambio_contrasena' => ! empty($datos['contrasena'])]
        );

        return back()->with('success', 'Clave guardada. Los aliados que usan esta razón social ya ven el cambio.');
    }

    public function eliminarClave(int $id)
    {
        $credencial = RazonSocialCredencial::findOrFail($id);
        $credencial->delete();

        Bitacora::registrar(
            'deleted', 'RazonSocialCredencial', $credencial->id,
            "Clave de {$credencial->entidad} eliminada"
        );

        return back()->with('success', 'Clave eliminada.');
    }
}
