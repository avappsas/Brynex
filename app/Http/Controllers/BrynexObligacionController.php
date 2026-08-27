<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\BrynexCalendarioVencimiento;
use App\Models\BrynexObligacion;
use App\Models\BrynexObligacionCatalogo;
use App\Models\BrynexObligacionDocumento;
use App\Models\BrynexRazonSocial;
use App\Services\BrynexRazonSocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * El checklist: chulear una obligación, subir su soporte y mantener el
 * calendario de vencimientos.
 */
class BrynexObligacionController extends Controller
{
    public function __construct(private BrynexRazonSocialService $servicio)
    {
        $this->middleware(['auth']);
    }

    // ─── Chulear un renglón ───────────────────────────────────────────

    public function actualizar(Request $request, int $id)
    {
        $obligacion = BrynexObligacion::findOrFail($id);

        $datos = $request->validate([
            'estado' => 'required|in:pendiente,presentada,pagada,no_aplica',
            'valor_pagado' => 'nullable|numeric|min:0|max:99999999999',
            'fecha_pago' => 'nullable|date',
            'observacion' => 'nullable|string|max:500',
        ]);

        // Marcar como pagada sin decir cuándo deja el histórico cojo: si no
        // ponen fecha, se asume hoy.
        if ($datos['estado'] === 'pagada' && empty($datos['fecha_pago'])) {
            $datos['fecha_pago'] = now()->toDateString();
        }

        $antes = $obligacion->estado;
        $datos['usuario_id'] = auth()->id();
        $obligacion->update($datos);

        Bitacora::registrar(
            'updated', 'BrynexObligacion', $obligacion->id,
            "{$obligacion->obligacion_codigo} {$obligacion->periodo_etiqueta} {$obligacion->anio}: {$antes} → {$obligacion->estado}",
            ['ficha_id' => $obligacion->ficha_id, 'valor' => $datos['valor_pagado'] ?? null]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'semaforo' => $obligacion->semaforo(),
                'estado' => $obligacion->estado,
            ]);
        }

        return back()->with('success', 'Obligación actualizada.');
    }

    /** Regenera los renglones que falten (tras cambiar el régimen, por ejemplo). */
    public function regenerar(Request $request, int $fichaId)
    {
        $ficha = BrynexRazonSocial::findOrFail($fichaId);

        if (! $ficha->regimen || ! $ficha->fecha_constitucion) {
            return back()->with('error', 'Falta el régimen o la fecha de constitución.');
        }

        $creadas = $this->servicio->generarObligaciones(
            $ficha,
            $request->filled('anio') ? (int) $request->get('anio') : null
        );

        return back()->with('success', $creadas
            ? "Se generaron {$creadas} obligaciones nuevas."
            : 'No faltaba ninguna obligación por generar.');
    }

    // ─── Soportes ─────────────────────────────────────────────────────

    /**
     * Sube el soporte de una obligación (la declaración, el recibo de pago).
     *
     * Va al disco `local` — storage/app — que NO se sirve por HTTP. Una
     * declaración de renta trae el NIT, los ingresos y el patrimonio de la
     * empresa; en `public` quedaría a un URL de distancia (C-4 de la
     * auditoría de seguridad).
     */
    public function subirDocumento(Request $request, int $id)
    {
        $obligacion = BrynexObligacion::findOrFail($id);

        $request->validate([
            'archivo' => 'required|file|max:15360|mimes:pdf,jpg,jpeg,png,webp,xls,xlsx,zip',
        ], [
            'archivo.max' => 'El archivo no puede pesar más de 15 MB.',
            'archivo.mimes' => 'Solo se aceptan PDF, imágenes, Excel o ZIP.',
        ]);

        $archivo = $request->file('archivo');
        $ficha = $obligacion->ficha;

        $ruta = $archivo->store(
            "brynex/razones-sociales/{$ficha->nit}/{$obligacion->anio}",
            'local'
        );

        $documento = BrynexObligacionDocumento::create([
            'obligacion_id' => $obligacion->id,
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'mime' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'subido_por' => auth()->id(),
        ]);

        // Subir el soporte sin mover el estado es el olvido más común: si
        // seguía pendiente, queda al menos como presentada.
        if ($obligacion->estado === 'pendiente') {
            $obligacion->update(['estado' => 'presentada', 'usuario_id' => auth()->id()]);
        }

        Bitacora::registrar(
            'created', 'BrynexObligacionDocumento', $documento->id,
            "Soporte «{$documento->nombre_original}» de {$obligacion->obligacion_codigo} {$obligacion->anio}",
            ['ficha_id' => $ficha->id]
        );

        return back()->with('success', 'Soporte subido.');
    }

    public function descargarDocumento(int $id)
    {
        $documento = BrynexObligacionDocumento::findOrFail($id);

        abort_unless(Storage::disk('local')->exists($documento->ruta), 404, 'El archivo ya no está.');

        Bitacora::registrar(
            'descarga', 'BrynexObligacionDocumento', $documento->id,
            "Descarga de «{$documento->nombre_original}»"
        );

        return Storage::disk('local')->download($documento->ruta, $documento->nombre_original);
    }

    public function eliminarDocumento(int $id)
    {
        $documento = BrynexObligacionDocumento::findOrFail($id);

        Storage::disk('local')->delete($documento->ruta);
        $nombre = $documento->nombre_original;
        $documento->delete();

        Bitacora::registrar(
            'deleted', 'BrynexObligacionDocumento', $id, "Soporte «{$nombre}» eliminado"
        );

        return back()->with('success', 'Soporte eliminado.');
    }

    // ─── Calendario ───────────────────────────────────────────────────

    /**
     * Mantenimiento del calendario. Es donde se cargan a mano las fechas que
     * la DIAN no publica en el calendario tributario (la exógena sale por
     * resolución aparte) y las del ICA, que las fija cada municipio.
     */
    public function calendario(Request $request)
    {
        $anio = (int) $request->get('anio', now()->year);

        $vencimientos = BrynexCalendarioVencimiento::where('anio', $anio)
            ->orderBy('obligacion_codigo')
            ->orderBy('periodo')
            ->orderByRaw('CASE WHEN ultimo_digito IS NULL THEN -1 ELSE ultimo_digito END')
            ->get()
            ->groupBy('obligacion_codigo');

        return view('brynex.razones_sociales.calendario', [
            'anio' => $anio,
            'anios' => BrynexCalendarioVencimiento::select('anio')->distinct()->orderByDesc('anio')->pluck('anio'),
            'vencimientos' => $vencimientos,
            'catalogo' => BrynexObligacionCatalogo::orderBy('orden')->get()->keyBy('codigo'),
        ]);
    }

    /**
     * Guarda las 10 fechas de un período (una por dígito del NIT), o una sola
     * si la obligación no depende del NIT.
     */
    public function guardarCalendario(Request $request)
    {
        $datos = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'obligacion_codigo' => 'required|exists:brynex_obligaciones_catalogo,codigo',
            'periodo' => 'required|integer|min:1|max:12',
            'depende_nit' => 'required|boolean',
            'fecha_unica' => 'required_if:depende_nit,0|nullable|date',
            'fechas' => 'required_if:depende_nit,1|nullable|array',
            'fechas.*' => 'nullable|date',
        ]);

        if (! $datos['depende_nit']) {
            BrynexCalendarioVencimiento::updateOrCreate(
                [
                    'anio' => $datos['anio'],
                    'obligacion_codigo' => $datos['obligacion_codigo'],
                    'periodo' => $datos['periodo'],
                    'ultimo_digito' => null,
                ],
                ['fecha_vencimiento' => $datos['fecha_unica']]
            );
        } else {
            foreach ($datos['fechas'] as $digito => $fecha) {
                if (! $fecha) {
                    continue;
                }

                BrynexCalendarioVencimiento::updateOrCreate(
                    [
                        'anio' => $datos['anio'],
                        'obligacion_codigo' => $datos['obligacion_codigo'],
                        'periodo' => $datos['periodo'],
                        'ultimo_digito' => (int) $digito,
                    ],
                    ['fecha_vencimiento' => $fecha]
                );
            }
        }

        $actualizados = $this->propagarACheckLists(
            (int) $datos['anio'], $datos['obligacion_codigo'], (int) $datos['periodo']
        );

        Bitacora::registrar(
            'updated', 'BrynexCalendarioVencimiento', null,
            "Calendario {$datos['anio']} · {$datos['obligacion_codigo']} período {$datos['periodo']}",
            ['renglones_actualizados' => $actualizados]
        );

        return back()->with('success',
            "Calendario guardado. Se actualizó la fecha en {$actualizados} renglón(es) del checklist.");
    }

    /**
     * Cuando se carga una fecha que antes no existía (la exógena, el ICA), los
     * renglones ya generados están con `fecha_vencimiento` en null. Aquí se
     * les pone la fecha para que entren al semáforo.
     *
     * Solo toca los que siguen abiertos: una obligación ya pagada conserva la
     * fecha con la que se trabajó.
     */
    private function propagarACheckLists(int $anio, string $codigo, int $periodo): int
    {
        $fechas = BrynexCalendarioVencimiento::where('anio', $anio)
            ->where('obligacion_codigo', $codigo)
            ->where('periodo', $periodo)
            ->get();

        if ($fechas->isEmpty()) {
            return 0;
        }

        $porDigito = $fechas->keyBy(fn ($f) => $f->ultimo_digito ?? 'todos');
        $total = 0;

        $pendientes = BrynexObligacion::query()
            ->join('brynex_razones_sociales as f', 'f.id', '=', 'brynex_obligaciones.ficha_id')
            ->where('brynex_obligaciones.anio', $anio)
            ->where('brynex_obligaciones.obligacion_codigo', $codigo)
            ->where('brynex_obligaciones.periodo', $periodo)
            ->whereNotIn('brynex_obligaciones.estado', BrynexObligacion::ESTADOS_CERRADOS)
            ->select('brynex_obligaciones.id', 'f.nit')
            ->get();

        foreach ($pendientes as $fila) {
            $digito = (int) substr((string) $fila->nit, -1);
            $fecha = $porDigito->get($digito) ?? $porDigito->get('todos');

            if (! $fecha) {
                continue;
            }

            BrynexObligacion::where('id', $fila->id)
                ->update(['fecha_vencimiento' => $fecha->fecha_vencimiento->toDateString()]);
            $total++;
        }

        return $total;
    }
}
