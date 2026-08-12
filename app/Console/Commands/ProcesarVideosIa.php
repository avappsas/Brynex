<?php

namespace App\Console\Commands;

use App\Models\AutopilotConfig;
use App\Models\IaConfiguracionAliado;
use App\Models\Publicacion;
use App\Models\PublicidadVideoIa;
use App\Services\Publicidad\CierreMarcaVideo;
use App\Services\Publicidad\PublicacionPublisher;
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
 *
 * Piezas de una sola escena (4/6/8s) usan `operation_name`/`video_path` directo. Piezas de
 * varias escenas (16s/24s) usan la columna `escenas` (json) — cada una se sondea por separado,
 * y solo cuando TODAS terminan se unen (VideoOverlayFfmpeg::concatenar) y se les monta el
 * overlay encima, igual que a una pieza normal.
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
            if ($video->escenas) {
                $this->procesarMultiEscena($video);
            } else {
                $this->procesarUnaEscena($video);
            }
        }

        return self::SUCCESS;
    }

    private function procesarUnaEscena(PublicidadVideoIa $video): void
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

        $rutaBrutaAbsoluta = $this->rutaTempPublica('bruto_' . Str::random(20) . '.mp4');

        if (!VeoVideoGenerator::descargar($iaConfig->gemini_api_key, $estado['videoUri'], $rutaBrutaAbsoluta)) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => 'No se pudo descargar el video generado por Veo.']);
            return;
        }

        $this->componerYFinalizar($video, [$rutaBrutaAbsoluta]);
    }

    private function procesarMultiEscena(PublicidadVideoIa $video): void
    {
        $iaConfig = IaConfiguracionAliado::paraAliado($video->aliado_id);
        if (!$iaConfig->gemini_api_key) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => 'Ya no hay una clave de Gemini configurada para este aliado.']);
            return;
        }

        $escenas = $video->escenas;
        $huboError = false;

        foreach ($escenas as $i => $escena) {
            if ($escena['estado'] === 'lista') {
                continue;
            }

            $estado = VeoVideoGenerator::consultarEstado($iaConfig->gemini_api_key, $escena['operation_name']);

            if (!$estado['ok']) {
                $escenas[$i]['estado'] = 'error';
                $escenas[$i]['error'] = $estado['error'];
                $huboError = true;
                continue;
            }

            if (!$estado['done']) {
                continue; // esta escena sigue generándose, se reintenta en el próximo tick
            }

            $rutaBruta = $this->rutaTempPublica('escena_' . Str::random(20) . '.mp4');
            if (!VeoVideoGenerator::descargar($iaConfig->gemini_api_key, $estado['videoUri'], $rutaBruta)) {
                $escenas[$i]['estado'] = 'error';
                $escenas[$i]['error'] = 'No se pudo descargar la escena generada por Veo.';
                $huboError = true;
                continue;
            }

            $escenas[$i]['estado'] = 'lista';
            $escenas[$i]['video_bruto_path'] = $rutaBruta;
        }

        if ($huboError) {
            $mensajes = collect($escenas)->filter(fn ($e) => $e['estado'] === 'error')->pluck('error')->implode(' / ');
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => $mensajes, 'escenas' => $escenas]);
            return;
        }

        $todasListas = collect($escenas)->every(fn ($e) => $e['estado'] === 'lista');
        if (!$todasListas) {
            $video->update(['escenas' => $escenas]); // guarda el progreso parcial, se sigue en el próximo tick
            $this->info("Video #{$video->id}: " . collect($escenas)->where('estado', 'lista')->count() . '/' . count($escenas) . ' escenas listas.');
            return;
        }

        $rutasEnOrden = collect($escenas)->sortBy('orden')->pluck('video_bruto_path')->all();
        $this->componerYFinalizar($video, $rutasEnOrden);
    }

    /** Une (si hay más de un clip), monta el overlay de texto+logo, y marca la pieza lista. */
    private function componerYFinalizar(PublicidadVideoIa $video, array $rutasBrutas): void
    {
        $aliado = $video->aliado;

        if (count($rutasBrutas) > 1) {
            $rutaCombinada = $this->rutaTempPublica('combinado_' . Str::random(20) . '.mp4');
            $union = VideoOverlayFfmpeg::concatenar($rutasBrutas, $rutaCombinada);

            foreach ($rutasBrutas as $ruta) {
                @unlink($ruta);
            }

            if (!$union['ok']) {
                $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => $union['error']]);
                return;
            }

            $rutaBrutaFinal = $rutaCombinada;
        } else {
            $rutaBrutaFinal = $rutasBrutas[0];
        }

        $rutaFinalRelativa  = 'publicidad/video_ia/' . Str::random(20) . '.mp4';
        $rutaPosterRelativa = 'publicidad/video_ia/' . Str::random(20) . '.jpg';

        $overlay = VideoOverlayFfmpeg::aplicar(
            $rutaBrutaFinal,
            $video->frases_texto ?? [],
            $aliado?->logo_marca_claro,
            $aliado?->logo_marca_recorte,
            $aliado?->color_primario,
            Storage::disk('public')->path($rutaFinalRelativa),
            Storage::disk('public')->path($rutaPosterRelativa)
        );

        @unlink($rutaBrutaFinal);

        if (!$overlay['ok']) {
            $video->update(['estado' => PublicidadVideoIa::ESTADO_ERROR, 'error_mensaje' => $overlay['error']]);
            return;
        }

        // Cierre de marca al final, con transición. Solo para las piezas del piloto: un video
        // generado a mano desde el panel puede tener otro destino (una historia, un envío
        // suelto) donde el cierre corporativo no viene al caso.
        if ($video->autopilot_payload) {
            $rutaFinalRelativa = $this->pegarCierreDeMarca($video, $rutaFinalRelativa);
        }

        $video->update([
            'estado'             => PublicidadVideoIa::ESTADO_LISTA,
            'video_path'         => $rutaFinalRelativa,
            'imagen_poster_path' => $overlay['posterPath'] ? $rutaPosterRelativa : null,
        ]);

        $this->info("Video #{$video->id} listo.");

        $this->publicarDesdeAutopilot($video->fresh());
    }

    /**
     * Le pega el cierre de marca a la pieza. Si algo falla —falta el clip de fondo, falla
     * FFmpeg— se devuelve la pieza SIN cierre en vez de romper: es preferible publicar un
     * Reel sin la cola corporativa a perder el video que ya se pagó.
     *
     * @return string Ruta relativa del video final (con cierre si se pudo, sin él si no).
     */
    private function pegarCierreDeMarca(PublicidadVideoIa $video, string $rutaContenidoRelativa): string
    {
        $aliado = $video->aliado;
        if (!$aliado) {
            return $rutaContenidoRelativa;
        }

        $config = \App\Models\AutopilotConfig::paraAliado($aliado->id);
        if (!$config->cierre_activo) {
            return $rutaContenidoRelativa;
        }

        // Sin variante explícita, obtener() alterna sola por día: así el mismo seguidor no
        // ve el mismo cierre en piezas consecutivas.
        $cierre = CierreMarcaVideo::obtener(
            $aliado,
            (int) ($config->cierre_anios ?: 12),
            $config->cierre_ciudad ?: 'Cali'
        );

        if (!$cierre['ok']) {
            $this->warn("Video #{$video->id}: sin cierre de marca ({$cierre['error']}).");
            return $rutaContenidoRelativa;
        }

        $rutaConCierre = 'publicidad/video_ia/' . Str::random(20) . '.mp4';

        $union = VideoOverlayFfmpeg::pegarCierre(
            Storage::disk('public')->path($rutaContenidoRelativa),
            $cierre['path'],
            Storage::disk('public')->path($rutaConCierre)
        );

        if (!$union['ok']) {
            $this->warn("Video #{$video->id}: no se pudo pegar el cierre ({$union['error']}).");
            return $rutaContenidoRelativa;
        }

        // El clip sin cierre ya no se usa: se borra para no acumular archivos huérfanos.
        Storage::disk('public')->delete($rutaContenidoRelativa);

        $this->info("Video #{$video->id}: cierre de marca pegado.");

        return $rutaConCierre;
    }

    /**
     * Cierra el ciclo del piloto automático: el Reel se lanzó hace unos minutos sin poder
     * crear su Publicacion (Veo es asíncrono), así que se crea ahora con el concepto que
     * quedó guardado en `autopilot_payload`.
     *
     * Los videos generados a mano desde el panel no traen payload y no se tocan: esos los
     * publica el admin cuando quiere.
     */
    private function publicarDesdeAutopilot(PublicidadVideoIa $video): void
    {
        $payload = $video->autopilot_payload;
        if (!$payload) {
            return;
        }

        $esAuto = ($payload['modo'] ?? null) === AutopilotConfig::MODO_AUTO;

        $publicacion = Publicacion::create([
            'aliado_id'          => $video->aliado_id,
            'titulo'             => $payload['titulo'] ?? 'Reel del día',
            'copy'               => $payload['copy'] ?? null,
            // El poster es el primer frame: sirve de portada en la web y de respaldo si una
            // red no acepta el video.
            'imagen_path'        => $video->imagen_poster_path ?: '',
            'tipo_pieza'         => 'video',
            'video_path'         => $video->video_path,
            'video_modelo'       => $video->modelo,
            'origen'             => 'ia_auto',
            'tema'               => $payload['tema'] ?? null,
            'costo_estimado_usd' => $video->costo_estimado_usd,
            'destinos'           => $payload['destinos'] ?? ['web'],
            'estado'             => $esAuto ? Publicacion::ESTADO_APROBADA : Publicacion::ESTADO_PENDIENTE,
            'creado_por'         => null,
        ]);

        // El payload se limpia para que un reintento del comando no publique dos veces.
        $video->update(['autopilot_payload' => null]);

        if ($esAuto) {
            PublicacionPublisher::publicar($publicacion);
            $this->info("Reel del piloto publicado como pieza #{$publicacion->id}.");
            return;
        }

        $this->info("Reel del piloto creado como pieza #{$publicacion->id} (pendiente de aprobación).");
    }

    private function rutaTempPublica(string $nombreArchivo): string
    {
        Storage::disk('public')->makeDirectory('publicidad/video_ia');
        return Storage::disk('public')->path('publicidad/video_ia/' . $nombreArchivo);
    }
}
