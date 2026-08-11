<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsesorNivel;
use App\Models\AsesorNivelTarifa;
use App\Services\TarifaAsesorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Niveles de asesor: las plantillas de comisión agrupadas por tamaño de cartera.
 *
 * Un nivel guarda UNA admon mensual (igual para todos los planes) y, por cada celda
 * plan × modalidad × riesgo, cuánto gana el asesor de la afiliación. El precio público, el
 * retiro y los "otros" NO se editan aquí: se heredan de Parámetros y se muestran en gris,
 * porque los manda el plan. Lo del aliado es el resto.
 *
 * Al asignarle el nivel a un asesor, la plantilla se COPIA a su matriz propia
 * (Asesor::aplicarNivel) y desde ahí es libre: editar el nivel no reescribe a nadie.
 */
class AsesorNivelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function aliadoId(): int
    {
        return (int) session('aliado_id_activo');
    }

    /** Todo lo que escribe exige asesores.gestionar; mirar basta con asesores.ver. */
    private function autorizarEdicion(): void
    {
        if (! auth()->user()->can('asesores.gestionar')) {
            abort(403, 'No tienes permiso para gestionar niveles de asesores.');
        }
    }

    private function autorizarLectura(): void
    {
        if (! auth()->user()->can('asesores.ver')) {
            abort(403, 'No tienes permiso para ver los niveles de asesores.');
        }
    }

    /** El nivel debe ser del aliado activo: nunca resolverlo solo por id. */
    private function nivelDelAliado(int $id): AsesorNivel
    {
        return AsesorNivel::where('aliado_id', $this->aliadoId())->findOrFail($id);
    }

    // ─────────────────────────────────────────────────────────────────
    // Listado + alta
    // ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->autorizarLectura();
        $alidoId = $this->aliadoId();

        $niveles = AsesorNivel::where('aliado_id', $alidoId)
            ->withCount(['tarifas', 'asesores'])
            ->orderBy('orden')
            ->get();

        // Cuántas celdas debería tener un nivel completo, para el badge de avance.
        $totalCeldas = collect(TarifaAsesorService::combinaciones())
            ->sum(fn ($c) => count($c['niveles']));

        // Sugerencia de nivel por cartera: se calcula una vez para toda la lista.
        $asesores = \App\Models\Asesor::where('aliado_id', $alidoId)->activos()->orderBy('nombre')->get();
        $vigentesPorAsesor = DB::table('contratos')
            ->where('aliado_id', $alidoId)
            ->where('estado', 'vigente')
            ->whereNotNull('asesor_id')
            ->select('asesor_id', DB::raw('count(*) as total'))
            ->groupBy('asesor_id')
            ->pluck('total', 'asesor_id');

        return view('admin.configuracion.niveles.index', compact(
            'niveles', 'totalCeldas', 'asesores', 'vigentesPorAsesor'
        ));
    }

    public function store(Request $request)
    {
        $this->autorizarEdicion();

        $datos = $this->validarNivel($request);
        $datos['aliado_id'] = $this->aliadoId();
        $datos['activo'] = $request->boolean('activo', true);

        $nivel = AsesorNivel::create($datos);

        return redirect()
            ->route('admin.configuracion.niveles.matriz', $nivel->id)
            ->with('success', "Nivel «{$nivel->nombre}» creado. Ahora define cuánto gana el asesor por cada plan.");
    }

    public function update(Request $request, int $id)
    {
        $this->autorizarEdicion();
        $nivel = $this->nivelDelAliado($id);

        $datos = $this->validarNivel($request, $id);
        $datos['activo'] = $request->boolean('activo');
        $nivel->update($datos);

        TarifaAsesorService::limpiarCache();

        return back()->with('success', "Nivel «{$nivel->nombre}» actualizado.");
    }

    public function destroy(int $id)
    {
        $this->autorizarEdicion();
        $nivel = $this->nivelDelAliado($id);

        // Un nivel asignado no se borra: los asesores quedarían apuntando a la nada y su
        // matriz propia (que es una copia) perdería el rastro de dónde salió.
        $enUso = $nivel->asesores()->count();
        if ($enUso > 0) {
            return back()->with('error', "No se puede eliminar: {$enUso} asesor(es) tienen este nivel. Desactívalo o cámbiales el nivel primero.");
        }

        $nombre = $nivel->nombre;
        DB::transaction(function () use ($nivel) {
            $nivel->tarifas()->delete();
            $nivel->delete();
        });

        TarifaAsesorService::limpiarCache();

        return redirect()->route('admin.configuracion.niveles.index')
            ->with('success', "Nivel «{$nombre}» eliminado.");
    }

    /** Copia el nivel completo con su matriz — la forma rápida de crear el nivel 2 desde el 1. */
    public function duplicar(int $id)
    {
        $this->autorizarEdicion();
        $origen = $this->nivelDelAliado($id);

        $nuevo = DB::transaction(function () use ($origen) {
            $copia = AsesorNivel::create([
                'aliado_id' => $origen->aliado_id,
                'nombre' => $origen->nombre.' (copia)',
                'descripcion' => $origen->descripcion,
                'orden' => (int) $origen->orden + 1,
                'contratos_min' => $origen->contratos_min,
                'contratos_max' => $origen->contratos_max,
                'admon_asesor' => $origen->admon_asesor,
                'activo' => true,
            ]);

            // En bloque: duplicar celda por celda tardaba ~45s con la matriz llena.
            $ahora = now();
            $filas = $origen->tarifas->map(fn ($t) => [
                'asesor_nivel_id' => $copia->id,
                'plan_id' => $t->plan_id,
                'tipo_modalidad_id' => $t->tipo_modalidad_id,
                'nivel_arl' => $t->nivel_arl,
                'afil_asesor' => $t->afil_asesor,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all();

            foreach (array_chunk($filas, 150) as $lote) {
                AsesorNivelTarifa::insert($lote);
            }

            return $copia;
        });

        TarifaAsesorService::limpiarCache();

        return redirect()->route('admin.configuracion.niveles.matriz', $nuevo->id)
            ->with('success', "Nivel duplicado desde «{$origen->nombre}». Ajusta los valores del asesor.");
    }

    // ─────────────────────────────────────────────────────────────────
    // Matriz del nivel
    // ─────────────────────────────────────────────────────────────────

    public function matriz(int $id)
    {
        $this->autorizarLectura();
        $alidoId = $this->aliadoId();
        $nivel = $this->nivelDelAliado($id);

        // Base heredada de Parámetros (público/retiro/otros/admon), para pintar en gris y para
        // que el navegador calcule el sobrante del aliado en vivo.
        $base = TarifaAsesorService::baseTarifario($alidoId);

        $valores = AsesorNivelTarifa::where('asesor_nivel_id', $nivel->id)
            ->get()
            ->keyBy(fn ($t) => AsesorNivelTarifa::claveCelda(
                (int) $t->plan_id, (int) $t->tipo_modalidad_id, (int) $t->nivel_arl
            ));

        $matriz = TarifaAsesorService::armarMatriz($base, $valores);

        return view('admin.configuracion.niveles.matriz', [
            'nivel' => $nivel,
            'nivelDelAsesor' => null,
            'admonValor' => $nivel->admon_asesor,
            'volverUrl' => route('admin.configuracion.niveles.index'),
            'volverTexto' => 'Volver a Niveles',
            'matriz' => $matriz,
            'base' => $base,
            'valores' => $valores,
            'niveles' => AsesorNivel::where('aliado_id', $alidoId)->orderBy('orden')->get(),
            'rutaGuarda' => route('admin.configuracion.niveles.matriz.guardar', $nivel->id),
            'titulo' => "Nivel: {$nivel->nombre}",
            'contexto' => 'nivel',
        ]);
    }

    public function guardarMatriz(Request $request, int $id)
    {
        $this->autorizarEdicion();
        $nivel = $this->nivelDelAliado($id);

        $request->validate([
            'admon_asesor' => 'nullable|numeric|min:0',
            'matriz.*.*.*' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $nivel) {
            if ($request->filled('admon_asesor') || $request->has('admon_asesor')) {
                $nivel->update(['admon_asesor' => (float) ($request->input('admon_asesor') ?: 0)]);
            }

            // Vacío = el asesor no gana nada configurado en esa celda: la fila se borra para
            // que caiga a la cascada (comisión plana), no para dejar un 0 fijo.
            TarifaAsesorService::sincronizarCeldas(
                AsesorNivelTarifa::class,
                ['asesor_nivel_id' => $nivel->id],
                $request->input('matriz', []),
                ['afil_asesor']
            );
        });

        TarifaAsesorService::limpiarCache();

        $descuadradas = count(TarifaAsesorService::celdasDescuadradas($this->aliadoId()));
        $aviso = $descuadradas > 0
            ? " Ojo: {$descuadradas} celda(s) quedaron por encima del precio de afiliación."
            : '';

        return back()->with('success', "Matriz del nivel «{$nivel->nombre}» guardada.".$aviso);
    }

    // ─────────────────────────────────────────────────────────────────
    // Privados
    // ─────────────────────────────────────────────────────────────────

    private function validarNivel(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'orden' => 'nullable|integer|min:0|max:999',
            'contratos_min' => 'required|integer|min:0',
            'contratos_max' => 'nullable|integer|min:0|gte:contratos_min',
            'admon_asesor' => 'required|numeric|min:0',
        ], [
            'nombre.required' => 'Ponle un nombre al nivel.',
            'contratos_min.required' => 'Indica desde cuántos contratos aplica este nivel.',
            'contratos_max.gte' => 'El tope de contratos no puede ser menor que el mínimo.',
            'admon_asesor.required' => 'Indica la administración mensual del asesor.',
        ]);

        $datos['orden'] = $datos['orden'] ?? 0;
        // Vacío = "sin tope": es el último nivel de la escalera.
        $datos['contratos_max'] = ($request->input('contratos_max') === '' || $request->input('contratos_max') === null)
            ? null
            : (int) $request->input('contratos_max');

        return $datos;
    }
}
