<?php

namespace App\Console\Commands;

use App\Services\AlertaOperativaService;
use Illuminate\Console\Command;

/**
 * Alerta operativa por WhatsApp para scripts de backup en el servidor (cron),
 * fuera del ciclo de request de Laravel. Usa la cuenta de WhatsApp de Brygar
 * (aliado_id=2) y la plantilla aprobada "notificar_brynex" (no depende de la
 * ventana de 24h, a diferencia de un mensaje de texto libre).
 *
 * Ejecución manual: php artisan whatsapp:alerta-backup "Backup BryNex" "Falló el backup full"
 */
class WhatsappAlertaBackup extends Command
{
    protected $signature = 'whatsapp:alerta-backup {origen} {mensaje}';

    protected $description = 'Envía una alerta por WhatsApp (cuenta Brygar) cuando falla un backup en el servidor';

    public function handle(AlertaOperativaService $alertas): int
    {
        $origen = $this->argument('origen');
        $mensaje = $this->argument('mensaje');

        if (! $alertas->enviar($origen, $mensaje)) {
            $this->error('Falló el envío de WhatsApp. El detalle quedó en el log.');

            return self::FAILURE;
        }

        $this->info('Alerta enviada a '.$alertas->numeroDestino());

        return self::SUCCESS;
    }
}
