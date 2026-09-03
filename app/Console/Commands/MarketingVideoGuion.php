<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\IaConfiguracionAliado;
use App\Models\PublicidadVideoIa;
use App\Models\RedSocialConfig;
use App\Services\Publicidad\ClipDeCapturas;
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
            'tema' => 'te mejoramos cualquier cotización que ya tengas, con verificación de tus pagos y asesoría sin costo',
            'titulo' => 'Te mejoramos cualquier cotización — y verificamos que tus pagos estén al día',
            'escenas' => [
                // 1. El problema. Una duda concreta, no un eslogan: el espectador se reconoce
                //    en la incomodidad de no saber si le están cobrando de más.
                'Vertical 9:16 cinematic shot. A Colombian independent worker in his 30s sits on the steps '
                .'outside a workshop during a break, still in work clothes, looking at a quote on his phone '
                .'with a doubtful frown. He scratches his head, looks away thinking. Natural afternoon light, '
                .'handheld camera, shallow depth of field. Ambient street sounds. No text on screen, nobody speaks.',

                // 2. La propuesta. Se ve la acción concreta que queremos que copie: escribir por
                //    WhatsApp. Mostrarlo vende mejor que decirlo.
                'Vertical 9:16 cinematic shot. Close-up of the same Colombian worker typing a message on his '
                .'phone with both thumbs, then a warm smile as he sees a reply arrive. Over his shoulder we '
                .'see a bright office out of focus. Natural light, handheld, shallow depth of field. Ambient '
                .'sounds only. No text on screen, nobody speaks.',

                // 3. El respaldo. Sin pantallas: pedirle a Veo "una pantalla con confirmaciones"
                //    y a la vez "que no se lea texto" es contradictorio, y devolvió la escena
                //    vacía. El alivio se actúa, no se muestra en un monitor.
                'Vertical 9:16 cinematic shot. A Colombian woman advisor in her late 20s sits beside a client '
                .'at a desk in a bright modern office, both looking at a printed document she points to. '
                .'The client nods slowly and smiles with relief, then they shake hands. Warm natural light, '
                .'medium shot, shallow depth of field. Ambient office sounds. Nobody speaks.',
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
                .'y te la mejoramos. Y algo que casi nadie hace: verificamos que tus aportes hayan quedado '
                .'bien reportados, para que sepas que tu plata sí llegó. La asesoría no te cuesta nada.',
            'copy' => '¿Ya tienes una cotización de seguridad social? Cuéntanos cuánto pagas hoy y te la mejoramos. '
                .'Además verificamos que tus aportes hayan quedado bien reportados — tú mismo lo compruebas. '
                .'Asesoría sin costo. 📲 Escríbenos.',
        ],
        // Misma estructura que el anterior, con dos cambios pedidos: un oficio más cotidiano
        // —un vendedor de tienda de barrio en vez de alguien de taller— y el diferencial de
        // "mejoramos cualquier cotización" dicho también en voz alta, no solo en pantalla.
        // Todas las escenas describen el audio como "Ambient sounds only": es la fórmula que
        // pasó el filtro de Veo, mientras que "ambient office sounds" lo hizo fallar dos veces.
        'mejorar-cotizacion-2' => [
            'tema' => 'te mejoramos cualquier cotización que ya tengas, con verificación de tus pagos y asesoría sin costo',
            'titulo' => 'Mejoramos cualquier cotización — y verificamos que tus pagos estén al día',
            'escenas' => [
                'Vertical 9:16 cinematic shot. A Colombian man in his 40s, a small neighborhood shop owner in '
                .'a simple polo shirt, stands behind the counter of his corner store during a quiet moment, '
                .'looking at a quote on his phone with a doubtful frown. Shelves with products behind him. '
                .'Natural daylight, handheld camera, shallow depth of field. Ambient sounds only. '
                .'No text on screen, nobody speaks.',

                'Vertical 9:16 cinematic shot. Close-up of the same Colombian shop owner typing a message on '
                .'his phone with both thumbs, then a warm smile as he sees a reply arrive. The store shelves '
                .'are out of focus behind him. Natural daylight, handheld, shallow depth of field. '
                .'Ambient sounds only. No text on screen, nobody speaks.',

                'Vertical 9:16 cinematic shot. A Colombian woman advisor in her late 20s sits beside a client '
                .'at a desk in a bright modern office, both looking at a printed document she points to. '
                .'The client nods slowly and smiles with relief. Warm natural light, medium shot, '
                .'shallow depth of field. Ambient sounds only. No text on screen, nobody speaks.',
            ],
            'frases' => [
                '¿Estás pagando de más?',
                'Cuéntanos qué pagas hoy',
                'Mejoramos cualquier cotización',
            ],
            'narracion' => 'Si ya tienes una cotización de seguridad social, cuéntanos cuánto estás pagando hoy: '
                .'te mejoramos cualquier cotización. Y algo que casi nadie hace: verificamos que tus aportes '
                .'hayan quedado bien reportados y que estés al día, para que sepas que tu plata sí llegó. '
                .'La asesoría no te cuesta nada.',
            'copy' => 'Mejoramos cualquier cotización de seguridad social. Cuéntanos cuánto pagas hoy y te decimos '
                .'cómo quedarías. Además verificamos que tus aportes estén al día — tú mismo lo compruebas. '
                .'Asesoría sin costo. 📲 Escríbenos.',
        ],
        // Reclutamiento de ASESORES: otro público y otra promesa. No le vendemos seguridad
        // social a nadie — le hablamos a quien YA la vende y tiene cartera propia, y le
        // ofrecemos quitarle el papeleo. Por eso ninguna escena muestra clientes ni planes.
        'asesores' => [
            'tema' => 'reclutamiento de asesores que ya venden seguridad social: mejores comisiones y nosotros hacemos el trabajo operativo',
            'titulo' => '¿Eres asesor y ya tienes clientes? Te mejoramos tus comisiones',
            'escenas' => [
                'Vertical 9:16 cinematic shot. A Colombian man in his 30s in a casual shirt sits at a small '
                .'desk at home surrounded by stacks of paper folders and forms, rubbing his eyes with '
                .'tiredness, overwhelmed by paperwork. Warm lamp light, handheld camera, shallow depth of '
                .'field. Ambient sounds only. No text on screen, nobody speaks.',

                // La plataforma REAL, no una inventada por Veo: a un asesor con experiencia una
                // pantalla falsa lo pierde. Ver ClipDeCapturas.
                'CAPTURAS',

                'Vertical 9:16 cinematic shot. The same Colombian man walks outdoors on a city street '
                .'looking down at his phone with a calm, satisfied expression, one hand in his pocket. '
                .'Late afternoon golden light, medium shot, shallow depth of field. Ambient street sounds '
                .'only. No text on screen, nobody speaks.',

                'Vertical 9:16 cinematic shot. The same Colombian man shakes hands with a client across a '
                .'table in a bright cafe, both smiling confidently, a phone on the table between them. '
                .'Natural daylight, medium shot, shallow depth of field. Ambient sounds only. '
                .'No text on screen, nobody speaks.',
            ],
            // Capturas tomadas con el navegador a 800px de ancho: la plataforma se acomoda
            // vertical sola y entran enteras, sin panear sobre una pantalla apaisada.
            'capturas' => [
                ['archivo' => '11-modulos.png', 'modo' => 'ancho', 'zoom' => 1.1, 'arriba' => 0.25],
                ['archivo' => '12-cobros.png', 'modo' => 'ancho', 'zoom' => 1.1, 'arriba' => 0.3],
                ['archivo' => '13-tendencia.png', 'modo' => 'ancho', 'zoom' => 1.1, 'arriba' => 0.5],
            ],
            'frases' => [
                '¿Ya tienes clientes afiliados?',
                'Más por cada afiliación',
                'Más por administración mensual',
                'Comunícate ya',
            ],
            // Dice QUÉ buscamos y CUÁNTO gana, no en qué le ayudamos. Es lo único que a un
            // asesor con cartera le hace parar el dedo: lo operativo —API, WhatsApp, la app—
            // se cuenta en el copy, donde ya está leyendo porque le interesó.
            'narracion' => 'En BRYGAR buscamos asesores de seguridad social que ya tengan clientes afiliados. '
                .'Te mejoramos tus condiciones actuales: ganas más por cada afiliación y más por la administración '
                .'mensual. Con garantía, respaldo y todo verificable. '
                .'Escríbenos y te damos la información completa, para que con los clientes que ya manejas empieces '
                .'a ganar mucho más. Y si quieres, también te ayudamos a crear tu propia empresa. Comunícate ya.',
            'copy' => 'En BRYGAR buscamos asesores de seguridad social que YA tengan clientes afiliados. '
                .'Te mejoramos tus condiciones actuales: más por cada afiliación y más por la administración '
                .'mensual, con garantía, respaldo y todo verificable.'."\n\n"
                .'Y el trabajo operativo lo hacemos nosotros: afiliaciones al instante, planillas pagadas por API '
                .'(sin que subas archivos a ningún operador), cobros automáticos por WhatsApp e incapacidades. '
                .'A tus clientes los sigues desde la aplicación, y no necesitas razones sociales propias.'."\n\n"
                .'¿Prefieres dar el siguiente paso? También te ayudamos a crear tu propia empresa.'."\n\n"
                .'📲 Escríbenos y cuéntanos cuántos clientes manejas hoy.',
        ],
        // El paso siguiente para el asesor que ya tiene volumen: dejar de ser intermediario y
        // tener su propia empresa. Es otro momento de la misma persona, por eso va en un video
        // aparte en vez de amontonarlo con el de comisiones.
        'asesores-empresa' => [
            'tema' => 'ayudamos al asesor con cartera propia a crear su propia empresa de seguridad social',
            'titulo' => '¿Ya tienes suficientes clientes? Te ayudamos a montar tu propia empresa',
            'escenas' => [
                'Vertical 9:16 cinematic shot. A Colombian man in his 30s stands looking out of a window '
                .'holding a phone, thoughtful and ambitious, morning light on his face. A small desk with a '
                .'laptop behind him. Handheld camera, shallow depth of field. Ambient sounds only. '
                .'No text on screen, nobody speaks.',

                'CAPTURAS',

                'Vertical 9:16 cinematic shot. The same Colombian man sits on a sofa at home holding his '
                .'phone with both hands, scrolling calmly with a slight smile, evening lamp light behind him. '
                .'Medium close shot, shallow depth of field. Ambient sounds only. '
                .'No text on screen, nobody speaks.',

                // Reescrita: la version con "two coworkers at desks behind him" la filtro Veo tres
                // veces seguidas. Una sola persona y un encuadre simple pasan sin problema.
                'Vertical 9:16 cinematic shot. The same Colombian man, now wearing a shirt, stands in a '
                .'bright modern office next to a large window with a city view, arms relaxed, calm '
                .'confident smile. Natural daylight, medium shot, shallow depth of field. '
                .'Ambient sounds only. No text on screen, nobody speaks.',
            ],
            'capturas' => [
                ['archivo' => '01-login.png', 'desde' => 0.5, 'hasta' => 0.5, 'zoom' => 1.15],
                ['archivo' => '11-modulos.png', 'modo' => 'ancho', 'zoom' => 1.1, 'arriba' => 0.25],
                ['archivo' => '13-tendencia.png', 'modo' => 'ancho', 'zoom' => 1.1, 'arriba' => 0.5],
            ],
            'frases' => [
                '¿Ya manejas tu propia cartera?',
                'Te montamos la plataforma',
                'Todo desde el celular',
                'Tu propia empresa',
            ],
            'narracion' => 'Si ya tienes tus propios clientes de seguridad social, el siguiente paso es tener tu '
                .'propia empresa. Nosotros te ayudamos a montarla y te damos la plataforma de BRYNEX punto co: '
                .'afiliaciones al instante, planillas pagadas por API sin subir archivos, cobros automáticos por '
                .'WhatsApp e incapacidades. Y tus clientes los sigues desde la aplicación. '
                .'Escríbenos y cuéntanos cuántos manejas hoy.',
            'copy' => '¿Eres asesor de seguridad social y ya tienes tu propia cartera? Te ayudamos a montar tu propia '
                .'empresa y te damos la plataforma de BRYNEX.co para manejarla: afiliaciones al instante, planillas '
                .'pagadas por API sin subir archivos, cobros automáticos por WhatsApp e incapacidades, y una '
                .'aplicación para seguir a tus clientes. 📲 Escríbenos y cuéntanos cuántos clientes manejas.',
        ],
    ];

    public function handle(): int
    {
        if ($this->option('listar')) {
            foreach (self::GUIONES as $nombre => $g) {
                $this->line("  {$nombre} — ".count($g['escenas']).' escenas ('.count($g['escenas']) * 8 .'s) — '.mb_substr($g['titulo'], 0, 50));
            }

            return self::SUCCESS;
        }

        $guion = self::GUIONES[$this->argument('guion')] ?? null;
        if (! $guion) {
            $this->error('No existe ese guion. Ver --listar.');

            return self::FAILURE;
        }

        $aliado = Aliado::where('slug', $this->option('aliado'))->first();
        if (! $aliado) {
            $this->error('No existe ese aliado.');

            return self::FAILURE;
        }

        $iaConfig = IaConfiguracionAliado::paraAliado($aliado->id);
        if (! $iaConfig->tieneGemini()) {
            $this->error('No hay clave de Gemini configurada.');

            return self::FAILURE;
        }

        $config = AutopilotConfig::paraAliado($aliado->id);
        $modelo = $config->modeloVideo();
        $duracion = count($guion['escenas']) * 8;
        $escenasVeo = count(array_filter($guion['escenas'], fn ($e) => $e !== 'CAPTURAS'));

        $this->line("Guion: {$this->argument('guion')} — {$duracion}s en ".count($guion['escenas']).' escenas');
        $this->line('Costo estimado: ~USD '.number_format(VeoVideoGenerator::costoEstimadoUsd($modelo, $escenasVeo * 8), 2)
            .' ('.$escenasVeo.' de '.count($guion['escenas']).' escenas van a Veo)');

        $escenas = [];
        foreach ($guion['escenas'] as $orden => $prompt) {
            // La escena marcada CAPTURAS no la genera Veo: se arma con pantallazos reales de la
            // plataforma. Veo no sabe escribir texto legible, y a un asesor con experiencia una
            // interfaz inventada lo pierde en dos segundos.
            if ($prompt === 'CAPTURAS') {
                $clip = $this->clipDeCapturas($guion['capturas'] ?? []);
                if (! $clip['ok']) {
                    $this->error('Escena '.($orden + 1).': '.$clip['error']);

                    return self::FAILURE;
                }
                $this->line('  escena '.($orden + 1).' armada con capturas reales');
                $escenas[] = [
                    'orden' => $orden,
                    'prompt' => 'Capturas de la plataforma',
                    'operation_name' => null,
                    'estado' => 'lista',
                    'video_bruto_path' => $clip['path'],
                ];

                continue;
            }

            $inicio = VeoVideoGenerator::iniciar($iaConfig->gemini_api_key, $prompt, $modelo, '9:16', '720p', 8);
            if (! $inicio['ok']) {
                $this->error('Escena '.($orden + 1).': '.$inicio['error']);

                return self::FAILURE;
            }
            $this->line('  escena '.($orden + 1).' encolada en Veo');
            $escenas[] = [
                'orden' => $orden,
                'prompt' => $prompt,
                'operation_name' => $inicio['operationName'],
                'estado' => 'generando',
                'video_bruto_path' => null,
            ];
        }

        $video = PublicidadVideoIa::create([
            'aliado_id' => $aliado->id,
            'prompt_video' => implode(' / ', $guion['escenas']),
            'frases_texto' => $guion['frases'],
            // Con guion propio nadie habla: las frases van en pantalla y la narración en off
            // las lee, que es lo que hace que se entienda sin sonido y también con él.
            'narrar' => true,
            'modelo' => $modelo,
            'duracion_seg' => $duracion,
            'costo_estimado_usd' => VeoVideoGenerator::costoEstimadoUsd($modelo, $escenasVeo * 8),
            'escenas' => $escenas,
            'autopilot_payload' => [
                'tema' => $guion['tema'],
                'titulo' => $guion['titulo'],
                'copy' => $guion['copy'] ?? $guion['titulo'],
                'narracion' => $guion['narracion'] ?? null,
                'modo' => AutopilotConfig::MODO_APROBAR,
                'destinos' => array_merge(['web'], RedSocialConfig::where('aliado_id', $aliado->id)->where('activo', true)->pluck('red')->all()),
            ],
            'creado_por' => null,
        ]);

        $this->info("Video #{$video->id} en generación. La pieza se crea al terminar.");

        return self::SUCCESS;
    }

    /**
     * Arma el clip de 8s con las capturas de la plataforma.
     *
     * @param  string[]  $nombres  Archivos dentro de storage/app/public/publicidad/capturas.
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    private function clipDeCapturas(array $nombres): array
    {
        if (empty($nombres)) {
            return ['ok' => false, 'path' => null, 'error' => 'El guion no dice qué capturas usar.'];
        }

        // Van en `resources` y no en `storage/app/public` a propósito: storage no se sincroniza
        // a producción, y el video se genera allá. En resources viajan con el mismo pull que el
        // código, sin un scp aparte que alguien tenga que acordarse de hacer.
        $base = base_path('resources/publicidad/capturas');
        $rutas = [];
        $faltan = [];
        foreach ($nombres as $entrada) {
            $entrada = is_array($entrada) ? $entrada : ['archivo' => $entrada];
            $entrada['archivo'] = $base.'/'.$entrada['archivo'];
            is_file($entrada['archivo']) ? $rutas[] = $entrada : $faltan[] = basename($entrada['archivo']);
        }

        if ($faltan) {
            return [
                'ok' => false,
                'path' => null,
                'error' => 'Faltan capturas en '.$base.': '.implode(', ', $faltan),
            ];
        }

        return ClipDeCapturas::generar($rutas, 8, ClipDeCapturas::rutaTemporal());
    }
}
