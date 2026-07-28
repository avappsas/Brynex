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
 * desde /admin/aliados), para cuando el cliente quiere ver las opciones escritas o de un
 * vistazo en vez de solo escuchar un valor.
 *
 * Es una imagen de REFERENCIA/orientación, no la fuente de verdad: el valor que se ofrece
 * SIEMPRE sale de cotizar_plan, nunca se lee de esta imagen (puede quedar desactualizada si
 * cambian las tarifas y nadie la reemplaza). Por eso esta tool no reemplaza la cotización, la
 * complementa.
 *
 * La imagen vive en el disco público (donde la sube el admin); enviarMedia() necesita el
 * archivo en el disco local, así que se copia a un temporal y se borra después de enviarlo
 * (mismo patrón que EnviarPlanillaTool con los PDF generados).
 */
class EnviarTablaPlanesTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'enviar_tabla_planes';
    }

    public function descripcion(): string
    {
        return 'Envía por WhatsApp una imagen con la tabla de planes y precios de referencia. Úsala cuando el '
            . 'cliente pida ver los planes "escritos", "por catálogo", "una imagen", o cuando esté indeciso '
            . 'entre varias combinaciones y le ayude verlas todas juntas. NO la uses como reemplazo de cotizar_plan: '
            . 'los valores exactos que le des al cliente siempre deben salir de esa tool, esta imagen es solo '
            . 'apoyo visual y puede tener precios de referencia, no el valor final. Si el aliado no tiene esta '
            . 'imagen configurada, te devuelve disponible=false — en ese caso sigue solo con texto, no lo menciones.';
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
                    'contenido'       => 'Estas son nuestras opciones 👇 (imagen: tabla de planes)',
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
}
