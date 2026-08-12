<?php

namespace App\Services\Cumplimiento;

use App\Services\MoraClienteService;
use Carbon\Carbon;

/**
 * Ventana horaria en la que la Ley 2300 de 2023 permite contactar a un consumidor con
 * mensajes de cobranza o de contenido comercial/publicitario (art. 3):
 *
 *   Lunes a viernes  07:00 – 19:00
 *   Sábados          08:00 – 15:00
 *   Domingos y festivos: PROHIBIDO
 *
 * Aplica a llamadas, SMS, correo y "mensajería por aplicaciones" — WhatsApp incluido.
 * Los festivos salen de MoraClienteService::festivosColombia(), que ya implementa la Ley
 * Emiliani; no se duplica ese calendario aquí.
 *
 * La hora SIEMPRE se evalúa en America/Bogota: el consumidor está en Colombia sin importar
 * dónde corra el servidor o cómo esté configurado el reloj de la máquina.
 */
class VentanaContactoLey2300
{
    public const ZONA = 'America/Bogota';

    /** Hora de inicio/fin por día de la semana (formato 24h, minutos incluidos). */
    private const VENTANAS = [
        Carbon::MONDAY    => ['07:00', '19:00'],
        Carbon::TUESDAY   => ['07:00', '19:00'],
        Carbon::WEDNESDAY => ['07:00', '19:00'],
        Carbon::THURSDAY  => ['07:00', '19:00'],
        Carbon::FRIDAY    => ['07:00', '19:00'],
        Carbon::SATURDAY  => ['08:00', '15:00'],
        // Domingo no está: no hay ventana.
    ];

    /** ¿Se puede contactar en este momento? */
    public static function permite(?Carbon $momento = null): bool
    {
        return self::motivoBloqueo($momento) === null;
    }

    /**
     * Razón por la que NO se puede contactar, o null si sí se puede. El texto es para
     * mostrarle al usuario del panel y para dejar en el log, así que va en español llano.
     */
    public static function motivoBloqueo(?Carbon $momento = null): ?string
    {
        $t = self::enBogota($momento);

        if (self::esFestivo($t)) {
            return 'Hoy es festivo en Colombia: la Ley 2300 prohíbe el contacto comercial y de cobranza.';
        }

        if ($t->dayOfWeek === Carbon::SUNDAY) {
            return 'Es domingo: la Ley 2300 prohíbe el contacto comercial y de cobranza.';
        }

        [$desde, $hasta] = self::VENTANAS[$t->dayOfWeek];
        $inicio = $t->copy()->setTimeFromTimeString($desde);
        $fin    = $t->copy()->setTimeFromTimeString($hasta);

        if ($t->lt($inicio) || $t->gte($fin)) {
            return "Fuera del horario permitido por la Ley 2300 (hoy: {$desde} a {$hasta}). Son las {$t->format('H:i')}.";
        }

        return null;
    }

    /**
     * Primer instante a partir de $desde en que sí se puede contactar. Si ya se puede,
     * devuelve ese mismo instante — así el llamador puede usarlo sin condicionar.
     */
    public static function proximaApertura(?Carbon $desde = null): Carbon
    {
        $t = self::enBogota($desde);

        // 8 días cubre cualquier racha de festivos/domingo consecutivos en Colombia.
        for ($i = 0; $i <= 8; $i++) {
            $dia = $t->copy()->addDays($i);

            if ($dia->dayOfWeek === Carbon::SUNDAY || self::esFestivo($dia)) {
                continue;
            }

            [$desdeHora, $hastaHora] = self::VENTANAS[$dia->dayOfWeek];
            $inicio = $dia->copy()->setTimeFromTimeString($desdeHora);
            $fin    = $dia->copy()->setTimeFromTimeString($hastaHora);

            // Hoy y todavía no abre → esperar a la apertura.
            if ($i === 0 && $t->lt($inicio)) {
                return $inicio;
            }
            // Hoy y dentro de la ventana → ya se puede.
            if ($i === 0 && $t->lt($fin)) {
                return $t;
            }
            // Cualquier día siguiente → su hora de apertura.
            if ($i > 0) {
                return $inicio;
            }
        }

        // Inalcanzable en la práctica; se devuelve algo válido antes que lanzar.
        return $t->copy()->addDay()->setTimeFromTimeString('07:00');
    }

    /** Segundos que faltan para poder contactar (0 si ya se puede). */
    public static function segundosHastaApertura(?Carbon $desde = null): int
    {
        $ahora = self::enBogota($desde);

        return max(0, $ahora->diffInSeconds(self::proximaApertura($ahora), false));
    }

    private static function esFestivo(Carbon $dia): bool
    {
        return in_array(
            $dia->format('Y-m-d'),
            MoraClienteService::festivosColombia((int) $dia->format('Y')),
            true
        );
    }

    private static function enBogota(?Carbon $momento): Carbon
    {
        return ($momento ? $momento->copy() : Carbon::now())->setTimezone(self::ZONA);
    }
}
