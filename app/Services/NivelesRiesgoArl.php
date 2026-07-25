<?php

namespace App\Services;

/**
 * Tabla oficial de niveles de riesgo ARL (I a V) con las ocupaciones típicas de cada uno.
 *
 * Sirve para dos cosas:
 *   - La IA: el cliente NO sabe su nivel de riesgo, pero sí sabe en qué trabaja. Con esto
 *     puede deducirlo de la ocupación en vez de preguntar algo que nadie sabe responder.
 *   - La publicidad: la foto del flyer debe corresponder al riesgo que se está cotizando
 *     (no tiene sentido anunciar "riesgo 1" con un obrero de construcción, que es riesgo 5).
 */
class NivelesRiesgoArl
{
    /**
     * @return array<int, array{ocupaciones: string[], resumen: string, escena: string}>
     */
    public static function tabla(): array
    {
        return [
            1 => [
                'ocupaciones' => ['oficinas', 'trabajo administrativo', 'empleadas domésticas', 'docentes', 'profesores', 'ventas de mostrador', 'call center', 'contadores', 'secretarias'],
                'resumen' => 'trabajo de oficina, docencia, servicio doméstico y ventas de mostrador',
                'escena'  => 'una persona colombiana trabajando en una oficina sencilla y real (escritorio con papeles, computador), '
                    . 'o una docente en su salón de clases, con expresión tranquila y concentrada en su labor',
            ],
            2 => [
                'ocupaciones' => ['meseros', 'asesores comerciales', 'vendedores externos', 'cocina', 'cocineros', 'panadería', 'panaderos', 'atención al cliente'],
                'resumen' => 'meseros, asesores, vendedores externos, cocina y panadería',
                'escena'  => 'un cocinero o panadero colombiano trabajando en su cocina real (harina en las manos, delantal con uso), '
                    . 'o un mesero atendiendo en un restaurante de barrio, en pleno movimiento del turno',
            ],
            3 => [
                'ocupaciones' => ['ebanistas', 'médicos', 'enfermeras', 'personal de salud', 'acabados', 'estibadores', 'bodega'],
                'resumen' => 'ebanistas, personal de salud, acabados y estibadores',
                'escena'  => 'una enfermera o auxiliar de salud colombiana en un centro médico sencillo atendiendo con calidez, '
                    . 'o un ebanista en su taller de madera lijando una pieza',
            ],
            4 => [
                'ocupaciones' => ['taxistas', 'conductores', 'soldadores', 'mensajeros', 'domiciliarios', 'motorizados', 'transporte'],
                'resumen' => 'taxistas, conductores, soldadores y mensajeros',
                'escena'  => 'un conductor o taxista colombiano al lado de su vehículo de trabajo en la calle, '
                    . 'o un mensajero en moto con su chaleco y casco, en una jornada normal de trabajo',
            ],
            5 => [
                'ocupaciones' => ['constructores', 'obras civiles', 'construcción', 'mineros', 'minería', 'alturas', 'obreros'],
                'resumen' => 'construcción, obras civiles y minería',
                'escena'  => 'un trabajador colombiano de construcción con casco, gafas y chaleco reflectivo en una obra real, '
                    . 'con polvo y desgaste auténtico en la ropa',
            ],
        ];
    }

    public static function nivel(int $nivel): ?array
    {
        return self::tabla()[$nivel] ?? null;
    }

    /** Escena para la foto publicitaria del riesgo dado (respaldo: riesgo 1). */
    public static function escena(int $nivel): string
    {
        return (self::nivel($nivel) ?? self::nivel(1))['escena'];
    }

    /** Texto de la tabla para inyectar en el prompt de la IA. */
    public static function paraPrompt(): string
    {
        $lineas = [];
        foreach (self::tabla() as $nivel => $datos) {
            $lineas[] = "  Riesgo {$nivel}: " . implode(', ', $datos['ocupaciones']);
        }

        return "NIVELES DE RIESGO ARL (dedúcelo de la ocupación del cliente, NO se lo preguntes por número):\n"
            . implode("\n", $lineas)
            . "\nSi la ocupación no aparece, ubícala por parecido con las de la lista; ante la duda entre dos "
            . "niveles, pregunta a qué se dedica exactamente en vez de adivinar (el precio cambia por nivel).";
    }
}
