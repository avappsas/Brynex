<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\WhatsappEnvioMasivo;
use App\Models\WhatsappEnvioMasivoDetalle;
use App\Models\WhatsappPlantilla;
use App\Services\Cumplimiento\VentanaContactoLey2300;
use Illuminate\Console\Command;

/**
 * Campaña de reactivación: le escribe a quien se retiró y no ha vuelto.
 *
 * Sale del hallazgo de que este negocio es TRANSACCIONAL RECURRENTE y no una suscripción:
 * el 33% de los clientes vuelve, y la mayoría dentro de los tres meses. Traer de vuelta a
 * alguien que ya compró cuesta una fracción de los ~$7.500 por conversación que cuesta un
 * desconocido en pauta.
 *
 * La ventana por defecto NO es 15-30 días, aunque suene a lo obvio: los retiros se registran
 * con rezago —el 18-ago-2026 el retiro más reciente era del 3-ago y en esa ventana había UNA
 * persona— así que apuntar ahí no dispara nunca. El valor está en el rezago acumulado: 166
 * personas entre 31 y 90 días, 537 entre 91 y 365.
 *
 * Arranca en simulación a propósito. Es un envío masivo a gente real, con costo por plantilla
 * de marketing y con la Ley 2300 encima: se mira la lista antes de mandarla.
 */
class MarketingReactivacion extends Command
{
    protected $signature = 'marketing:reactivacion
        {--aliado=brygar : Slug del aliado}
        {--desde=31 : Días mínimos desde el retiro}
        {--hasta=90 : Días máximos desde el retiro}
        {--plantilla= : Nombre de la plantilla de WhatsApp aprobada (categoría MARKETING)}
        {--limite=50 : Máximo de destinatarios por corrida}
        {--enviar : Enviar de verdad. Sin esto solo simula y muestra a quién le llegaría}';

    protected $description = 'Escribe por WhatsApp a los clientes retirados que no han vuelto';

    public function handle(): int
    {
        $aliado = Aliado::where('slug', $this->option('aliado'))->first();
        if (!$aliado) {
            $this->error('No existe ese aliado.');
            return self::FAILURE;
        }

        $desde = (int) $this->option('desde');
        $hasta = (int) $this->option('hasta');
        $limite = (int) $this->option('limite');
        $enviarDeVerdad = (bool) $this->option('enviar');

        $r = \App\Services\Marketing\CandidatosReactivacion::elegibles($aliado->id, $desde, $hasta);
        $elegibles = $r['elegibles'];

        $this->line("Retirados hace {$desde}-{$hasta} días que ya no son clientes: {$r['candidatos']}");
        $this->line("Contactables hoy: {$elegibles->count()}"
            . "  (fuera: {$r['sin_consentimiento']} por baja o teléfono inválido, "
            . "{$r['aplazados']} aplazados, {$r['ya_enviados']} ya contactados)");

        if ($elegibles->isEmpty()) {
            $this->warn('Nadie por contactar en esta ventana.');
            return self::SUCCESS;
        }

        $destinatarios = $elegibles->take($limite)->values();
        $recortados = $elegibles->count() - $destinatarios->count();
        if ($recortados > 0) {
            $this->line("En esta corrida: {$destinatarios->count()} por el --limite. Quedan {$recortados} para la siguiente.");
        }

        $this->table(
            ['Contrato', 'Nombre', 'Teléfono', 'Retiro', 'Días'],
            $destinatarios->take(10)->map(fn ($c) => [
                $c->contrato_id,
                mb_substr((string) $c->nombre, 0, 28),
                $c->telefono,
                substr((string) $c->fecha_retiro, 0, 10),
                $c->dias,
            ])->all()
        );
        if ($destinatarios->count() > 10) {
            $this->line('  … y ' . ($destinatarios->count() - 10) . ' más.');
        }

        if (!$enviarDeVerdad) {
            $this->newLine();
            $this->info('SIMULACIÓN — no se envió nada. Agregar --enviar para mandarlo de verdad.');
            return self::SUCCESS;
        }

        return $this->enviar($aliado, $destinatarios, $desde, $hasta);
    }

    private function enviar(Aliado $aliado, $destinatarios, int $desde, int $hasta): int
    {
        $nombrePlantilla = $this->option('plantilla');
        if (!$nombrePlantilla) {
            $this->error('Falta --plantilla. Es un mensaje fuera de la ventana de 24h: Meta exige una plantilla aprobada.');
            return self::FAILURE;
        }

        $r = \App\Services\Marketing\EnvioReactivacion::lanzar(
            $aliado,
            collect($destinatarios),
            $nombrePlantilla,
            ['dias_desde' => $desde, 'dias_hasta' => $hasta]
        );

        $r['ok'] ? $this->info($r['mensaje']) : $this->error($r['mensaje']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
