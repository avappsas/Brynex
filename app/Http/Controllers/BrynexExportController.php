<?php

namespace App\Http\Controllers;

use App\Models\Aliado;
use App\Models\Bitacora;
use App\Models\ExportacionAliado;
use App\Services\AlertaOperativaService;
use App\Services\Exportacion\ExportAliadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega de datos a un aliado que se va de la plataforma.
 *
 * El flujo es de dos pasos a propósito: se pide, llega un código al celular de
 * BryNex, y solo con ese código se genera. La sesión sola no alcanza para sacar
 * los datos personales de miles de personas.
 *
 * El código va al número fijo de `services.whatsapp.alertas_numero`, no al
 * teléfono del perfil del usuario: si saliera del perfil, quien se apodere de
 * la cuenta cambia su teléfono y recibe su propio código, y el segundo factor
 * dejaría de existir.
 *
 * Si WhatsApp no responde, la salida es `php artisan aliado:exportar`, que corre
 * fuera de la web sobre el mismo servicio.
 */
class BrynexExportController extends Controller
{
    public function __construct(
        private ExportAliadoService $exportador,
        private AlertaOperativaService $alertas,
    ) {
        $this->middleware(['auth', 'exportacion.access']);
    }

    // ── Pantalla ─────────────────────────────────────────────────────────

