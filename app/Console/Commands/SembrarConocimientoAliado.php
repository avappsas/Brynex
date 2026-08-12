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
 * Solo se siembra lo que la marca YA afirma en sus propias piezas o lo verificable en el
 * sistema. Los datos de operación (tiempos de afiliación, fechas de pago, mora, retiro) NO
 * van aquí: los tiene que dictar el aliado, porque el asistente se los va a decir a clientes
 * reales y equivocarse ahí cuesta caro.
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
        $this->newLine();
        $this->warn('Falta el conocimiento de OPERACIÓN, que no se puede deducir del sistema y');
        $this->warn('el aliado tiene que dictar (el asistente se lo dirá a clientes reales):');
        foreach ([
            'Desde cuándo queda cubierta la persona una vez se afilia',
            'Cuándo se paga cada mes y hasta qué día hay plazo',
            'Qué medios de pago se aceptan',
            'Qué pasa si se atrasa (mora): cuándo se suspende y cómo se reactiva',
            'Cómo se hace el retiro y con cuánta anticipación hay que avisar',
        ] as $i => $falta) {
            $this->line('  ' . ($i + 1) . '. ' . $falta);
        }

        return self::SUCCESS;
    }
}
