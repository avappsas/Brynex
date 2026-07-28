<?php

namespace App\Services\Ia\Tools;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\Aliado;
use App\Models\WhatsappConfig;
use App\Models\WhatsappConversacion;
use App\Models\WhatsappMensaje;
use App\Services\WhatsappApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Solo canal WhatsApp. Envía la imagen con la tabla de planes/precios del aliado (subida
 * desde /admin/aliados) para arrancar la conversación cuando alguien pregunta por planes o
 * precios en general — es el vistazo inicial, antes de entrar al detalle con cotizar_plan.
 *
 * Es una imagen de REFERENCIA/orientación, no la fuente de verdad: el valor que se ofrece
 * SIEMPRE sale de cotizar_plan, nunca se lee de esta imagen (puede quedar desactualizada si
 * cambian las tarifas y nadie la reemplaza). Por eso esta tool no reemplaza la cotización, la
 * complementa.
 *
 * Se manda como máximo una vez cada 24h por conversación (ver HORAS_ANTES_DE_REENVIAR): si el
 * cliente ya la recibió hoy, no tiene sentido insistir con la misma imagen — vuelve a estar
 * disponible al día siguiente.
 *
 * La imagen vive en el disco público (donde la sube el admin); enviarMedia() necesita el
 * archivo en el disco local, así que se copia a un temporal y se borra después de enviarlo
 * (mismo patrón que EnviarPlanillaTool con los PDF generados).
 */
class EnviarTablaPlanesTool implements IaToolInterface
{
    /** Cada cuánto se le puede volver a mandar la imagen a la misma conversación. */
    private const HORAS_ANTES_DE_REENVIAR = 24;

    /** Marca el mensaje para poder encontrarlo después sin depender de una columna nueva. */
    private const MARCADOR_MENSAJE = 'imagen: tabla de planes';

    public function nombre(): string
    {
        return 'enviar_tabla_planes';
    }

    public function descripcion(): string
    {
        return 'Envía por WhatsApp una imagen con la tabla de planes y precios de referencia. Llámala para '
            . 'ARRANCAR la conversación cuando alguien pregunta por planes o precios en general, sin haber '
            . 'identificado todavía un plan específico (ej. "¿qué planes tienen?", "quiero saber de precios", '
            . '"cuánto cuesta afiliarme") — mándala primero y ya sobre eso ayúdalo a identificar qué quiere para '
            . 'cotizar con cotizar_plan. También úsala si el cliente pide verla explícitamente o está indeciso '
            . 'entre varias combinaciones. Se manda como máximo una vez cada 24 horas por conversación: si ya se '
            . 'envió en ese lapso, te devuelve ya_enviada=true en vez de mandarla de nuevo — en ese caso NO la '
            . 'llames otra vez ni le digas al cliente que ya se la mandaste, solo sigue ayudándolo (identifica qué '
            . 'quiere y cotiza con cotizar_plan). NO la uses como reemplazo de cotizar_plan: los valores exactos '
            . 'que le des al cliente siempre deben salir de esa tool, esta imagen es solo apoyo visual y puede '
            . 'tener precios de referencia, no el valor final. Si el aliado no tiene esta imagen configurada, te '
            . 'devuelve disponible=false — en ese caso sigue solo con texto, no lo menciones.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $waConversacionId = $contexto['wa_conversacion_id'] ?? null;
        $conversacion = $waConversacionId ? WhatsappConversacion::find($waConversacionId) : null;

        if (!$conversacion) {
            return ['disponible' => false, 'nota' => 'No pude identificar la conversación para enviar la imagen.'];
        }

        $ultimoEnvio = self::ultimoEnvioReciente($conversacion->id);
        if ($ultimoEnvio) {
            return [
                'disponible'   => true,
                'enviada'      => false,
                'ya_enviada'   => true,
                'enviada_hace' => $ultimoEnvio->diffForHumans(now(), true),
                'nota'         => 'Ya le enviaste esta imagen hace ' . $ultimoEnvio->diffForHumans(now(), true)
                    . ' (se manda máximo una vez cada 24h). NO la reenvíes ni le digas "ya te la mandé" — solo '
                    . 'sigue ayudándolo: identifica qué quiere y cotiza con cotizar_plan.',
            ];
        }

        $aliado = Aliado::find($conversacion->aliado_id);
        if (!$aliado || !$aliado->imagen_planes || !Storage::disk('public')->exists($aliado->imagen_planes)) {
            return ['disponible' => false, 'nota' => 'No hay imagen de planes configurada para este aliado.'];
        }

        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) {
            return ['disponible' => true, 'enviada' => false, 'nota' => 'No se pudo enviar por un problema técnico. Sigue ayudando solo con texto.'];
        }

