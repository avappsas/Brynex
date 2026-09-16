<?php

namespace App\Jobs;

use App\Models\Contrato;
use App\Services\Pension\PensionConciliacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Al crear un contrato con pensión, pregunta al RUAF a qué fondo pertenece la
 * persona: pone el fondo en el contrato y en la ficha, y deja el radicado de
 * pensión en OK confirmado.
 *
 * En pensión el vínculo es persona ↔ fondo, así que el trámite ya está hecho
 * antes de que el contrato exista: lo único que falta es comprobarlo. Por eso el
 * radicado nace cerrado y no hay que conciliar nada después.
 *
 * Va en cola y no dentro del guardado porque la consulta al operador tarda unos
 * segundos y no tiene por qué hacer esperar a quien llena el formulario (en
 * producción `QUEUE_CONNECTION=database`; en un entorno con `sync` sí corre
 * dentro de la petición, aunque para entonces el RUAF suele estar en la caché
 * de 10 minutos que dejó la consulta del cliente). Si el operador no responde,
 * el radicado se queda pendiente como antes y lo recoge `pension:conciliar`.
 *
 * Ojo: si el RUAF dice un fondo distinto al que se escribió en el formulario,
 * el contrato se corrige solo —el RUAF manda— y el cambio queda en la bitácora
 * del contrato, no en pantalla.
 */
class ConfirmarPensionConRuaf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public function __construct(public int $contratoId, public ?int $usuarioId = null) {}

    public function handle(PensionConciliacionService $servicio): void
    {
        $contrato = Contrato::with('cliente')->find($this->contratoId);

        if (! $contrato) {
            return;
        }

        // La bitácora se cuelga de Auth::id(); en cola no hay sesión.
        if ($this->usuarioId) {
            Auth::onceUsingId($this->usuarioId);
        }

        $resultado = $servicio->alCrearContrato($contrato, $this->usuarioId);

        if (($resultado['accion'] ?? null) !== 'cerrado') {
            Log::info('Pensión RUAF: el radicado sigue abierto', [
                'contrato_id' => $contrato->id,
                'cedula' => $contrato->cedula,
                'accion' => $resultado['accion'] ?? null,
                'mensaje' => $resultado['mensaje'] ?? null,
            ]);
        }
    }
}
