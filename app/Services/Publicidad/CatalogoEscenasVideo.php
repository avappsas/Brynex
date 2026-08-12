<?php

namespace App\Services\Publicidad;

/**
 * Escenas y ángulo emocional del Reel según lo que se esté ofreciendo.
 *
 * Existe porque el prompt de video pedía siempre lo mismo —una persona hablándole a la
 * cámara en una oficina o una cocina— sin importar si el tema era ARL, pensión o caja. El
 * resultado se veía genérico: cualquier agencia de cualquier cosa. Un anuncio de ARL tiene
 * que mostrar el oficio donde el riesgo es real (el andamio, la moto, el taller), porque ahí
 * es donde el espectador se reconoce y entiende que le hablan a él.
 *
 * Los oficios son deliberadamente populares y colombianos: el cliente de este negocio es el
 * independiente que trabaja con las manos o en la calle, no un ejecutivo de oficina.
 *
 * El ángulo emocional no es adorno. Lo que mueve a alguien a afiliarse no es el dato del
 * porcentaje del IBC: es el miedo concreto (accidentarse sin cobertura, enfermarse y no
 * poder pagar) o el deseo concreto (que la familia esté bien, llegar a viejo con algo). Cada
 * bloque nombra ese resorte para que la IA escriba desde ahí y no desde el folleto.
 */
class CatalogoEscenasVideo
{
    /**
     * @return array<string, array{
     *   claves: string[], oficios: string[], emocion: string, tension: string
     * }>
     */
    public static function bloques(): array
    {
        return [
            'arl' => [
                'claves'  => ['arl', 'riesgo', 'accidente', 'laboral', 'protección laboral'],
                'oficios' => [
                    'un obrero de construcción con casco y chaleco reflectivo sobre un andamio',
                    'un mensajero domiciliario en moto abriéndose paso entre el tráfico de la ciudad',
                    'un soldador con careta levantada, chispas alrededor, en un taller',
                    'un electricista subido en una escalera conectando un tablero',
                    'un pintor de brocha gorda en una fachada, con arnés',
                    'un mecánico bajo un carro levantado en un taller de barrio',
                    'una estilista de pie todo el día atendiendo clientas en su salón',
                    'un vendedor ambulante empujando su carreta bajo el sol',
                ],
                'emocion' => 'la conciencia de que el cuerpo es la herramienta de trabajo y un accidente lo para todo',
                'tension' => 'si hoy te pasa algo trabajando, ¿quién responde? Sin ARL, nadie.',
            ],

            'eps' => [
                'claves'  => ['eps', 'salud', 'médic', 'enferm', 'familia', 'beneficiario'],
                'oficios' => [
                    'una madre cabeza de familia con su hijo pequeño en la sala de espera de un centro médico',
                    'un tendero de barrio atendiendo su negocio, cansado pero de buen ánimo',
                    'una manicurista trabajando concentrada en su local',
                    'un domiciliario joven descansando un momento en el andén con su bicicleta',
                    'una señora que vende almuerzos caseros, sirviendo en su cocina',
                    'un padre joven cargando a su bebé mientras habla a cámara',
                ],
                'emocion' => 'el alivio de saber que si el hijo o uno mismo se enferma, hay a dónde llegar',
                'tension' => 'una urgencia médica sin EPS se paga de la bolsa, y cuesta lo que uno no tiene.',
            ],

            'pension' => [
                'claves'  => ['pensión', 'pension', 'futuro', 'vejez', 'semanas', 'ahorro'],
                'oficios' => [
                    'un carpintero de unos 50 años lijando una pieza en su taller, mirada serena',
                    'un taxista veterano al volante, esperando en un semáforo, pensativo',
                    'una comerciante de plaza de mercado organizando su puesto temprano',
                    'un agricultor revisando su cultivo al atardecer',
                    'un adulto mayor jugando con su nieto en un parque',
                ],
                'emocion' => 'la pregunta incómoda de con qué se va a vivir cuando el cuerpo ya no dé para trabajar',
                'tension' => 'las semanas que no cotizas hoy son las que te van a faltar después.',
            ],

            'caja' => [
                'claves'  => ['caja', 'compensación', 'subsidio', 'recreación', 'bono'],
                'oficios' => [
                    'una familia colombiana disfrutando un día de piscina en un club familiar',
                    'unos papás llevando a sus hijos a un parque recreativo un domingo',
                    'una mamá recibiendo el mercado del subsidio, sonriendo',
                    'un joven estudiando de noche después del trabajo, con su cuaderno',
                ],
                'emocion' => 'la satisfacción de poder darle a la familia algo más que lo justo',
                'tension' => 'estás pagando caja y no estás usando ni la mitad de lo que te da.',
            ],

            'general' => [
                'claves'  => [],
                'oficios' => [
                    'un independiente trabajando en su oficio con las manos, concentrado',
                    'una emprendedora atendiendo su negocio propio',
                    'un trabajador por días terminando su jornada, satisfecho',
                    'un domiciliario en moto revisando su celular entre entregas',
                ],
                'emocion' => 'el orgullo de trabajar por cuenta propia y las ganas de hacerlo sin quedar desprotegido',
                'tension' => 'ser independiente no debería significar estar solo si algo pasa.',
            ],
        ];
    }

    /**
     * Elige el bloque que corresponde al tema. Se busca por palabra clave sobre el texto
     * normalizado; si el tema no menciona ninguna cobertura concreta (por ejemplo "años de
     * experiencia acompañando afiliados"), cae en 'general', que sirve para cualquier caso.
     *
     * @return array{oficio: string, emocion: string, tension: string, bloque: string}
     */
    public static function paraContexto(string $contexto): array
    {
        $t = mb_strtolower($contexto, 'UTF-8');
        $bloques = self::bloques();

        foreach ($bloques as $nombre => $def) {
            foreach ($def['claves'] as $clave) {
                if (str_contains($t, $clave)) {
                    return [
                        'oficio'  => $def['oficios'][array_rand($def['oficios'])],
                        'emocion' => $def['emocion'],
                        'tension' => $def['tension'],
                        'bloque'  => $nombre,
                    ];
                }
            }
        }

        $g = $bloques['general'];

        return [
            'oficio'  => $g['oficios'][array_rand($g['oficios'])],
            'emocion' => $g['emocion'],
            'tension' => $g['tension'],
            'bloque'  => 'general',
        ];
    }
}
