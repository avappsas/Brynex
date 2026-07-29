<?php

namespace App\Services\Ia\Tools;

use App\Models\AdresChequeo;
use App\Models\WhatsappConversacion;
use App\Services\Adres\ChequeoService;
use App\Services\Adres\EnvioCaptcha;

/**
 * Solo canal WhatsApp. Arranca el chequeo del estado de seguridad social de una
 * persona contra ADRES: en qué EPS está, si sus aportes están al día, si hay
 * meses pagados incompletos o períodos sin cotización real.
 *
 * El flujo no termina aquí. ADRES exige un código de seguridad que debe leer una
 * persona, así que la tool abre la consulta, le manda la imagen al cliente, y el
 * resultado llega cuando él responda el código (lo recoge el webhook). El modelo
 * NO debe prometer el resultado de inmediato ni pedir el código otra vez: ya se
 * le pidió con la imagen.
 *
 * Requisito no negociable: autorización explícita del titular. Sin un "sí"
 * registrado no se consulta el dato de nadie.
 */
class ChequeoSeguridadSocialTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'chequeo_seguridad_social';
    }

    public function descripcion(): string
    {
        return 'Consulta en ADRES (el sistema oficial del Estado) el estado REAL de la seguridad social de una '
            . 'persona: en qué EPS está, si los aportes de los últimos meses quedaron efectivamente radicados, '
            . 'meses pagados incompletos, períodos que figuran cubiertos sin cotización real, y meses sin aporte. '
            . 'ESTA es la herramienta para frases como "quiero revisar los pagos de mi seguridad social", '
            . '"¿me están pagando bien?", "¿estoy activo?", "¿mis aportes sí están llegando?", "reviso si me '
            . 'están cumpliendo", o cuando dude de lo que hace otro operador. NO la confundas con '
            . 'consultar_cliente: esa mira lo que el cliente nos debe A NOSOTROS, esta mira lo que el Estado '
            . 'tiene registrado sobre él. ANTES de llamarla necesitas dos cosas: su número de cédula y que haya '
            . 'dicho claramente que SÍ autoriza la consulta de sus datos, LAS DOS EN ESTE MISMO TURNO — nunca '
            . 'reutilices una autorización o cédula de mensajes anteriores para justificar una llamada nueva, '
            . 'aunque el cliente ya haya autorizado antes en la misma conversación. Si el cliente solo saluda o '
            . 'escribe algo sin relación (ej. "hola", números sueltos sin contexto), NO la llames — pregúntale '
            . 'primero qué necesita. Si te falta la cédula o la autorización fresca, pídesela primero. Después de '
            . 'llamarla, NO le pidas el código: la imagen ya se la mandé yo.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'cedula' => [
                    'type'        => 'string',
                    'description' => 'Número de cédula de la persona a consultar, solo dígitos.',
                ],
                'autorizacion' => [
                    'type'        => 'string',
                    'description' => 'Las palabras textuales con las que el cliente autorizó la consulta '
                        . '(ej: "sí, revísenlo", "dale, autorizo"). Queda como constancia.',
                ],
            ],
            'required' => ['cedula', 'autorizacion'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $conversacionId = $contexto['wa_conversacion_id'] ?? null;
        $conversacion = $conversacionId ? WhatsappConversacion::find($conversacionId) : null;

        if (!$conversacion) {
            return ['ok' => false, 'mensaje' => 'No pude identificar la conversación para hacer el chequeo.'];
        }

        $cedula = preg_replace('/\D+/', '', (string) ($input['cedula'] ?? ''));
        if (!preg_match('/^\d{4,15}$/', (string) $cedula)) {
            return [
                'ok'      => false,
                'mensaje' => 'La cédula no parece válida. Pídesela de nuevo, solo números y sin puntos.',
            ];
        }

        $autorizacion = trim((string) ($input['autorizacion'] ?? ''));
        if ($autorizacion === '') {
            return [
                'ok'      => false,
                'mensaje' => 'Falta la autorización del titular. Pregúntale explícitamente si autoriza que '
                    . 'revises su seguridad social y espera su respuesta antes de volver a llamarme.',
            ];
        }

        // El schema exige autorizacion, pero eso solo garantiza que el campo llegue con ALGO —
        // nada impide que el modelo reciclé un "sí, autorizo" de hace 50 mensajes para justificar
        // una consulta nueva sin que el cliente lo haya vuelto a confirmar ahora (visto en
        // producción: el cliente solo mandó saludos y se abrió un chequeo igual). Exigimos que la
        // autorización realmente aparezca en lo que el cliente escribió EN ESTE turno.
        $mensajeActual = mb_strtolower((string) ($contexto['mensaje_usuario'] ?? ''));
        $autorizacionFresca = $mensajeActual !== '' && (
            str_contains($mensajeActual, 'autoriz')
            || str_contains($mensajeActual, mb_strtolower($autorizacion))
        );
        if (!$autorizacionFresca) {
            return [
                'ok'      => false,
                'mensaje' => 'La autorización que reportaste no aparece en lo que el cliente escribió en este '
                    . 'turno — no reuses un "sí, autorizo" de un mensaje anterior aunque esté en el historial. '
                    . 'Pregúntale de nuevo, ahora mismo, si autoriza la consulta y espera que responda antes de '
                    . 'volver a llamarme.',
            ];
        }

        // Un chequeo a la vez por conversación: si ya hay uno esperando código,
        // abrir otro dejaría dos captchas vivos y el cliente no sabría cuál responder.
        $enCurso = AdresChequeo::where('conversacion_id', $conversacion->id)
            ->esperandoCaptcha()
            ->latest('id')
            ->first();

        if ($enCurso) {
            return [
                'ok'      => false,
                'mensaje' => 'Ya hay un chequeo esperando que el cliente responda el código que se le envió. '
                    . 'Pídele que escriba ese código; no arranques otro.',
            ];
        }

        $servicio = new ChequeoService();
        $r = $servicio->iniciar(
            aliadoId: $conversacion->aliado_id,
            cedula: $cedula,
            autorizacionTexto: $autorizacion,
            conversacionId: $conversacion->id,
        );

        if (!$r['ok']) {
            return [
                'ok'      => false,
                'mensaje' => 'No se pudo abrir la consulta en ADRES en este momento. Discúlpate, dile que lo '
                    . 'intentas más tarde y ofrécele pasar con un asesor.',
                'detalle' => $r['error'] ?? null,
            ];
        }

        $enviado = (new EnvioCaptcha())->enviar(
            $r['chequeo'],
            $r['captcha_png'],
            EnvioCaptcha::encabezadoInicial()
        );

        if (!$enviado) {
            $servicio->cancelar($r['chequeo'], 'No se pudo entregar el captcha por WhatsApp.');

            return [
                'ok'      => false,
                'mensaje' => 'No pude enviarle la imagen del código. Ofrécele pasar con un asesor.',
            ];
        }

        return [
            'ok'         => true,
            'chequeo_id' => $r['chequeo']->id,
            'mensaje'    => 'Consulta abierta y la imagen con el código de seguridad ya se le envió al cliente. '
                . 'Dile solamente que le acabas de mandar una imagen y que te escriba los números que ve; '
                . 'no le pidas la cédula otra vez ni le prometas el resultado ya mismo. Cuando responda el '
                . 'código, el sistema hace la consulta y le entrega el resultado automáticamente.',
        ];
    }
}
