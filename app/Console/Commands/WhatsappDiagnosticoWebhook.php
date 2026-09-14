<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Responde una sola pregunta: ¿los webhooks de Meta están llegando?
 *
 * Nació porque el log salió en cero y eso admite dos lecturas muy distintas:
 * que Meta no esté llamando, o que el nivel de log esté descartando los
 * `Log::info`. Mirar la base no tiene esa ambigüedad — un mensaje entrante solo
 * puede existir si un webhook llegó y se procesó.
 */
class WhatsappDiagnosticoWebhook extends Command
{
    protected $signature = 'whatsapp:diagnostico-webhook {--dias=7 : Ventana a revisar}';

    protected $description = 'Revisa si los webhooks de Meta están llegando y si los acuses de entrega se registran';

    public function handle(): int
    {
        $desde = now()->subDays((int) $this->option('dias'));

        $this->newLine();
        $this->line('<comment>── Registro ──</comment>');
        $this->line('  LOG_CHANNEL: '.config('logging.default'));
        $this->line('  LOG_LEVEL:   '.env('LOG_LEVEL', 'debug').(env('LOG_LEVEL', 'debug') === 'debug' ? '' : '  <- distinto de debug: los Log::info del webhook NO se escriben'));

        // Nunca el valor: solo si está puesto.
        $this->newLine();
        $this->line('<comment>── Credenciales del webhook ──</comment>');
        foreach (['app_secret' => 'App Secret (firma HMAC)', 'webhook_verify_token' => 'Verify token'] as $clave => $etiqueta) {
            $puesto = filled(config("services.whatsapp.{$clave}"));
            $this->line(sprintf('  %-26s %s', $etiqueta.':', $puesto ? 'configurado' : 'VACÍO'));
        }

        // Un entrante no se puede fabricar solo: si hay, el webhook llega.
        $this->newLine();
        $this->line('<comment>── ¿Llegan webhooks? ──</comment>');
        $entrante = WhatsappMensaje::where('direccion', 'entrante')->latest('id')->first();

        if ($entrante) {
            $this->info('  Último mensaje ENTRANTE: '.$entrante->created_at.'  (hace '.$entrante->created_at->diffForHumans(null, true).')');
            $this->line('  → El webhook sí llega al servidor.');
        } else {
            $this->error('  Nunca se ha registrado un mensaje entrante.');
            $this->line('  → O Meta no está llamando, o la URL del webhook no está registrada.');
        }

        // Un estado distinto de «enviado» solo lo pone el webhook de statuses.
        $this->newLine();
        $this->line('<comment>── ¿Llegan los acuses de entrega? ──</comment>');
        $conAcuse = WhatsappMensaje::whereIn('estado', ['entregado', 'leido', 'fallido'])->latest('id')->first();

        if ($conAcuse) {
            $this->info('  Último acuse: '.$conAcuse->estado.' el '.($conAcuse->estado_at ?? $conAcuse->updated_at));
        } else {
            $this->error('  Nunca se ha registrado un acuse (entregado/leído/fallido).');
            $this->line('  → Meta no manda `statuses`, o no se están procesando.');
        }

        $this->newLine();
        $this->line("<comment>── Mensajes salientes de los últimos {$this->option('dias')} días ──</comment>");
        $porEstado = WhatsappMensaje::where('direccion', 'saliente')
            ->where('created_at', '>=', $desde)
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->orderByDesc('total')
            ->get();

        if ($porEstado->isEmpty()) {
            $this->warn('  Ninguno.');
        } else {
            $this->table(['Estado', 'Cantidad'], $porEstado->map(fn ($e) => [$e->estado ?? '(nulo)', $e->total])->all());

            if ($porEstado->count() === 1 && $porEstado->first()->estado === 'enviado') {
                $this->warn('  Todos en «enviado»: ni una entrega ni un rebote. Los acuses no se están registrando.');
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
