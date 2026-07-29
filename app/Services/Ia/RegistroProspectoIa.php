<?php

namespace App\Services\Ia;

use App\Models\Cliente;
use App\Models\CotizacionProspecto;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use App\Models\WhatsappConversacion;
use Illuminate\Support\Facades\Log;

/**
 * Deja registrado en /admin/cotizaciones a quien la IA le cotizó por WhatsApp, para que los
 * interesados no se queden solo dentro del chat. El módulo de prospectos ya existía, pero
 * únicamente se alimentaba a mano: la IA cotizaba todo el día y nada de eso llegaba ahí.
 *
 * Se trabaja por CICLO, no por persona: mientras el prospecto siga abierto, una nueva
 * cotización actualiza ese mismo registro. Pero si el ciclo anterior ya se cerró —se afilió
 * y luego se retiró, o dijo que no le interesaba— una cotización nueva abre un registro
 * nuevo. Así el histórico queda intacto: se ve que ese número se convirtió una vez y hoy
 * está otra vez en negociación, en vez de sobrescribir lo que pasó antes.
 */
class RegistroProspectoIa
{
    /** Estados que dan por terminado un ciclo: lo que venga después es un ciclo nuevo. */
    private const ESTADOS_CERRADOS = ['convertido', 'no_interesado'];

    /**
     * @param array $datosCotizacion ['plan','modalidad','salario','nivel_arl','total','costo_afiliacion','es_independiente']
     */
    public static function registrar(array $contexto, PlanContrato $plan, TipoModalidad $modalidad, array $datosCotizacion): void
    {
        // Solo WhatsApp: la web tiene su propio registro de leads (PaginaLead).
        $conversacionId = $contexto['wa_conversacion_id'] ?? null;
        $aliadoId = $contexto['aliado_id'] ?? null;
        if (!$conversacionId || !$aliadoId) {
            return;
        }

        // Simulador de conversación: no ensuciar /admin/cotizaciones con prospectos falsos
        // cada vez que un entrenador prueba una frase de cotización.
        if ($contexto['modo_prueba'] ?? false) {
            return;
        }

        try {
            $conversacion = WhatsappConversacion::find($conversacionId);
            if (!$conversacion) {
                return;
            }

            $celular = preg_replace('/\D/', '', (string) $conversacion->wa_contact_id);
            if ($celular === '') {
                return;
            }
            // Los números llegan con el indicativo del país; en la ficha del asesor se
            // manejan a 10 dígitos.
            if (strlen($celular) === 12 && str_starts_with($celular, '57')) {
                $celular = substr($celular, 2);
            }

            $cliente = ClienteWhatsappResolver::resolver($conversacion, null)['cliente'] ?? null;

            // Solo el ciclo ABIERTO de este número. Uno cerrado (convertido o no interesado)
            // no se toca — si vuelve a cotizar, se abre un ciclo nuevo, dejando el anterior
            // como historial de lo que ya pasó con esa persona.
            $prospecto = CotizacionProspecto::where('aliado_id', $aliadoId)
                ->where('celular', $celular)
                ->whereNotIn('estado', self::ESTADOS_CERRADOS)
                ->orderByDesc('id')
                ->first();

            $datos = [
                'aliado_id'        => $aliadoId,
                'celular'          => $celular,
                'canal_origen'     => 'whatsapp',
                'plan_id'          => $plan->id,
                'modalidad_id'     => $modalidad->id,
                'salario_base'     => $datosCotizacion['salario'] ?? null,
                'n_arl'            => $plan->incluye_arl ? ($datosCotizacion['nivel_arl'] ?? null) : null,
                'costo_afiliacion' => $datosCotizacion['costo_afiliacion'] ?? null,
                'es_independiente' => (bool) ($datosCotizacion['es_independiente'] ?? false),
                'fecha_cotizacion' => now()->toDateString(),
                'resultado_cotizacion' => [
                    'plan'           => $plan->nombre,
                    'modalidad'      => $modalidad->nombre,
                    'valor_mensual'  => $datosCotizacion['total'] ?? null,
                    'cotizado_por'   => 'asistente_ia',
                    'cotizado_at'    => now()->toDateTimeString(),
                ],
            ];

            // Datos de identidad: solo si se conocen y el registro aún no los tiene, para no
            // sobrescribir lo que el asesor haya corregido a mano.
            if ($cliente) {
                $datos['cliente_id'] = $cliente->id;
                $datos['cedula']     = $cliente->cedula;
                $datos['tipo_doc']   = $cliente->tipo_doc;
                $datos['primer_nombre']   = $cliente->primer_nombre;
                $datos['primer_apellido'] = $cliente->primer_apellido;
            } elseif (!$prospecto || !$prospecto->nombre_completo) {
                $datos['nombre_completo'] = $conversacion->nombreMostrar();
            }

            if (!$prospecto) {
                $datos['estado'] = 'interesado';
                $datos['creado_por'] = null; // lo registró la IA, no un usuario
                CotizacionProspecto::create($datos);
                return;
            }

            // El ciclo ya estaba abierto (nunca se busca uno cerrado): una cotización nueva
            // lo reactiva como interesado sin importar si estaba en "sin respuesta" o
            // "pendiente respuesta".
            $datos['estado'] = 'interesado';
            $prospecto->update($datos);
        } catch (\Throwable $e) {
            // Registrar un prospecto nunca puede tumbar la respuesta al cliente.
            Log::warning('No se pudo registrar el prospecto de la IA', [
                'conversacion_id' => $conversacionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca el desenlace que detectó el seguimiento automático: si el cliente dijo que no le
     * interesa o que ya se afilió en otro lado, el asesor no debería seguir persiguiéndolo.
     */
    public static function marcarNoInteresado(WhatsappConversacion $conversacion, string $motivo): void
    {
        try {
            $celular = preg_replace('/\D/', '', (string) $conversacion->wa_contact_id);
            if (strlen($celular) === 12 && str_starts_with($celular, '57')) {
                $celular = substr($celular, 2);
            }

            CotizacionProspecto::where('aliado_id', $conversacion->aliado_id)
                ->where('celular', $celular)
                ->whereNotIn('estado', self::ESTADOS_CERRADOS)
                ->update([
                    'estado' => 'no_interesado',
                    'razon_no_afiliacion' => mb_substr($motivo, 0, 250),
                ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo marcar el prospecto como no interesado', [
                'conversacion_id' => $conversacion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
