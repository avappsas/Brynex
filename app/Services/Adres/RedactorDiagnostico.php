<?php

namespace App\Services\Adres;

use App\Models\AdresChequeo;

/**
 * Convierte el diagnóstico en un mensaje que una persona entienda por WhatsApp.
 *
 * El valor del chequeo no es sacar el dato —eso lo hace ADRES— sino traducirlo.
 * "08/2015: 22 días compensados" no le dice nada a nadie; "en agosto de 2015 solo
 * te pagaron 22 de los 30 días" sí.
 *
 * Dos reglas de redacción que no se negocian:
 *   - Se describe lo que aparece publicado, nunca se acusa a otro operador.
 *   - Se aclara que es compensación EN SALUD, no el histórico de pensión.
 */
class RedactorDiagnostico
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public static function paraWhatsapp(AdresChequeo $chequeo): string
    {
        $d = $chequeo->diagnostico ?? [];
        $resumen = $d['resumen'] ?? null;

        if (!$resumen) {
            return "Revisé tu documento en ADRES y no aparece ningún período compensado.\n\n"
                . "Eso suele pasar cuando nunca se ha cotizado como dependiente o independiente, "
                . "o cuando la afiliación es al régimen subsidiado (Sisbén). "
                . "¿Quieres que te cuente cómo afiliarte?";
        }

        $lineas = [];
        $lineas[] = 'Listo, esto es lo que aparece hoy en ADRES 👇';
        $lineas[] = '';
        $lineas[] = '*Tu situación actual*';
        $lineas[] = "• EPS: {$resumen['eps_actual']}";
        $lineas[] = '• Estás como: ' . mb_strtolower($resumen['tipo_afiliado'] ?? 'cotizante');
        $lineas[] = '• Último mes reportado: ' . self::periodoLegible($resumen['ultimo_periodo']);
        $lineas[] = '• Meses con aporte a salud: ' . $resumen['meses_con_aporte']
            . ' (desde ' . self::periodoLegible($resumen['primer_periodo']) . ')';

        $hallazgos = $d['hallazgos'] ?? [];
        $relevantes = array_values(array_filter(
            $hallazgos,
            fn ($h) => in_array($h['severidad'], [DiagnosticoCompensados::SEV_ALTA, DiagnosticoCompensados::SEV_ATENCION], true)
        ));

        if (!$relevantes) {
            $lineas[] = '';
            $lineas[] = '✅ *No encontré problemas.* Tus aportes aparecen al día y sin meses incompletos.';
        } else {
            $lineas[] = '';
            $lineas[] = '*Lo que encontré*';
            foreach ($relevantes as $h) {
                $icono = $h['severidad'] === DiagnosticoCompensados::SEV_ALTA ? '⚠️' : '🔎';
                $lineas[] = '';
                $lineas[] = "{$icono} *{$h['titulo']}*";
                $lineas[] = self::humanizar($h);
            }
        }

        $lineas[] = '';
        $lineas[] = '_Esto es el reporte de compensación en salud de ADRES. Las semanas de pensión '
            . 'las certifica tu fondo, no ADRES. La compensación va uno o dos meses detrás del pago, '
            . 'así que los meses más recientes pueden no aparecer todavía._';

        return implode("\n", $lineas);
    }

    /**
     * Texto que acompaña el PDF y ofrece pasar con un asesor.
     *
     * No se escala solo: escalarAHumano() apaga el bot, y si lo hiciéramos por
     * nuestra cuenta el cliente respondería "sí, páseme con alguien" y nadie le
     * contestaría. Se le pregunta, y si acepta, la IA usa hablar_con_asesor.
     */
    public static function ofertaAsesor(AdresChequeo $chequeo): string
    {
        $hayHallazgos = !empty($chequeo->diagnostico['requiere_asesor']);

        $intro = $hayHallazgos
            ? "Te dejo el reporte completo de ADRES 📄\n\n"
                . 'Ahí está todo mes por mes, es el documento oficial. Como encontré cosas que vale la pena '
                . 'mirar con calma, '
            : "Te dejo el reporte completo de ADRES 📄\n\n"
                . 'Ahí está todo mes por mes, es el documento oficial. Si quieres, ';

        return $intro . '¿te comunico con un asesor para que lo revise contigo y te explique qué significa '
            . 'cada cosa? Solo dime que sí. 🙂';
    }

    /**
     * Los hallazgos vienen redactados para el expediente. Aquí se reescriben los
     * que más se le explican a un cliente, con fechas en palabras.
     */
    private static function humanizar(array $h): string
    {
        $periodos = $h['periodos'] ?? [];

        return match ($h['codigo']) {
            'meses_incompletos' => count($periodos) === 1
                ? 'En ' . self::periodoLegible($periodos[0]) . ' aparecen menos de 30 días pagados. '
                    . 'Ese mes no quedó cubierto completo.'
                : 'Hay ' . count($periodos) . ' meses donde el aporte no cubrió los 30 días: '
                    . self::listaLegible($periodos) . '.',

            'periodos_sin_cotizacion' => 'Hay ' . count($periodos) . ' '
                . (count($periodos) === 1 ? 'mes marcado' : 'meses marcados') . ' como "Estado Emergencia": '
                . self::listaLegible($periodos) . '. La propia nota de ADRES aclara que esos períodos figuran '
                . 'compensados pero no tienen un pago ni cotización detrás. Es decir: parecen cubiertos, '
                . 'pero no lo están.',

            'observacion_desconocida' => 'En ' . count($periodos) . ' '
                . (count($periodos) === 1 ? 'mes aparece una anotación' : 'meses aparecen anotaciones')
                . ' que no reconozco, así que prefiero no sacar conclusiones sobre '
                . (count($periodos) === 1 ? 'ese mes' : 'esos meses') . ' sin que los revise un asesor.',

            'posible_inactividad' => 'Tu último aporte reportado es de ' . self::periodoLegible($periodos[0] ?? '')
                . '. Como la compensación va con un mes o dos de retraso, un rezago corto es normal — '
                . 'pero este ya es largo, así que es probable que hoy no tengas la afiliación activa.',

            'periodos_faltantes' => 'Hay ' . count($periodos) . ' meses sin ningún aporte registrado. '
                . 'Puede ser porque no estabas trabajando en esas épocas, no necesariamente un error.',

            default => $h['detalle'] ?? '',
        };
    }

    /** "08/2015" -> "agosto de 2015" */
    private static function periodoLegible(?string $periodo): string
    {
        if (!$periodo || !preg_match('#^(\d{1,2})/(\d{4})$#', $periodo, $m)) {
            return (string) $periodo;
        }

        $mes = self::MESES[(int) $m[1]] ?? $m[1];

        return "{$mes} de {$m[2]}";
    }

    private static function listaLegible(array $periodos, int $tope = 6): string
    {
        $muestra = array_map([self::class, 'periodoLegible'], array_slice($periodos, 0, $tope));
        $resto   = count($periodos) - count($muestra);

        return implode(', ', $muestra) . ($resto > 0 ? " y {$resto} más" : '');
    }
}
