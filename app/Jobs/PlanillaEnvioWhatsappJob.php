<?php

namespace App\Jobs;

use App\Models\{
    PlanillaEnvioWhatsapp, PlanillaEnvioWhatsappDetalle,
    WhatsappConfig, WhatsappConversacion, WhatsappMensaje, WhatsappPlantilla, Plano
};
use App\Services\WhatsappApiService;
use App\Services\PlanillaFormularioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PlanillaEnvioWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 3600;

    public function __construct(protected int $envioId) {}

    public function handle(WhatsappApiService $apiService, PlanillaFormularioService $formularioService): void
    {
        $envio = PlanillaEnvioWhatsapp::with(['detalles'])->find($this->envioId);

        if (!$envio || $envio->estado === 'completado') {
            return;
        }

        $envio->update(['estado' => 'procesando']);

        $config = WhatsappConfig::paraAliado($envio->aliado_id);

        if (!$config->credencialesCompletas()) {
            $envio->update(['estado' => 'fallido']);
            Log::error("Envío de planillas WhatsApp #{$this->envioId}: sin credenciales de WhatsApp configuradas");
            return;
        }

        // Obtener la plantilla asignada en la configuración
        $plantillaId = $config->planilla_envio_plantilla_id ?? $envio->plantilla_id;
        $plantilla = WhatsappPlantilla::find($plantillaId);

        if (!$plantilla) {
            $envio->update(['estado' => 'fallido']);
            Log::error("Envío de planillas WhatsApp #{$this->envioId}: Plantilla de envío de planillas no configurada");
            return;
        }

        $creds = $config->credencialesEfectivas();

        // Procesar pendientes y fallidos
        $detalles = $envio->detalles()
            ->whereIn('estado', ['pendiente', 'fallido'])
            ->get();

        $enviados  = $envio->total_enviados;
        $fallidos  = $envio->total_fallidos;
        $omitidos  = $envio->total_omitidos;

        $contadorBatch = 0;

        foreach ($detalles as $detalle) {
            try {
                // 1. Cargar el plano
                $plano = Plano::find($detalle->plano_id);
                if (!$plano) {
                    $detalle->update([
                        'estado' => 'fallido',
                        'error'  => 'El registro del plano no existe en la base de datos'
                    ]);
                    $fallidos++;
                    continue;
                }

                // 2. Validar operador autorizado
                $codigoOp = strtoupper($detalle->operador_nombre ?? '');
                // Buscar el código del operador por nombre
                $operadorReg = null;
                if ($detalle->operador_nombre) {
                    $operadorReg = DB::table('operadores_planilla')
                        ->where('nombre', $detalle->operador_nombre)
                        ->first(['id', 'codigo']);
                }

                $operadorId = null;
                if ($operadorReg) {
                    $operadorId = $operadorReg->id;
                    $codigoOp = strtoupper($operadorReg->codigo ?? '');
                }

                // Si el operador no está autorizado, omitir este detalle
                if (!in_array($codigoOp, \App\Services\PlanillaWhatsappService::OPERADORES_AUTORIZADOS)) {
                    $detalle->update([
                        'estado' => 'omitido',
                        'error'  => 'Operador sin plantilla PDF autorizada para envío por WhatsApp',
                    ]);
                    $omitidos++;
                    continue;
                }

                // 3. Generar el PDF
                $pdfContenido = $formularioService->generar($plano, $operadorId);

                // 4. Nombre del PDF: período de servicio (mes del lote = mes del filtro UI)
                $nombreCompleto = trim("{$plano->primer_nombre} {$plano->segundo_nombre} {$plano->primer_ape} {$plano->segundo_ape}");
                $nombreArchivo = \App\Services\PlanillaWhatsappService::generarNombreArchivoPdf(
                    $nombreCompleto,
                    $envio->mes,
                    $envio->anio
                );
                $pathTemporal = "temp_planillas/{$envio->aliado_id}/" . uniqid() . '_' . $nombreArchivo;
                Storage::disk('local')->put($pathTemporal, $pdfContenido);

                $rutaAbsolutaTemp = Storage::disk('local')->path($pathTemporal);

                // 5. Subir a Meta para obtener media_id
                $mediaId = $apiService->subirMedia($pathTemporal, 'application/pdf', $creds);

                // Borrar archivo temporal inmediatamente
                Storage::disk('local')->delete($pathTemporal);

                if (!$mediaId) {
                    $detalle->update([
                        'estado' => 'fallido',
                        'error'  => 'Error al subir el certificado PDF a los servidores de Meta'
                    ]);
                    $fallidos++;
                    continue;
                }

                // 6. Preparar parámetros de la plantilla
                // variables: {{1}} nombre_cliente, {{2}} operador, {{3}} numero_planilla
                $bodyParams = [
                    $detalle->nombre_destinatario,
                    $detalle->operador_nombre ?: 'Operador',
                    $detalle->numero_planilla ?: 'N/A'
                ];

                // 7. Enviar template por WhatsApp
                $resultado = $apiService->enviarTemplateConDocumento(
                    $detalle->wa_numero,
                    $plantilla,
                    $bodyParams,
                    $mediaId,
                    $nombreArchivo,
                    $config
                );

                if ($resultado['ok']) {
                    // Obtener o crear conversación
                    $conversacion = $this->obtenerOCrearConversacion(
                        $detalle->wa_numero,
                        $envio->aliado_id,
                        $detalle->nombre_destinatario
                    );

                    // Registrar mensaje saliente
                    WhatsappMensaje::create([
                        'conversacion_id'      => $conversacion->id,
                        'aliado_id'            => $envio->aliado_id,
                        'wa_message_id'        => $resultado['wa_message_id'],
                        'direccion'            => 'saliente',
                        'tipo'                 => 'template',
                        'contenido'            => "Certificado Planilla de Seguridad Social (PDF adjunto)",
                        'plantilla_id'         => $plantilla->id,
                        'plantilla_parametros' => json_encode($bodyParams),
                        'estado'               => 'enviado',
                        'estado_at'            => now(),
                    ]);

                    $detalle->update([
                        'estado'        => 'enviado',
                        'wa_message_id' => $resultado['wa_message_id'],
                        'enviado_at'    => now(),
                        'error'         => null
                    ]);

                    $enviados++;
                } else {
                    $detalle->update([
                        'estado' => 'fallido',
                        'error'  => $resultado['error'] ?? 'Error desconocido al enviar mensaje'
                    ]);
                    $fallidos++;
                }

            } catch (\Exception $ex) {
                Log::error("WhatsApp Planilla Detalle #{$detalle->id} falló: " . $ex->getMessage());
                $detalle->update([
                    'estado' => 'fallido',
                    'error'  => $ex->getMessage()
                ]);
                $fallidos++;
            }

            // 8. Rate Limiting de Meta: pausar 0.8s cada 10 mensajes
            $contadorBatch++;
            if ($contadorBatch >= 10) {
                usleep(800000);
                $contadorBatch = 0;
            }

            // Actualizar contadores intermedios
            $envio->update([
                'total_enviados' => $enviados,
                'total_fallidos' => $fallidos,
                'total_omitidos' => $omitidos
            ]);
        }

        $envio->update(['estado' => 'completado']);
    }

    /**
     * Obtiene o crea una conversación de WhatsApp para registrar los mensajes.
     */
    protected function obtenerOCrearConversacion(string $numero, int $aliadoId, string $nombre): WhatsappConversacion
    {
        // Limpiar número
        $numeroLimpio = preg_replace('/[^0-9]/', '', $numero);
        if (strlen($numeroLimpio) === 10) {
            $numeroLimpio = '57' . $numeroLimpio;
        }

        $conversacion = WhatsappConversacion::where('aliado_id', $aliadoId)
            ->where('wa_numero', $numeroLimpio)
            ->first();

        if (!$conversacion) {
            $conversacion = WhatsappConversacion::create([
                'aliado_id'          => $aliadoId,
                'wa_numero'          => $numeroLimpio,
                'nombre_contacto'    => $nombre,
                'estado'             => 'abierta',
                'mensajes_no_leidos' => 0,
                'ultima_actividad'   => now(),
            ]);
        } else {
            $conversacion->update([
                'ultima_actividad' => now()
            ]);
        }

        return $conversacion;
    }
}
