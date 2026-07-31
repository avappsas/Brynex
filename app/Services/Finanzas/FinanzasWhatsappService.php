<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Models\WhatsappConfig;
use App\Models\WhatsappConversacion;
use App\Models\WhatsappMensaje;
use App\Services\WhatsappApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FinanzasWhatsappService
{
    protected WhatsappApiService $apiService;

    public function __construct(WhatsappApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Envía un recordatorio de cobro por WhatsApp a un deudor de préstamo.
     *
     * @param Prestamo $prestamo
     * @return array ['ok' => bool, 'message' => string, 'error' => string|null]
     */
    public function enviarRecordatorioPrestamo(Prestamo $prestamo): array
    {
        $celular = $prestamo->telefono_deudor;

        if (!$celular) {
            return ['ok' => false, 'message' => 'El deudor no tiene un número de teléfono configurado.'];
        }

        // Normalizar número
        $numeroNormalizado = preg_replace('/[^0-9]/', '', $celular);
        if (strlen($numeroNormalizado) === 10) {
            $numeroNormalizado = '57' . $numeroNormalizado; // Prefijo Colombia por defecto si tiene 10 dígitos
        }

        // Obtener configuración de WhatsApp para el aliado activo del usuario autenticado
        $user = Auth::user();
        $aliadoId = $user->aliado_id;
        $config = WhatsappConfig::paraAliado($aliadoId);

        if (!$config->credencialesCompletas()) {
            return ['ok' => false, 'message' => 'No hay credenciales de WhatsApp configuradas en el sistema para tu aliado.'];
        }

        $saldoFormateado = '$' . number_of_format_or_custom($prestamo->saldo_actual);
        $diasMora = $prestamo->dias_mora;

        // Calcular interés mínimo a pagar
        $interesMinimo = $prestamo->intereses_acumulados;
        if ($interesMinimo <= 0) {
            $interesMinimo = round($prestamo->saldo_actual * ($prestamo->tasa_interes_mensual / 100), 0);
        }
        $interesMinimoFormateado = '$' . number_of_format_or_custom($interesMinimo);

        // Construir mensaje de texto amigable (para el fallback de texto libre)
        $texto = "Hola *{$prestamo->nombre_deudor}*, te recordamos que tienes un saldo pendiente de *{$saldoFormateado}* con *{$diasMora}* días de vencimiento correspondiente a tu préstamo , rogamos si puedas enviar mínimo *{$interesMinimoFormateado}* de los intereses si no puedes abonar a capital . Agradecemos tu atención.";

        try {
            // Obtener o crear conversación en base de datos principal
            $conversacion = WhatsappConversacion::firstOrCreate(
                [
                    'aliado_id' => $aliadoId,
                    'wa_contact_id' => $numeroNormalizado,
                ],
                [
                    'nombre_contacto' => $prestamo->nombre_deudor,
                    'estado' => 'abierta',
                ]
            );

            if ($conversacion->estado === 'cerrada') {
                $conversacion->update(['estado' => 'abierta']);
            }

            // Buscar si hay una plantilla aprobada para recordatorio de préstamos
            $plantilla = \App\Models\WhatsappPlantilla::delAliado($aliadoId)
                ->aprobadas()
                ->where('nombre', 'recordatorio_prestamo')
                ->first();

            $tipoMensaje = 'text';
            $contenidoMensaje = $texto;

            if ($plantilla) {
                // Mapear parámetros para la plantilla de 4 variables:
                // 1: Nombre Deudor, 2: Saldo Pendiente, 3: Días Vencimiento, 4: Interés Mínimo
                $params = [
                    $prestamo->nombre_deudor,
                    $saldoFormateado,
                    (string)$diasMora,
                    $interesMinimoFormateado
                ];

                $res = $this->apiService->enviarTemplate($numeroNormalizado, $plantilla, $params, $config);
                $tipoMensaje = 'template';
                
                // Mapear contenido del mensaje para guardar en BD
                $contenidoMensaje = $plantilla->cuerpo;
                foreach ($params as $index => $paramValue) {
                    $contenidoMensaje = str_replace('{{' . ($index + 1) . '}}', $paramValue, $contenidoMensaje);
                }
            } else {
                // Intentar enviar mensaje de texto libre
                $res = $this->apiService->enviarTexto($numeroNormalizado, $texto, $config);
            }

            if (!$res['ok']) {
                $msgError = 'Meta API rechazó el mensaje.';
                if (!$plantilla) {
                    $msgError .= ' Para iniciar la conversación fuera de la ventana de 24 horas, debes crear y aprobar una plantilla llamada "recordatorio_prestamo".';
                } else {
                    $msgError .= ' Asegúrate de que las variables de la plantilla "recordatorio_prestamo" coincidan.';
                }
                
                return [
                    'ok' => false,
                    'message' => $msgError,
                    'error' => $res['error'] ?? 'Desconocido',
                ];
            }

            // Registrar mensaje en la base de datos
            WhatsappMensaje::create([
                'conversacion_id' => $conversacion->id,
                'aliado_id' => $aliadoId,
                'wa_message_id' => $res['wa_message_id'] ?? 'fin_wa_' . uniqid(),
                'direccion' => 'saliente',
                'tipo' => $tipoMensaje,
                'contenido' => $contenidoMensaje,
                'estado' => 'enviado',
                'usuario_id' => $user->id,
            ]);

            $conversacion->update(['ultimo_mensaje_at' => now()]);

            return [
                'ok' => true,
                'message' => 'Recordatorio enviado con éxito por WhatsApp.',
            ];

        } catch (\Exception $e) {
            Log::error('Error enviando recordatorio de préstamo por WhatsApp: ' . $e->getMessage());
            return [
                'ok' => false,
                'message' => 'Ocurrió un error interno al intentar procesar el envío del mensaje.',
                'error' => $e->getMessage(),
            ];
        }
    }
}

/**
 * Función auxiliar para formatear números de moneda si no existe la global
 */
if (!function_exists('number_of_format_or_custom')) {
    function number_of_format_or_custom($value) {
        return number_format($value, 0, ',', '.');
    }
}
