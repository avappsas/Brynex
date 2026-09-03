<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\IaConfiguracionAliado;
use App\Models\PublicidadVideoIa;
use App\Models\RedSocialConfig;
use App\Services\Publicidad\VeoVideoGenerator;
use Illuminate\Console\Command;

/**
 * Video largo con GUION FIJO, escena por escena.
 *
 * El piloto diario deja que la IA invente la escena a partir de un tema, y para una pieza de
 * 8 segundos está bien. Para una de 30 no: un video largo necesita que las tres escenas
 * cuenten una historia en orden —problema, propuesta, respaldo— y eso no se puede pedir "en
 * general" y esperar que salga coherente tres veces seguidas.
 *
 * El guion de "mejorar cotización" existe porque es el ÚNICO ángulo con evidencia: la pieza
 * #60 trajo 16 conversaciones a $1.568 cada una, contra $11.404 de la peor. Le habla a quien
 * ya está decidido y comparando precio, no a quien está aprendiendo qué es una EPS.
 */
class MarketingVideoGuion extends Command
{
    protected $signature = 'marketing:video-guion
        {guion=mejorar-cotizacion : Nombre del guion a usar}
        {--aliado=brygar : Slug del aliado}
        {--listar : Solo muestra los guiones disponibles}';

    protected $description = 'Genera un video largo siguiendo un guion escena por escena';

    /**
     * Cada escena es un prompt para Veo (en inglés: entiende mejor la dirección de escena) y
     * las frases van en pantalla, no habladas — así el mensaje se lee aunque lo vean sin
     * sonido, que es como se ve la mayoría de los Reels.
     */
    private const GUIONES = [
        'mejorar-cotizacion' => [
            'tema'   => 'te mejoramos cualquier cotización que ya tengas, con verificación de tus pagos y asesoría sin costo',
            'titulo' => 'Te mejoramos cualquier cotización — y verificamos que tus pagos estén al día',
            'escenas' => [
                // 1. El problema. Una duda concreta, no un eslogan: el espectador se reconoce
                //    en la incomodidad de no saber si le están cobrando de más.
                'Vertical 9:16 cinematic shot. A Colombian independent worker in his 30s sits on the steps '
                . 'outside a workshop during a break, still in work clothes, looking at a quote on his phone '
                . 'with a doubtful frown. He scratches his head, looks away thinking. Natural afternoon light, '
                . 'handheld camera, shallow depth of field. Ambient street sounds. No text on screen, nobody speaks.',

                // 2. La propuesta. Se ve la acción concreta que queremos que copie: escribir por
                //    WhatsApp. Mostrarlo vende mejor que decirlo.
                'Vertical 9:16 cinematic shot. Close-up of the same Colombian worker typing a message on his '
                . 'phone with both thumbs, then a warm smile as he sees a reply arrive. Over his shoulder we '
                . 'see a bright office out of focus. Natural light, handheld, shallow depth of field. Ambient '
                . 'sounds only. No text on screen, nobody speaks.',

                // 3. El respaldo. La verificación es el diferencial real y hay que VERLA: una
                //    pantalla que confirma, y la cara de alivio de quien ya no duda.
                'Vertical 9:16 cinematic shot. A Colombian woman advisor in her late 20s turns a computer '
                . 'monitor toward the camera showing a clean confirmation screen with green checkmarks, '
                . 'while a client nods with visible relief. Modern bright office, warm natural light, '
                . 'medium shot. Ambient office sounds. No readable text on the screen, nobody speaks.',
            ],
            'frases' => [
                '¿Estás pagando de más?',
                'Cuéntanos qué pagas hoy',
                'Verificamos que estés al día',
            ],
            // Las frases en pantalla tienen que ser cortas para leerse; la narración puede
            // explicar. En un video de 30 segundos, tres frases de cuatro palabras dejan el
            // audio casi vacío y la pieza no "explica" nada.
            'narracion' => 'Si ya tienes una cotización de seguridad social, cuéntanos cuánto estás pagando hoy '
                . 'y te la mejoramos. Y algo que casi nadie hace: verificamos que tus aportes hayan quedado '
                . 'bien reportados, para que sepas que tu plata sí llegó. La asesoría no te cuesta nada.',
            'copy' => '¿Ya tienes una cotización de seguridad social? Cuéntanos cuánto pagas hoy y te la mejoramos. '
                . 'Además verificamos que tus aportes hayan quedado bien reportados — tú mismo lo compruebas. '
                . 'Asesoría sin costo. 📲 Escríbenos.',
        ],
    ];

