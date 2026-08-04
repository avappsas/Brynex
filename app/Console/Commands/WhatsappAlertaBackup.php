<?php

namespace App\Console\Commands;

use App\Models\WhatsappConfig;
use App\Models\WhatsappPlantilla;
use App\Services\WhatsappApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

    private const ALIADO_ID = 2; // Brygar
    private const NUMERO_DESTINO = '3117762689';
    private const NOMBRE_PLANTILLA = 'notificar_brynex';

    public function handle(WhatsappApiService $whatsappApi): int
    {
        $origen = $this->argument('origen');
        $mensaje = $this->argument('mensaje');

        $config = WhatsappConfig::paraAliado(self::ALIADO_ID);
        if (!$config->credencialesCompletas()) {
            $this->error('No hay credenciales de WhatsApp completas para el aliado Brygar.');
            Log::error('whatsapp:alerta-backup: credenciales incompletas', ['aliado_id' => self::ALIADO_ID]);
            return self::FAILURE;
        }

        $plantilla = WhatsappPlantilla::delAliado(self::ALIADO_ID)
            ->aprobadas()
            ->where('nombre', self::NOMBRE_PLANTILLA)
            ->first();

        if (!$plantilla) {
            $this->error('No se encontró la plantilla "' . self::NOMBRE_PLANTILLA . '" aprobada para Brygar.');
            Log::error('whatsapp:alerta-backup: plantilla no encontrada', ['plantilla' => self::NOMBRE_PLANTILLA]);
            return self::FAILURE;
        }

        $envio = $whatsappApi->enviarTemplate(self::NUMERO_DESTINO, $plantilla, [$origen, $mensaje], $config);

        if (!$envio['ok']) {
            $this->error('Falló el envío de WhatsApp: ' . ($envio['error'] ?? 'desconocido'));
            Log::error('whatsapp:alerta-backup: falló el envío', [
                'origen' => $origen,
                'mensaje' => $mensaje,
                'error' => $envio['error'] ?? null,
            ]);
            return self::FAILURE;
        }

        $this->info('Alerta enviada: ' . $envio['wa_message_id']);
        return self::SUCCESS;
    }
}
