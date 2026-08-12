<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\IaConocimiento;
use Illuminate\Console\Command;

/**
 * Siembra el conocimiento OPERATIVO del aliado en la base del asistente.
 *
 * El conocimiento que ya existía son conceptos generales de seguridad social ("qué es la ARL",
 * "qué es la PILA"): sirve para explicar, no para vender. Un lead que llega de un anuncio
 * pagado pregunta otra cosa —quiénes son ustedes, por qué ustedes, atienden en mi ciudad— y
 * sin eso el asistente contesta con generalidades.
 *
 * Las reglas de operación (tiempos de afiliación, fecha de pago, mora, retiro) las dictó el
 * aliado el 12-ago-2026 y están transcritas tal cual: el asistente se las va a decir a
 * clientes reales, así que no se adornan ni se completan con supuestos. Si cambian, se
 * cambian aquí — no se le pide al modelo que las deduzca.
 *
 * Las cuentas de pago NO se escriben nunca: varían por aliado y salen de consultar_cliente.
 *
 * Es idempotente: se reconoce por título, así que correrlo dos veces actualiza en vez de
 * duplicar.
 */
class SembrarConocimientoAliado extends Command
{
    protected $signature = 'ia:sembrar-conocimiento {aliado : Slug del aliado}';

    protected $description = 'Siembra el conocimiento operativo del aliado para el asistente de IA';

