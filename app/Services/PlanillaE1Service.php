<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\OperadorPlanillaApi;

/**
 * PlanillaE1Service — reglas del flujo de dos pasos de la modalidad E-1
 * (tipo_modalidad_id = -4). Ver PilaCotizanteE1 para el cálculo de cada fila.
 *
 * El pago de esta modalidad son dos liquidaciones encadenadas contra la API
 * del operador:
 *
 *   paso 1 → planilla E con un día de pensión. Se paga.
 *   paso 2 → planilla N que corrige la anterior y agrega salud, ARL y caja.
 *
 * El orden no es una comodidad de la interfaz: el operador solo acepta una
 * corrección sobre una planilla YA PAGADA, y el archivo tiene que decir cuál
 * es y en qué fecha se pagó (campos 9 y 10 del registro tipo 1). Este servicio
 * es el que decide si el paso 2 puede salir y de dónde saca esos dos datos.
 *
 * Vive aparte del controlador por la misma razón que PilaCotizanteE1: es un
 * rodeo temporal a una validación del operador, y el día que deje de hacer
 * falta se borra el archivo sin tocar el flujo normal de liquidación.
 */
class PlanillaE1Service
{
    /**
     * ¿Este lote es de E-1?
     *
     * Se exige que TODAS las modalidades del filtro sean la -4. Mezclarla con
     * otras en el mismo archivo dejaría sin salud, en el paso 1, a gente que
     * no está en este esquema — y el paso 2 solo corrige a los que van en la
     * planilla asociada.
     */
    public static function aplica(array $tiposModalidad): bool
    {
        if ($tiposModalidad === []) {
            return false;
        }

        foreach ($tiposModalidad as $tipo) {
            if ((int) $tipo !== PilaCotizanteCalculator::TIPO_E1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Todo lo que el paso 2 necesita para poder salir, o el motivo por el que
     * todavía no puede.
     *
     * @param  array  $llave  La misma llave del updateOrCreate del paso 1,
     *                        sin el paso.
     * @return array{ok: bool, message?: string, planilla_asociada?: array{numero: string, fecha_pago: string}}
     */
    public static function contextoCorreccion(array $llave): array
    {
        $paso1 = OperadorPlanillaApi::where($llave)
            ->where('paso', 1)
            ->where('estado', 'validada')
            ->latest('id')
            ->first();

        if (! $paso1 || empty($paso1->numero_planilla)) {
            return [
                'ok' => false,
                'message' => 'Todavía no hay una primera planilla liquidada para esta tanda. '
                    .'Liquide el paso 1 antes de la corrección.',
            ];
        }

        // El pago no lo confirma la API: su servicio de estado solo dice si la
        // planilla sigue teniendo URL de pago, y nunca devuelve la fecha. La
        // fuente de verdad es la confirmación que el usuario ya registra en
        // /admin/planos, que deja el gasto con su soporte y su fecha.
        $pago = self::pagoConfirmado((int) $llave['aliado_id'], (string) $paso1->numero_planilla);

        if (! $pago) {
            return [
                'ok' => false,
                'message' => "La planilla {$paso1->numero_planilla} todavía no tiene el pago confirmado. "
                    .'El operador rechaza una corrección sobre una planilla sin pagar: '
                    .'confirme el pago y vuelva a intentarlo.',
            ];
        }

        return [
            'ok' => true,
            'planilla_asociada' => [
                'numero' => (string) $paso1->numero_planilla,
                'fecha_pago' => $pago->fecha->format('Y-m-d'),
            ],
        ];
    }

    /**
     * El gasto que registra el pago de esa planilla, si existe. Es lo que crea
     * PlanoPagoController::confirmarPago().
     */
    public static function pagoConfirmado(int $aliadoId, string $numeroPlanilla): ?Gasto
    {
        return Gasto::where('aliado_id', $aliadoId)
            ->where('tipo', 'pago_planilla')
            ->where('numero_planilla', $numeroPlanilla)
            ->latest('id')
            ->first();
    }
}
