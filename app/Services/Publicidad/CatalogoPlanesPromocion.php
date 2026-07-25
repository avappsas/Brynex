<?php

namespace App\Services\Publicidad;

use App\Services\CotizacionPublicaService;

/**
 * Catálogo de planes que SÍ se promocionan en redes, cada uno con su nombre comercial, su
 * gancho y la escena que debe mostrar la imagen (una distinta por plan, para que el flyer no
 * se vea siempre igual). Los parámetros de cotización van aquí para que el precio se resuelva
 * por el mismo camino que la web y la IA — incluyendo los casos especiales (Exterior, UPC,
 * Tiempo Parcial), que de otro modo se cotizarían con una modalidad que no se ofrece.
 *
 * Solo se listan combinaciones que un cliente puede contratar directo. Los planes internos
 * (Gestión ARL, el Tiempo Parcial oculto) NUNCA se promocionan.
 */
class CatalogoPlanesPromocion
{
    /**
     * @return array<string, array{
     *   nombre: string, gancho: string, servicios: string[],
     *   componentes: array{incluye_eps: bool, incluye_arl: bool, incluye_pension: bool, incluye_caja: bool},
     *   independiente: bool, desde_exterior?: bool, es_upc?: bool, tiempo_parcial_dias?: int,
     *   escena: string
     * }>
     */
    public static function todos(): array
    {
        return [
            'solo_arl' => [
                'nombre'    => 'Protección Laboral',
                'gancho'    => 'Trabaja cubierto ante cualquier accidente',
                'servicios' => ['ARL'],
                'componentes' => ['incluye_eps' => false, 'incluye_arl' => true, 'incluye_pension' => false, 'incluye_caja' => false],
                'independiente' => true,
                // La ARL es TODO el plan y su precio depende del nivel de riesgo, así que la
                // foto la define el riesgo cotizado (ver NivelesRiesgoArl): anunciar "riesgo 1"
                // con un obrero de construcción —que es riesgo 5— contradice el precio.
                'escena_por_riesgo' => true,
                'escena' => '', // se resuelve según el nivel de riesgo
            ],

            'esencial' => [
                'nombre'    => 'Plan Esencial',
                'gancho'    => 'Tu salud y tu trabajo, cubiertos',
                'servicios' => ['EPS', 'ARL'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => false, 'incluye_caja' => false],
                'independiente' => false,
                'escena' => 'una persona colombiana joven adulta saliendo de una consulta médica con gesto de alivio y tranquilidad, '
                    . 'acompañada por alguien de su familia, ambiente cálido y humano',
            ],

            'salud_beneficios' => [
                'nombre'    => 'Salud + Beneficios',
                'gancho'    => 'Suma los subsidios de la caja a tu cobertura',
                'servicios' => ['EPS', 'ARL', 'Caja'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => false, 'incluye_caja' => true],
                'independiente' => false,
                'escena' => 'una familia colombiana disfrutando un día de recreación al aire libre (parque o club familiar), '
                    . 'padres e hijos riendo juntos, luz dorada de tarde, sensación de bienestar y recompensa',
            ],

            'completo' => [
                'nombre'    => 'Plan Completo',
                'gancho'    => 'Salud, protección y tu pensión creciendo',
                'servicios' => ['EPS', 'ARL', 'Pensión'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => true, 'incluye_caja' => false],
                'independiente' => false,
                'escena' => 'una pareja colombiana de adultos revisando juntos sus planes a futuro en la mesa de su casa, '
                    . 'con expresión serena y optimista, ambiente hogareño cálido',
            ],

            'todo_incluido' => [
                'nombre'    => 'Todo Incluido',
                'gancho'    => 'La cobertura más completa para ti y los tuyos',
                'servicios' => ['EPS', 'ARL', 'Pensión', 'Caja'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => true, 'incluye_caja' => true],
                'independiente' => false,
                'escena' => 'una familia colombiana de tres generaciones (abuelos, padres e hijo) reunida y abrazada en casa, '
                    . 'sonrisas genuinas, luz cálida de hogar, sensación de respaldo total',
            ],

            'independiente' => [
                'nombre'    => 'Independiente',
                'gancho'    => 'Trabajas por tu cuenta, pero no estás solo',
                'servicios' => ['EPS', 'Pensión'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => false, 'incluye_pension' => true, 'incluye_caja' => false],
                'independiente' => true,
                'escena' => 'un emprendedor o trabajador independiente colombiano en su propio negocio (barbería, cafetería, taller '
                    . 'o local pequeño), atendiendo con orgullo y confianza, ambiente real y cercano',
            ],

            'independiente_plus' => [
                'nombre'    => 'Independiente Plus',
                'gancho'    => 'Tu cuenta propia, con los beneficios de la caja',
                'servicios' => ['EPS', 'Pensión', 'Caja'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => false, 'incluye_pension' => true, 'incluye_caja' => true],
                'independiente' => true,
                'escena' => 'una mujer colombiana emprendedora en su negocio propio junto a su hijo pequeño, '
                    . 'equilibrando trabajo y familia con una sonrisa genuina, luz natural cálida',
            ],

            'pension_exterior' => [
                'nombre'    => 'Pensión desde el Exterior',
                'gancho'    => 'Vives fuera, tu pensión sigue creciendo en Colombia',
                'servicios' => ['Pensión'],
                'componentes' => ['incluye_eps' => false, 'incluye_arl' => false, 'incluye_pension' => true, 'incluye_caja' => false],
                'independiente'  => true,
                'desde_exterior' => true,
                'escena' => 'una persona colombiana adulta en el extranjero hablando por videollamada con su familia desde su casa, '
                    . 'expresión de nostalgia feliz y conexión, luz cálida de interior por la noche',
            ],

            'solo_salud' => [
                'nombre'    => 'Solo Salud',
                'gancho'    => 'Afilia a quien no está en tu grupo familiar',
                'servicios' => ['EPS'],
                'componentes' => ['incluye_eps' => true, 'incluye_arl' => false, 'incluye_pension' => false, 'incluye_caja' => false],
                'independiente' => true,
                'es_upc'        => true,
                'escena' => 'una persona colombiana acompañando con cariño a un familiar (un sobrino adolescente o un adulto mayor) '
                    . 'en la sala de espera de un centro médico, gesto protector y cercano',
            ],

            'tiempo_parcial' => [
                'nombre'    => 'Tiempo Parcial',
                'gancho'    => 'Trabajas por días, cotizas por días',
                'servicios' => ['ARL', 'Pensión', 'Caja'],
                'componentes' => ['incluye_eps' => false, 'incluye_arl' => true, 'incluye_pension' => true, 'incluye_caja' => true],
                'independiente'       => false,
                'tiempo_parcial_dias' => 14,
                'escena' => 'una trabajadora colombiana por días (servicios generales, cocina o ventas) terminando su jornada '
                    . 'con expresión satisfecha y digna, ambiente laboral real y luz cálida',
            ],
        ];
    }

