<?php

namespace App\Services\Publicidad;

use App\Models\Publicacion;

/**
 * Cómo se cuenta el Reel: no solo QUÉ se muestra, sino de qué forma.
 *
 * Existe porque el prompt de video pedía siempre "testimonio a cámara" —una persona mirando
 * al lente y hablando— literalmente, en todas las piezas. No era una tendencia del modelo:
 * estaba escrito así. El resultado es que todos los Reels se sienten el mismo video con
 * distinto actor, y el que sigue la página deja de mirarlos.
 *
 * La clave que lo hace posible: las frases en pantalla ya cargan el mensaje. Un video SIN
 * diálogo comunica igual, y de paso se ahorra los problemas de sincronía de labios y de
 * pronunciación de siglas, que son los que más trabajo han costado.
 *
 * El testimonio se conserva como un formato más y no se elimina: hasta ahora es el que más
 * conversaciones ha traído. Con diez conversaciones la muestra es demasiado chica para
 * afirmarlo, así que se deja compitiendo en vez de coronarlo o descartarlo.
 */
class CatalogoFormatosVideo
{
    /**
     * @return array<string, array{nombre: string, dialogo: bool, direccion: string}>
     */
    public static function formatos(): array
    {
        return [
            'testimonio' => [
                'nombre'  => 'testimonio a cámara',
                'dialogo' => true,
                'direccion' => 'Una persona mirando DIRECTO a la cámara y hablando, como si grabara un video para '
                    . 'redes sociales. Cámara en mano, cercana, estilo selfie o entrevista. Que suene a alguien '
                    . 'contando algo que le pasó, no a comercial.',
            ],
            'trabajando' => [
                'nombre'  => 'el oficio en acción',
                'dialogo' => false,
                'direccion' => 'La persona TRABAJANDO, concentrada en lo suyo, SIN hablar y sin mirar a la cámara. '
                    . 'Se ve el oficio de verdad: las manos, las herramientas, el esfuerzo, el sudor. La cámara '
                    . 'observa desde cerca, como un documental. Nada de poses ni de sonrisas a cámara.',
            ],
            'tension' => [
                'nombre'  => 'tensión y alivio',
                'dialogo' => false,
                'direccion' => 'Un momento de riesgo o de dificultad en el trabajo —un resbalón que se alcanza a '
                    . 'evitar, una mano que se lastima, un gesto de dolor en la espalda— y enseguida la calma: la '
                    . 'persona respira, sigue. SIN diálogo. La tensión se ve, no se explica.',
            ],
            'manos' => [
                'nombre'  => 'manos y detalle',
                'dialogo' => false,
                'direccion' => 'Primerísimos planos: las manos trabajando, la herramienta, el casco que se ajusta, '
                    . 'el celular en la mano. Casi no se ve la cara. Cortes cortos entre detalles. SIN diálogo. '
                    . 'La textura y el movimiento son el protagonista.',
            ],
            'siguiendo' => [
                'nombre'  => 'cámara que sigue',
                'dialogo' => false,
                'direccion' => 'La cámara SIGUE a la persona mientras camina por su lugar de trabajo —entra al '
                    . 'taller, cruza la obra, se sube a la moto— con otros trabajando alrededor. Plano en '
                    . 'movimiento continuo, sin cortes. SIN diálogo, ni mirada a cámara.',
            ],
        ];
    }

    /**
     * Formato que le toca a la próxima pieza de este aliado.
     *
     * Rota por la cantidad de piezas ya publicadas y no por el día del año: si el piloto se
     * salta un día —o se generan dos piezas seguidas— la rotación por fecha repetiría formato,
     * y es justo lo que se quiere evitar.
     *
     * @return array{clave: string, nombre: string, dialogo: bool, direccion: string}
     */
    public static function siguiente(int $aliadoId): array
    {
        $formatos = self::formatos();
        $claves = array_keys($formatos);

        $cuantas = Publicacion::where('aliado_id', $aliadoId)->whereNotNull('video_path')->count();
        $clave = $claves[$cuantas % count($claves)];

        return ['clave' => $clave] + $formatos[$clave];
    }
}