        $extension = pathinfo($aliado->imagen_planes, PATHINFO_EXTENSION) ?: 'png';
        $mimeType  = Storage::disk('public')->mimeType($aliado->imagen_planes) ?: 'image/png';
        $pathTemporal = "temp_tabla_planes_ia/{$conversacion->aliado_id}/" . uniqid() . '.' . $extension;

        try {
            Storage::disk('local')->put($pathTemporal, Storage::disk('public')->get($aliado->imagen_planes));

            $resultado = app(WhatsappApiService::class)->enviarMedia(
                $conversacion->wa_contact_id,
                'image',
                $pathTemporal,
                $mimeType,
                'planes.' . $extension,
                $config,
                'Estas son nuestras opciones 👇'
            );

            if (!$resultado['ok']) {
                Log::warning('IA WhatsApp: fallo al enviar imagen de planes', ['error' => $resultado['error'] ?? null]);
                return ['disponible' => true, 'enviada' => false, 'nota' => 'No se pudo enviar la imagen por un problema técnico. Sigue ayudando solo con texto.'];
            }

            // El envío ya ocurrió: lo que sigue es secundario y no debe hacer que se reporte
            // "fallido" un envío que en realidad sí llegó al cliente.
            try {
                $mensaje = WhatsappMensaje::create([
                    'conversacion_id' => $conversacion->id,
                    'aliado_id'       => $conversacion->aliado_id,
                    'wa_message_id'   => $resultado['wa_message_id'],
                    'direccion'       => 'saliente',
                    'tipo'            => 'image',
                    'contenido'       => 'Estas son nuestras opciones 👇 (' . self::MARCADOR_MENSAJE . ')',
                    'estado'          => 'enviado',
                    'es_bot'          => true,
                ]);

                $conversacion->update(['ultimo_mensaje_at' => now()]);

                try {
                    broadcast(new WhatsappMensajeNuevo($mensaje, $conversacion));
                    broadcast(new WhatsappConversacionActualizada($conversacion));
                } catch (\Throwable $e) {
                    Log::warning('IA WhatsApp: imagen de planes enviada pero falló la difusión en vivo', ['error' => $e->getMessage()]);
                }
            } catch (\Throwable $e) {
                Log::warning('IA WhatsApp: imagen de planes enviada pero falló el registro local', ['error' => $e->getMessage()]);
            }

            return [
                'disponible' => true,
                'enviada'    => true,
                'nota'       => 'La imagen ya se envió como mensaje aparte. Solo confírmaselo brevemente al '
                    . 'cliente (ej. "Listo, ahí te dejé las opciones 📋") y sigue ayudándolo con lo que pregunte '
                    . 'sobre ellas — recuerda que el valor final siempre lo confirmas con cotizar_plan.',
            ];
        } finally {
            Storage::disk('local')->delete($pathTemporal);
        }
    }

    /**
     * Busca si ya se le mandó esta imagen a la conversación dentro de la ventana de reenvío.
     *
     * Se identifica por el marcador en `contenido` en vez de una columna dedicada: es la
     * única tool que manda esta imagen, así que el marcador es suficiente y evita una
     * migración solo para esto.
     */
    private static function ultimoEnvioReciente(int $conversacionId): ?\Carbon\Carbon
    {
        $ultimo = WhatsappMensaje::where('conversacion_id', $conversacionId)
            ->where('direccion', 'saliente')
            ->where('tipo', 'image')
            ->where('contenido', 'like', '%' . self::MARCADOR_MENSAJE . '%')
            ->latest('id')
            ->first();

        if (!$ultimo || $ultimo->created_at->lt(now()->subHours(self::HORAS_ANTES_DE_REENVIAR))) {
            return null;
        }

        return $ultimo->created_at;
    }
}
