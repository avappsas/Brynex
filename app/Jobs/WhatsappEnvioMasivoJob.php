<?php

namespace App\Jobs;

use App\Models\{
    WhatsappConfig, WhatsappConversacion,
    WhatsappEnvioMasivo, WhatsappEnvioMasivoDetalle,
    WhatsappMensaje, WhatsappPlantilla
};
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Job para procesar envíos masivos de WhatsApp en background.
 *
 * Procesa cada destinatario del lote, respetando los rate limits de Meta:
 *  - Máximo ~80 mensajes por segundo en tier 1
 *  - El job hace micro-sleeps entre envíos para no superar el límite
 *
 * El job se puede reintentar si falla parcialmente.
 */
class WhatsappEnvioMasivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 3600; // 1 hora máximo para lotes grandes

    public function __construct(
        protected int   $envioId,
        protected array $parametrosGlobales = []
    ) {}

    public function handle(WhatsappApiService $apiService): void
    {
        $envio = WhatsappEnvioMasivo::with(['plantilla', 'detalles'])->find($this->envioId);

        if (!$envio || $envio->estado === 'completado') return;

        // Marcar como procesando
        $envio->update(['estado' => 'procesando']);

        $config = WhatsappConfig::paraAliado($envio->aliado_id);

        if (!$config->credencialesCompletas()) {
            $envio->update(['estado' => 'fallido']);
            Log::error("WhatsApp masivo #{$this->envioId}: sin credenciales configuradas");
            return;
        }

        $plantilla = $envio->plantilla;
        $enviados  = 0;
        $fallidos  = 0;
        $omitidos  = 0;

        // URL pública de imagen del header (si el aliado tiene cobro_header_imagen configurado)
        $headerImageUrl = null;
        if ($config->cobro_header_imagen) {
            $headerImageUrl = asset('storage/' . $config->cobro_header_imagen);
        }

        // Procesar solo los pendientes (permite reanudar si el job falla a mitad)
        $pendientes = $envio->detalles()->where('estado', 'pendiente')->get();

        foreach ($pendientes as $detalle) {
            try {
                // Construir parámetros para esta plantilla
                $params = $this->construirParametros($plantilla, $detalle, $this->parametrosGlobales);

                // Obtener o crear la conversación para este destinatario
                $conversacion = $this->obtenerOCrearConversacion(
                    $detalle->wa_numero,
                    $envio->aliado_id,
                    $detalle->nombre_destinatario
                );

                // Enviar el template (con imagen del header si aplica)
                $resultado = $apiService->enviarTemplate(
                    $detalle->wa_numero,
                    $plantilla,
                    $params,
                    $config,
                    $headerImageUrl
                );

                if ($resultado['ok']) {
                    // Guardar el mensaje en la conversación
                    WhatsappMensaje::create([
                        'conversacion_id'      => $conversacion->id,
                        'aliado_id'            => $envio->aliado_id,
                        'wa_message_id'        => $resultado['wa_message_id'],
                        'direccion'            => 'saliente',
                        'tipo'                 => 'template',
                        'plantilla_id'         => $plantilla->id,
                        'plantilla_parametros' => $params,
                        'estado'               => 'enviado',
                        'usuario_id'           => $envio->usuario_id,
                    ]);

                    $conversacion->update(['ultimo_mensaje_at' => now()]);

                    $detalle->update([
                        'estado'        => 'enviado',
                        'wa_message_id' => $resultado['wa_message_id'],
                    ]);

                    $enviados++;
                } else {
                    $detalle->update([
                        'estado' => 'fallido',
                        'error'  => $resultado['error'] ?? 'Error desconocido',
                    ]);
                    $fallidos++;
                }
            } catch (\Exception $e) {
                $detalle->update([
                    'estado' => 'fallido',
                    'error'  => $e->getMessage(),
                ]);
                $fallidos++;
                Log::error("WhatsApp masivo: error en destinatario {$detalle->wa_numero}", [
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limiting: ~12 envíos/segundo es seguro para tier 1
            // Para lotes grandes pausamos cada 10 mensajes
            if (($enviados + $fallidos) % 10 === 0) {
                usleep(800000); // 0.8 segundos cada 10 mensajes
            }
        }

        // Marcar el envío como completado
        $envio->update([
            'estado'          => 'completado',
            'total_enviados'  => $enviados,
            'total_fallidos'  => $fallidos,
            'total_omitidos'  => $omitidos,
        ]);

        Log::info("WhatsApp masivo #{$this->envioId} completado", [
            'enviados' => $enviados,
            'fallidos' => $fallidos,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        WhatsappEnvioMasivo::find($this->envioId)?->update(['estado' => 'fallido']);
        Log::error("WhatsApp masivo #{$this->envioId}: job fallido", ['error' => $e->getMessage()]);
    }

    private function construirParametros(
        WhatsappPlantilla $plantilla,
        WhatsappEnvioMasivoDetalle $detalle,
        array $parametrosGlobales
    ): array {
        // Si hay parámetros globales definidos por el usuario, usarlos directamente
        if (!empty($parametrosGlobales)) {
            return array_values($parametrosGlobales);
        }

        // Determinar el contrato primario para datos del cliente
        $contratoIdPrimario = $detalle->contratoIdPrimario();

        if ($contratoIdPrimario) {
            $contrato = \App\Models\Contrato::with(['cliente', 'razonSocial', 'plan', 'tipoModalidad', 'aliado'])
                ->find($contratoIdPrimario);

            if ($contrato) {
                $nombreCliente = $contrato->cliente?->nombre_completo ?? $detalle->nombre_destinatario;
                $nombreAliado  = $contrato->aliado?->nombre ?? 'BryNex';
                $plazoDias     = '10'; // Días por defecto

                // Obtener cuentas para cobro
                $cuentasCobro = \App\Models\BancoCuenta::paraCobro($contrato->aliado_id);
                $cuentasText = $cuentasCobro->map(function($bc) {
                    $nitPart = '';
                    if ($bc->nit) {
                        $trimmedNit = trim($bc->nit);
                        if (preg_match('/^(nit|cc|c\.c\.)/i', $trimmedNit)) {
                            $nitPart = " " . $trimmedNit;
                        } else {
                            $nitPart = " NIT " . $trimmedNit;
                        }
                    }
                    return "{$bc->banco} {$bc->tipo_cuenta} {$bc->numero_cuenta} - {$bc->nombre}{$nitPart}";
                })->join("\n");

                if (empty($cuentasText)) {
                    $cuentasText = 'no tiene configurada';
                }

                // Celular de soporte
                $configAliado   = \App\Models\WhatsappConfig::where('aliado_id', $contrato->aliado_id)->first();
                $celularSoporte = $configAliado?->numero_telefono ?: 'no tiene configurado';

                // Usar valor_cobro pre-calculado si está disponible (evita recalcular)
                $valorCobro = $detalle->valor_cobro !== null
                    ? (float) $detalle->valor_cobro
                    : null;

                // Fallback: calcular desde factura o estimado
                if ($valorCobro === null) {
                    $envio   = $detalle->envio;
                    $factura = \App\Models\Factura::where('aliado_id', $contrato->aliado_id)
                        ->where('contrato_id', $contrato->id)
                        ->periodo($envio->mes ?? now()->month, $envio->anio ?? now()->year)
                        ->whereIn('tipo', ['planilla', 'afiliacion'])
                        ->whereNull('deleted_at')
                        ->first();

                    $valorCobro = $factura ? $factura->total : null;

                    if ($valorCobro === null) {
                        $esIndep = $contrato->tipoModalidad?->esIndependiente() ?? false;
                        $ibc     = (float)($contrato->salario ?? 0);
                        $plan    = $contrato->plan;
                        $r100    = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100);
                        $pctSS   = $esIndep ? 28.5 : 29.5;
                        $vSS     = ($plan?->incluye_eps || $plan?->incluye_arl || $plan?->incluye_pension || $plan?->incluye_caja)
                            ? $r100($ibc * $pctSS / 100)
                            : 0;
                        $valorCobro = $vSS + (int)($contrato->administracion ?? 0) + (int)($contrato->seguro ?? 0);
                    }
                }

                $valorFormateado = '$' . number_format($valorCobro, 0, ',', '.');

                // Determinar el número de variables de la plantilla (5 o 6)
                $cantVars = $plantilla->cantidadVariables();
                if ($cantVars <= 5) {
                    // Cobro sin variable de valor
                    return [$nombreCliente, $nombreAliado, $plazoDias, $cuentasText, $celularSoporte];
                } else {
                    // Cobro con variable de valor
                    return [$nombreCliente, $nombreAliado, $plazoDias, $cuentasText, $celularSoporte, $valorFormateado];
                }
            }
        }

        // Auto-generar parámetros desde el mapa de variables de la plantilla
        $mapa = $plantilla->variables_mapa ?? [];
        if (empty($mapa)) return [];

        $params = [];
        foreach ($mapa as $campo) {
            $params[] = $this->resolverCampo($campo, $detalle);
        }

        return $params;
    }

    private function resolverCampo(string $campo, WhatsappEnvioMasivoDetalle $detalle): string
    {
        return match($campo) {
            'cliente.nombre'              => $detalle->nombre_destinatario,
            'factura.total'               => '',
            'factura.fecha_vencimiento'   => '',
            default                       => '',
        };
    }

    private function obtenerOCrearConversacion(
        string $numero,
        int $alidoId,
        string $nombre
    ): WhatsappConversacion {
        // Buscar conversación existente por aliado + número (sin importar el estado)
        $conversacion = WhatsappConversacion::where('aliado_id', $alidoId)
            ->where('wa_contact_id', $numero)
            ->orderByDesc('updated_at')
            ->first();

        if ($conversacion) {
            // Re-abrir si estaba cerrada
            if ($conversacion->estado === 'cerrada') {
                $conversacion->update(['estado' => 'abierta']);
            }
            return $conversacion;
        }

        // Crear nueva conversación vinculada al aliado
        return WhatsappConversacion::create([
            'aliado_id'             => $alidoId,
            'wa_contact_id'         => $numero,
            'nombre_contacto'       => $nombre,
            'estado'                => 'abierta',
            'ultimo_mensaje_at'     => now(),
        ]);
    }
}