    public function handle(): int
    {
        if ($this->option('listar')) {
            foreach (self::GUIONES as $nombre => $g) {
                $this->line("  {$nombre} — " . count($g['escenas']) . ' escenas (' . count($g['escenas']) * 8 . 's) — ' . mb_substr($g['titulo'], 0, 50));
            }
            return self::SUCCESS;
        }

        $guion = self::GUIONES[$this->argument('guion')] ?? null;
        if (!$guion) {
            $this->error('No existe ese guion. Ver --listar.');
            return self::FAILURE;
        }

        $aliado = Aliado::where('slug', $this->option('aliado'))->first();
        if (!$aliado) {
            $this->error('No existe ese aliado.');
            return self::FAILURE;
        }

        $iaConfig = IaConfiguracionAliado::paraAliado($aliado->id);
        if (!$iaConfig->tieneGemini()) {
            $this->error('No hay clave de Gemini configurada.');
            return self::FAILURE;
        }

        $config = AutopilotConfig::paraAliado($aliado->id);
        $modelo = $config->modeloVideo();
        $duracion = count($guion['escenas']) * 8;

        $this->line("Guion: {$this->argument('guion')} — {$duracion}s en " . count($guion['escenas']) . ' escenas');
        $this->line('Costo estimado: ~USD ' . number_format(VeoVideoGenerator::costoEstimadoUsd($modelo, $duracion), 2));

        $escenas = [];
        foreach ($guion['escenas'] as $orden => $prompt) {
            $inicio = VeoVideoGenerator::iniciar($iaConfig->gemini_api_key, $prompt, $modelo, '9:16', '720p', 8);
            if (!$inicio['ok']) {
                $this->error('Escena ' . ($orden + 1) . ': ' . $inicio['error']);
                return self::FAILURE;
            }
            $this->line('  escena ' . ($orden + 1) . ' encolada en Veo');
            $escenas[] = [
                'orden'            => $orden,
                'prompt'           => $prompt,
                'operation_name'   => $inicio['operationName'],
                'estado'           => 'generando',
                'video_bruto_path' => null,
            ];
        }

        $video = PublicidadVideoIa::create([
            'aliado_id'          => $aliado->id,
            'prompt_video'       => implode(' / ', $guion['escenas']),
            'frases_texto'       => $guion['frases'],
            // Con guion propio nadie habla: las frases van en pantalla y la narración en off
            // las lee, que es lo que hace que se entienda sin sonido y también con él.
            'narrar'             => true,
            'modelo'             => $modelo,
            'duracion_seg'       => $duracion,
            'costo_estimado_usd' => VeoVideoGenerator::costoEstimadoUsd($modelo, $duracion),
            'escenas'            => $escenas,
            'autopilot_payload'  => [
                'tema'     => $guion['tema'],
                'titulo'   => $guion['titulo'],
                'copy'     => $guion['copy'] ?? $guion['titulo'],
                'narracion' => $guion['narracion'] ?? null,
                'modo'     => AutopilotConfig::MODO_APROBAR,
                'destinos' => array_merge(['web'], RedSocialConfig::where('aliado_id', $aliado->id)->where('activo', true)->pluck('red')->all()),
            ],
            'creado_por'         => null,
        ]);

        $this->info("Video #{$video->id} en generación. La pieza se crea al terminar.");

        return self::SUCCESS;
    }
}