    public function index()
    {
        $this->exportador->purgarVencidas();

        $aliados = Aliado::orderBy('nombre')->get();

        $entregas = ExportacionAliado::with(['aliado', 'solicitante'])
            ->whereIn('estado', ['generado', 'fallido'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $pendiente = ExportacionAliado::with('aliado')
            ->where('estado', 'pendiente')
            ->where('solicitado_por', Auth::id())
            ->where('codigo_expira_at', '>', now())
            ->orderByDesc('id')
            ->first();

        return view('brynex.exportaciones', compact('aliados', 'entregas', 'pendiente'));
    }

    // ── Paso 1: pedir el código ──────────────────────────────────────────

    public function solicitar(Request $request)
    {
        $datos = $request->validate([
            'aliado_id' => 'required|integer|exists:aliados,id',
        ]);

        $aliado = Aliado::findOrFail($datos['aliado_id']);

        // Una solicitud viva por usuario: dos códigos al mismo tiempo solo
        // sirven para equivocarse.
        ExportacionAliado::where('estado', 'pendiente')
            ->where('solicitado_por', Auth::id())
            ->update(['estado' => 'cancelado', 'codigo_hash' => null]);

        $registro = ExportacionAliado::create([
            'aliado_id' => $aliado->id,
            'solicitado_por' => Auth::id(),
            'estado' => 'pendiente',
            'ip' => $request->ip(),
        ]);

        $codigo = $registro->generarCodigo();

        $enviado = $this->alertas->enviar(
            'Entrega de datos',
            sprintf(
                'Codigo %s para exportar los datos de %s. Vence en %d minutos. Si no lo pidio usted, avise de inmediato.',
                $codigo,
                $aliado->nombre,
                (int) config('exportacion.codigo_minutos')
            )
        );

        if (! $enviado) {
            $registro->update(['estado' => 'fallido', 'error' => 'No se pudo enviar el código por WhatsApp.']);

            return back()->with('error',
                'No se pudo enviar el código por WhatsApp. Use el comando '.
                '"php artisan aliado:exportar '.$aliado->id.'" o revise la configuración de WhatsApp de Brygar.'
            );
        }

        Bitacora::registrar(
            'created', 'ExportacionAliado', $registro->id,
            'Solicitó la entrega de datos de '.$aliado->nombre,
            ['aliado' => $aliado->nombre], (int) $aliado->id
        );

        return back()->with('success', 'Le llegó un código al WhatsApp de BryNex. Tiene '.config('exportacion.codigo_minutos').' minutos para confirmarlo.');
    }

    // ── Paso 2: confirmar y generar ──────────────────────────────────────

    public function confirmar(Request $request)
    {
        $datos = $request->validate([
            'exportacion_id' => 'required|integer',
            'codigo' => 'required|string|max:10',
        ]);

        $registro = ExportacionAliado::where('id', $datos['exportacion_id'])
            ->where('solicitado_por', Auth::id())
            ->firstOrFail();

        if ($registro->estado !== 'pendiente') {
            return back()->with('error', 'Esa solicitud ya no está vigente. Pida una nueva.');
        }

        if ($registro->codigoVencido()) {
            $registro->update(['estado' => 'vencido', 'codigo_hash' => null]);

            return back()->with('error', 'El código venció. Pida uno nuevo.');
        }

        if (! $registro->codigoValido($datos['codigo'])) {
            $restantes = max(0, (int) config('exportacion.codigo_intentos') - $registro->fresh()->intentos);

            return back()->with('error', $restantes > 0
                ? "Código incorrecto. Le quedan {$restantes} intento(s)."
                : 'Código incorrecto. La solicitud se canceló por seguridad.');
        }

        $registro->update([
            'estado' => 'confirmado',
            'confirmado_at' => now(),
            'codigo_hash' => null,
        ]);

        try {
            $registro = $this->exportador->generar($registro);
        } catch (\Throwable $e) {
            return back()->with('error', 'La generación falló: '.$e->getMessage());
        }

        $aliadoNombre = $registro->aliado->nombre ?? '';

        Bitacora::registrar(
            'created', 'ExportacionAliado', $registro->id,
            'Generó la entrega de datos de '.$aliadoNombre.' ('.number_format((int) $registro->filas_total, 0, ',', '.').' registros)',
            ['hash' => $registro->archivo_hash, 'filas' => $registro->filas_total],
            (int) $registro->aliado_id
        );

        $this->alertas->enviar(
            'Entrega de datos',
            sprintf(
                'Se genero la entrega #%d de %s: %s registros, %s. La descarga queda disponible %d dias.',
                $registro->id,
                $aliadoNombre,
                number_format((int) $registro->filas_total, 0, ',', '.'),
                $registro->tamanoLegible(),
                (int) config('exportacion.dias_retencion')
            )
        );

        return redirect()
            ->route('brynex.exportaciones.index')
            ->with('generada', $registro->id);
    }

    public function cancelar(Request $request)
    {
        ExportacionAliado::where('estado', 'pendiente')
            ->where('solicitado_por', Auth::id())
            ->update(['estado' => 'cancelado', 'codigo_hash' => null]);

        return back()->with('success', 'Solicitud cancelada.');
    }

    // ── Descarga ─────────────────────────────────────────────────────────

    public function descargar(int $id)
    {
        $registro = ExportacionAliado::with('aliado')->findOrFail($id);

        if (! $registro->disponible() || ! Storage::disk('local')->exists($registro->archivo)) {
            return back()->with('error', 'Ese archivo ya no está disponible. Genere la entrega de nuevo.');
        }

        $registro->increment('descargas');
        $registro->update(['ultima_descarga_at' => now()]);

        Bitacora::registrar(
            'updated', 'ExportacionAliado', $registro->id,
            'Descargó la entrega de datos de '.($registro->aliado->nombre ?? ''),
            ['descargas' => $registro->descargas], (int) $registro->aliado_id
        );

        $this->alertas->enviar(
            'Entrega de datos',
            sprintf('Se descargo la entrega #%d de %s (descarga N %d).',
                $registro->id, $registro->aliado->nombre ?? '', (int) $registro->descargas)
        );

        return Storage::disk('local')->download($registro->archivo, basename($registro->archivo));
    }

    /** Vuelve a mostrar la contraseña del ZIP, sin regenerar 400.000 filas. */
    public function password(int $id)
    {
        $registro = ExportacionAliado::findOrFail($id);

        if (! $registro->disponible()) {
            return response()->json(['error' => 'No disponible'], 404);
        }

        Bitacora::registrar(
            'updated', 'ExportacionAliado', $registro->id,
            'Consultó la contraseña de la entrega #'.$registro->id,
            null, (int) $registro->aliado_id
        );

        return response()->json(['password' => $registro->passwordPlano()]);
    }

    public function eliminar(int $id)
    {
        $registro = ExportacionAliado::with('aliado')->findOrFail($id);

        if ($registro->archivo) {
            Storage::disk('local')->delete($registro->archivo);
        }

        $registro->update(['purgado_at' => now(), 'zip_password' => null]);

        Bitacora::registrar(
            'deleted', 'ExportacionAliado', $registro->id,
            'Borró el archivo de la entrega de datos de '.($registro->aliado->nombre ?? ''),
            null, (int) $registro->aliado_id
        );

        return back()->with('success', 'Archivo borrado del servidor.');
    }
}