    public function handle(): int
    {
        $aliado = Aliado::where('slug', $this->argument('aliado'))->first();
        if (!$aliado) {
            $this->error('No existe ese aliado.');
            return self::FAILURE;
        }

        $config  = \App\Models\AutopilotConfig::paraAliado($aliado->id);
        $anios   = (int) ($config->cierre_anios ?: 12);
        $ciudad  = $config->cierre_ciudad ?: 'Cali';
        $wa      = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero  = $wa?->numero_telefono ?: '';
        $nombre  = $aliado->nombre;

        $entradas = [
            [
                'titulo'    => "¿Quién es {$nombre} y por qué afiliarme con ustedes?",
                'categoria' => 'sobre_nosotros',
                'contenido' => "{$nombre} es una empresa con más de {$anios} años de experiencia en seguridad social "
                    . "en Colombia, con sede en {$ciudad}. Nos encargamos de todo el trámite de afiliación a EPS, ARL, "
                    . "fondo de pensión y caja de compensación, para que la persona no tenga que hacer filas ni pelear "
                    . "con formularios. Tenemos convenio con todas las EPS y atendemos a nivel nacional. La diferencia "
                    . "está en el acompañamiento: un asesor real que responde, no un formulario que se pierde.",
            ],
            [
                'titulo'    => '¿Me mejoran una cotización que ya tengo con otra empresa?',
                'categoria' => 'comercial',
                'contenido' => "Sí. Si la persona ya tiene una cotización de otra empresa o dice que consiguió algo "
                    . "más barato, la respuesta es: \"te mejoramos cualquier cotización que tengas\". Hay que pedirle "
                    . "que mande la cotización que tiene para compararla. IMPORTANTE: nunca inventar un descuento, un "
                    . "porcentaje ni un precio para ganar la comparación — se cotiza con la herramienta de cotización "
                    . "y, si el caso necesita una condición especial, se pasa con un asesor.",
            ],
            [
                'titulo'    => '¿Atienden solo en ' . $ciudad . ' o en todo el país?',
                'categoria' => 'sobre_nosotros',
                'contenido' => "La sede está en {$ciudad}, pero la afiliación se hace a nivel nacional: no hace falta "
                    . "que la persona viva en {$ciudad} ni que venga a la oficina. Todo el trámite se puede hacer a "
                    . "distancia por WhatsApp.",
            ],
            [
                'titulo'    => '¿Tengo que ir a una oficina o hacer filas?',
                'categoria' => 'proceso',
                'contenido' => "No. El trámite lo hace {$nombre} completo: la persona manda sus datos por WhatsApp y "
                    . "nosotros nos encargamos del papeleo con la EPS, la ARL, el fondo de pensión y la caja. Ese es "
                    . "justamente el servicio: quitarle de encima el trámite.",
            ],
            [
                'titulo'    => '¿En cuánto tiempo queda activa la afiliación?',
                'categoria' => 'proceso',
                'contenido' => "La EPS demora entre 1 y 2 días hábiles, dependiendo de cuál sea. La ARL queda activa "
                    . "al día siguiente, pero el radicado sale el MISMO día — o sea que el soporte de que el trámite "
                    . "ya está en curso se entrega de una, aunque la cobertura empiece al día siguiente. Ese radicado "
                    . "es lo que suele necesitar quien lo pide para empezar a trabajar.",
            ],
            [
                'titulo'    => '¿Afiliarme nuevo es lo mismo que trasladarme de EPS?',
                'categoria' => 'proceso',
                'contenido' => "No, y confundirlos genera un reclamo seguro. Una afiliación NUEVA queda en 1 a 2 días "
                    . "hábiles. Un TRASLADO —cuando la persona ya está afiliada a una EPS y quiere cambiarse a otra— "
                    . "es otro proceso: primero se afilia con la EPS que ya tiene y después se pide el traslado, que "
                    . "tarda alrededor de 2 meses en hacerse efectivo. Antes de prometer tiempos hay que averiguar si "
                    . "la persona ya tiene EPS activa. Nunca decirle 1 a 2 días a alguien que en realidad necesita un "
                    . "traslado.",
            ],
            [
                'titulo'    => '¿Cuándo se paga cada mes?',
                'categoria' => 'pagos',
                'contenido' => "El pago va dentro de los primeros 10 días del mes. Nunca dar por tuya una fecha "
                    . "distinta ni improvisar plazos: si la persona pregunta por su valor exacto, su período o hasta "
                    . "cuándo tiene, hay que consultarlo con consultar_cliente.",
            ],
            [
                'titulo'    => '¿Cómo y dónde pago?',
                'categoria' => 'pagos',
                'contenido' => "Las cuentas de pago cambian según el aliado, así que NUNCA se dictan de memoria ni se "
                    . "inventan: se obtienen con consultar_cliente, que devuelve las cuentas vigentes junto con el "
                    . "valor del período. Dar un número de cuenta equivocado hace que el cliente mande la plata a otro "
                    . "lado, así que ante cualquier duda es preferible pasar con un asesor.",
            ],
            [
                'titulo'    => '¿Qué pasa si me atraso en el pago?',
                'categoria' => 'pagos',
                'contenido' => "Si se atrasa queda en mora PERO NO pierde el servicio: la afiliación sigue activa. "
                    . "Eso sí, mientras esté en mora puede encontrar dificultades para acceder a citas médicas o para "
                    . "reclamar una incapacidad, porque las entidades ven el aporte pendiente. Lo que no puede es "
                    . "pasarse del mes: si llega el cambio de mes sin pagar, ahí sí se retira. Conviene decirlo con "
                    . "calma y sin amenazar — sigue cubierto, pero tiene que ponerse al día antes de fin de mes.",
            ],
            [
                'titulo'    => '¿Cómo me retiro del servicio?',
                'categoria' => 'proceso',
                'contenido' => "El retiro va antes del cambio de mes. Hay dos caminos. Si a la persona le toca pagar "
                    . "el mes y no paga, se retira sola por falta de pago, sin necesidad de que avise. Y si ya sabe "
                    . "que se quiere retirar, lo mejor es que lo informe antes, para no tener que esperar a fin de mes "
                    . "y dejar el trámite en orden. No hay permanencia mínima ni penalidad por retirarse.",
            ],
            [
                'titulo'    => '¿Tengo que quedarme un tiempo mínimo? ¿Hay cláusula de permanencia?',
                'categoria' => 'comercial',
                'contenido' => "No hay cláusula de permanencia ni tiempo mínimo de estadía: la persona puede retirarse "
                    . "cuando quiera, sin penalidad ni multa por irse antes. Solo hay que avisar antes del cambio de "
                    . "mes para dejar el trámite en orden. Es un buen argumento cuando alguien duda por miedo a quedar "
                    . "amarrado: no se está firmando un contrato de permanencia, se paga el mes que se usa.",
            ],
            [
                'titulo'    => '¿Por dónde me contacto con un asesor?',
                'categoria' => 'contacto',
                'contenido' => "El canal principal es WhatsApp" . ($numero ? " al {$numero}" : '') . ". Si la persona "
                    . "ya está lista para afiliarse, quiere saber cómo pagar, o su caso necesita revisión de un "
                    . "humano, hay que pasarla con un asesor en vez de seguir respondiendo desde el asistente.",
            ],
        ];

        $nuevas = 0;
        $actualizadas = 0;

        foreach ($entradas as $e) {
            $existente = IaConocimiento::where('aliado_id', $aliado->id)
                ->where('titulo', $e['titulo'])
                ->first();

            if ($existente) {
                $existente->update(['contenido' => $e['contenido'], 'categoria' => $e['categoria']]);
                $actualizadas++;
                continue;
            }

            IaConocimiento::create([
                'aliado_id' => $aliado->id,
                'titulo'    => $e['titulo'],
                'contenido' => $e['contenido'],
                'categoria' => $e['categoria'],
                'fuente'    => 'siembra_inicial',
                'estado'    => 'aprobado',
            ]);
            $nuevas++;
        }

        $this->info("Conocimiento de {$nombre}: {$nuevas} nueva(s), {$actualizadas} actualizada(s).");

        return self::SUCCESS;
    }
}
