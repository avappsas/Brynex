<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Services\Finanzas\FinanzasWhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordarPrestamos extends Command
{
    /**
     * Envío automático de los mensajes de préstamo por WhatsApp.
     *
     * Dos disparos por ciclo, y solo uno de cada por corte:
     *   - 3 días ANTES de la fecha de corte → aviso previo (plantilla `aviso_previo_prestamo`)
     *   - 3 días DESPUÉS del corte, si quedó interés sin pagar → cobro (`recordatorio_prestamo`)
     *
     * Pasada esa ventana el sistema no vuelve a escribir: la gestión de un vencido
     * viejo es manual, y un automático insistiendo por su cuenta pisaría esa gestión.
     * Por eso el cobro tiene tope por arriba (`--margen`) y no dispara sobre atrasos
     * de semanas — que es justo lo que pasaría el primer día que corre el comando.
     *
     * El margen existe porque comparar contra un día exacto pierde el envío si el cron
     * no corrió esa madrugada. El control de repetición va en `aviso_previo_enviado_para`
     * / `cobro_enviado_para`, que guardan la fecha de corte atendida: pasado el corte, el
     * ciclo siguiente vuelve a disparar.
     *
     * Ejecución manual: php artisan finanzas:recordar-prestamos [--dry-run]
     */
    protected $signature = 'finanzas:recordar-prestamos
                            {--dry-run : Muestra a quién le escribiría, sin enviar nada ni marcar la base}
                            {--dias=3 : Días antes del corte para el aviso y días después para el cobro}
                            {--margen=2 : Días extra de tolerancia por si el cron no corrió; pasados, la gestión es manual}';

    protected $description = 'Envía por WhatsApp el aviso previo al corte y el cobro de lo vencido de cada préstamo';

    public function handle(FinanzasWhatsappService $whatsapp): int
    {
        $dias = max(0, (int) $this->option('dias'));
        $margen = max(0, (int) $this->option('margen'));
        $seco = $this->option('dry-run');

        $prestamos = Prestamo::whereIn('estado', ['activo', 'mora'])
            ->where('alertas_activas', true)
            ->where('saldo_actual', '>', 0)
            ->whereNotNull('telefono_deudor')
            ->get();

        $avisos = 0;
        $cobros = 0;
        $fallos = 0;

        foreach ($prestamos as $prestamo) {
            $accion = $this->accionPendiente($prestamo, $dias, $margen);

            if (! $accion) {
                continue;
            }

            [$tipo, $corteAtendido, $detalle] = $accion;

            if ($seco) {
                $this->line("[DRY] {$tipo} → #{$prestamo->id} {$prestamo->nombre_deudor} ({$detalle})");
                $tipo === 'AVISO' ? $avisos++ : $cobros++;

                continue;
            }

            $res = $whatsapp->enviarRecordatorioPrestamo($prestamo);

            if (! $res['ok']) {
                $fallos++;
                $this->error("#{$prestamo->id} {$prestamo->nombre_deudor}: {$res['message']}");

                continue;
            }

            // Se marca solo tras el envío confirmado: un fallo de Meta se reintenta mañana.
            $prestamo->update([
                $tipo === 'AVISO' ? 'aviso_previo_enviado_para' : 'cobro_enviado_para' => $corteAtendido,
            ]);

            $tipo === 'AVISO' ? $avisos++ : $cobros++;
            $this->info("{$tipo} → #{$prestamo->id} {$prestamo->nombre_deudor} ({$detalle})");
        }

        $prefijo = $seco ? '[DRY] ' : '';
        $this->info("{$prefijo}Listo. Avisos previos: {$avisos}. Cobros: {$cobros}. Fallos: {$fallos}.");

        return self::SUCCESS;
    }

    /**
     * Decide qué mensaje le toca hoy a un préstamo, si es que le toca alguno.
     *
     * El cobro tiene prioridad: un préstamo con interés vencido no debe recibir un
     * "faltan 3 días" del ciclo siguiente mientras siga debiendo el anterior.
     *
     * @return array{0: string, 1: string, 2: string}|null [tipo, corte atendido, detalle]
     */
    private function accionPendiente(Prestamo $prestamo, int $dias, int $margen): ?array
    {
        if ($prestamo->esta_vencido) {
            // Fuera de la ventana no se escribe: antes todavía no toca, después ya es manual.
            if ($prestamo->dias_vencidos < $dias || $prestamo->dias_vencidos > $dias + $margen) {
                return null;
            }

            // `ultimo_corte` y `fecha_desembolso` llegan como string, no como Carbon.
            $corte = Carbon::parse($prestamo->ultimo_corte ?: $prestamo->fecha_desembolso)->toDateString();

            if (optional($prestamo->cobro_enviado_para)->toDateString() === $corte) {
                return null;
            }

            return ['COBRO', $corte, "vencido hace {$prestamo->dias_vencidos}d"];
        }

        $faltan = $prestamo->dias_para_corte;

        if ($faltan < 0 || $faltan > $dias) {
            return null;
        }

        $corte = $prestamo->fecha_corte->toDateString();

        if (optional($prestamo->aviso_previo_enviado_para)->toDateString() === $corte) {
            return null;
        }

        return ['AVISO', $corte, "corte en {$faltan}d"];
    }
}
