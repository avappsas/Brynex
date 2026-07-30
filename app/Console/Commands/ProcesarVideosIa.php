<?php

namespace App\Console\Commands;

use App\Models\IaConfiguracionAliado;
use App\Models\PublicidadVideoIa;
use App\Services\Publicidad\VeoVideoGenerator;
use App\Services\Publicidad\VideoOverlayFfmpeg;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Avanza el pipeline asíncrono de video publicitario (Veo + overlay FFmpeg). Programado cada
 * minuto en Kernel::schedule() — Veo tarda 1-3 min típico para un clip de 8s, y no hay worker
 * de colas desplegado (QUEUE_CONNECTION=sync) — este comando hace el mismo trabajo que un job
 * en background haría, con el mismo patrón cron+estado-en-BD que ya usa `publicaciones:despachar`.
 */
class ProcesarVideosIa extends Command
{
    protected $signature = 'videos:procesar';

    protected $description = 'Avanza la generación de videos IA (Veo) pendientes: consulta estado, aplica overlay y marca listos';

    public function handle(): int
    {
        $pendientes = PublicidadVideoIa::where('estado', PublicidadVideoIa::ESTADO_GENERANDO)->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay videos pendientes.');
            return self::SUCCESS;
        }

        foreach ($pendientes as $video) {
            $this->procesarUno($video);
        }

        return self::SUCCESS;
    }

    private function procesarUno(PublicidadVideoIa $video): void
    {
        $iaConfig = IaConfiguracionAliado::paraAliado($video->aliado_id);
        if (!$iaConfig->gemini_api_key) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => 'Ya no hay una clave de Gemini configurada para este aliado.']);
            return;
        }

        $estado = VeoVideoGenerator::consultarEstado($iaConfig->gemini_api_key, $video->operation_name);

        if (!$estado['ok']) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => $estado['error']]);
            return;
        }

        if (!$estado['done']) {
            $this->info("Video #{$video->id} todavía generándose en Veo.");
            return; // se vuelve a intentar en el próximo tick del cron
        }

        $aliado = $video->aliado;

        Storage::disk('public')->makeDirectory('publicidad/video_ia');

        $rutaBrutaAbsoluta = Storage::disk('public')->path('publicidad/video_ia/bruto_' . Str::random(20) . '.mp4');

        if (!VeoVideoGenerator::descargar($iaConfig->gemini_api_key, $estado['videoUri'], $rutaBrutaAbsoluta)) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => 'No se pudo descargar el video generado por Veo.']);
            return;
        }

        $rutaFinalRelativa  = 'publicidad/video_ia/' . Str::random(20) . '.mp4';
        $rutaPosterRelativa = 'publicidad/video_ia/' . Str::random(20) . '.jpg';

        $overlay = VideoOverlayFfmpeg::aplicar(
            $rutaBrutaAbsoluta,
            $video->frases_texto ?? [],
            $aliado?->logo_marca_claro,
            $aliado?->logo_marca_recorte,
            $aliado?->color_primario,
            Storage::disk('public')->path($rutaFinalRelativa),
            Storage::disk('public')->path($rutaPosterRelativa)
        );

        @unlink($rutaBrutaAbsoluta);

        if (!$overlay['ok']) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => $overlay['error']]);
            return;
        }

        $video->update([
            'estado'             => PublicidadVideoIa::ESTADO_LISTA,
            'video_path'         => $rutaFinalRelativa,
            'imagen_poster_path' => $overlay['posterPath'] ? $rutaPosterRelativa : null,
        ]);

        $this->info("Video #{$video->id} listo.");
    }
}