    public static function obtener(string $clave): ?array
    {
        return self::todos()[$clave] ?? null;
    }

    /**
     * Cotiza un plan del catálogo con el MISMO camino que la web y la IA (resolverPlan +
     * resolverModalidadPermitida), para que el precio del flyer nunca difiera del que se le
     * da al cliente. Devuelve null si esa combinación no se puede cotizar para este aliado.
     *
     * @return ?array{plan_nombre: string, valor_mensual: float, costo_afiliacion: float, en_promocion: bool, promocion_vence: mixed}
     */
    /**
     * Nivel de riesgo ARL con el que se calculan los precios promocionales. El valor de la ARL
     * cambia por nivel, así que el flyer SIEMPRE debe decir con cuál está cotizado; si no, el
     * precio induce a error para quien trabaja en un riesgo más alto.
     */
    public const NIVEL_ARL_PROMOCIONAL = 1;

    public static function cotizar(string $clave, int $aliadoId, int $nivelArl = self::NIVEL_ARL_PROMOCIONAL): ?array
    {
        $def = self::obtener($clave);
        if (!$def) {
            return null;
        }

        [$plan] = CotizacionPublicaService::resolverPlan($def['componentes'], $def['independiente']);
        if (!$plan) {
            return null;
        }

        $modalidad = CotizacionPublicaService::resolverModalidadPermitida(
            $plan,
            $def['independiente'],
            $def['desde_exterior'] ?? false,
            $def['tiempo_parcial_dias'] ?? null,
            false,
            $def['es_upc'] ?? false
        );
        if (!$modalidad) {
            return null;
        }

        $resultado = CotizacionPublicaService::cotizar($plan, $modalidad, $aliadoId, ['nivel_arl' => $nivelArl]);

        return [
            'plan_nombre'      => $plan->nombre,
            'modalidad'        => $modalidad->nombre,
            'valor_mensual'    => $resultado['total'],
            'costo_afiliacion' => $resultado['costo_afiliacion_sugerido'],
            'en_promocion'     => $resultado['en_promocion'],
            'promocion_vence'  => $resultado['promocion_vence'],
            // Solo tiene sentido mostrarlo si el plan realmente incluye ARL.
            'nivel_arl'        => $plan->incluye_arl ? $nivelArl : null,
        ];
    }
}
