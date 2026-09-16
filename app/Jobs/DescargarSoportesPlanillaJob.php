<?php

namespace App\Jobs;

use App\Models\Plano;
use App\Services\AlertaOperativaService;
use App\Services\EnlaceInformeIndividualService;
use App\Services\SuaporteApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Baja del operador los informes individuales de una planilla recién pagada y
 * los deja en disco, para que el envío por WhatsApp los tenga listos.
 *
 * Se dispara al confirmar el pago (PlanoPagoController::confirmarPago), con
 * espera: el operador tarda en mostrar el pago. Si no aparece, reintenta según
 * `planillas.soportes_esperas_minutos`, y al agotar los intentos avisa por
 * WhatsApp: una planilla que nunca aparece pagada casi siempre es un número mal
 * digitado al confirmar, y mejor saberlo el mismo día.
 *
 * Trabaja por tramos de menos de un minuto y se vuelve a encolar para seguir.
 * La cola `database` le devuelve a otro worker toda tarea que pase de
 * `retry_after` (90 s): una planilla de cien personas en una sola corrida la
 * bajarían dos workers a la vez. Lo ya bajado queda en disco y no se repite.
 */
class DescargarSoportesPlanillaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Varios intentos solo para cuando la encuentra ocupada (ver handle); los
     * reintentos por pago aún no visible son tareas nuevas, con su espera.
     */
    public $tries = 5;

    public $timeout = 85;

    /** Segundos de trabajo por tramo, con margen bajo el `retry_after` de la cola. */
    private const SEGUNDOS_POR_TRAMO = 55;

    public function __construct(
        public int $aliadoId,
        public string $numeroPlanilla,
        public int $operadorPlanillaId,
        public ?int $usuarioId = null,
        public int $intento = 0,
    ) {}

    /**
     * Encola la descarga de una planilla recién confirmada, si su operador es de
     * los que la permiten. Nunca lanza: confirmar el pago no puede fallar por esto.
     */
    public static function programar(int $aliadoId, string $numeroPlanilla, string $nombreOperador, ?int $usuarioId): void
    {
        try {
            $operador = DB::table('operadores_planilla')->where('nombre', $nombreOperador)->first(['id', 'codigo']);

            if (! $operador || ! SuaporteApiService::soportaOperador($operador->codigo)) {
                return;
            }

            $esperas = config('planillas.soportes_esperas_minutos', [10]);

            self::dispatch($aliadoId, $numeroPlanilla, (int) $operador->id, $usuarioId)
                ->delay(now()->addMinutes($esperas[0] ?? 10));
        } catch (\Throwable $e) {
            Log::warning('Soportes de planilla: no se pudo programar la descarga', [
                'planilla' => $numeroPlanilla,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function handle(EnlaceInformeIndividualService $soportes, AlertaOperativaService $alertas): void
    {
        // Un solo worker por planilla, aunque la cola la entregue dos veces.
        $candado = Cache::lock("soportes-planilla:{$this->aliadoId}:{$this->numeroPlanilla}", 120);

        if (! $candado->get()) {
            // Otro worker la tiene: puede ser una entrega repetida de la cola o
            // la continuación de un tramo que llegó antes de soltar el candado.
            // Se devuelve a la cola: si era repetida, al volver no queda nada
            // pendiente en disco y termina sin hacer nada.
            $this->release(60);

            return;
        }

        try {
            $this->descargar($soportes, $alertas);
        } finally {
            $candado->release();
        }
    }

    private function descargar(EnlaceInformeIndividualService $soportes, AlertaOperativaService $alertas): void
    {
        $inicio = microtime(true);

        $planos = Plano::with('razonSocial')
            ->where('aliado_id', $this->aliadoId)
            ->where('numero_planilla', $this->numeroPlanilla)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($planos->isEmpty()) {
            return;
        }

        $pendientes = $planos->reject(
            fn ($p) => \Storage::disk('local')->exists(EnlaceInformeIndividualService::rutaEnDisco($p))
        )->values();

        if ($pendientes->isEmpty()) {
            return;
        }

        $bajados = 0;
        $fallos = [];

        foreach ($pendientes as $i => $plano) {
            $r = $soportes->obtener($plano, $this->operadorPlanillaId);

            if ($r['success']) {
                $bajados++;
            } else {
                $fallos[$plano->no_identifi] = $r['message'] ?? '';

                // Si en una planilla sin nada bajado fallan las dos primeras
                // personas, lo que no está es la planilla (aún sin pago visible, o
                // el número mal digitado): no tiene caso seguir con las demás. Con
                // una sola que falle puede ser solo esa persona.
                $planillaNueva = $pendientes->count() === $planos->count();
                if ($planillaNueva && $bajados === 0 && count($fallos) >= min(2, $pendientes->count())) {
                    $this->sinPago($this->motivo($r['message'] ?? ''), $r['message'] ?? '', $plano, $soportes, $alertas);

                    return;
                }
            }

            if (microtime(true) - $inicio > self::SEGUNDOS_POR_TRAMO && $i < $pendientes->count() - 1) {
                // Sigue en otro tramo, sin esperar: lo bajado ya quedó en disco.
                self::dispatch($this->aliadoId, $this->numeroPlanilla, $this->operadorPlanillaId, $this->usuarioId, $this->intento)
                    ->delay(now()->addSeconds(10));

                return;
            }
        }

        if ($fallos) {
            Log::warning('Soportes de planilla: personas sin informe del operador', [
                'aliado_id' => $this->aliadoId,
                'planilla'  => $this->numeroPlanilla,
                'fallos'    => $fallos,
            ]);
        }
    }

    /** no_pagada: reintentar · config: avisar ya · error: reintentar (red, portal caído). */
    private function motivo(string $mensaje): string
    {
        if (str_contains($mensaje, 'no es un número de planilla válido')) {
            return 'config';
        }

        return match (EnlaceInformeIndividualService::estadoPorMensaje($mensaje)) {
            'no_encontrada', null => str_contains($mensaje, 'sin credenciales') && ! str_contains($mensaje, 'no figura')
                ? 'config'
                : 'no_pagada',
            'sin_acceso' => 'config',
            default      => 'error',
        };
    }

    private function sinPago(string $motivo, string $mensaje, Plano $plano, EnlaceInformeIndividualService $soportes, AlertaOperativaService $alertas): void
    {
        $esperas = config('planillas.soportes_esperas_minutos', [10]);
        $siguiente = $this->intento + 1;

        // Sin credenciales el aliado simplemente no usa esto: ni reintento ni aviso.
        if (str_contains($mensaje, 'sin credenciales')) {
            return;
        }

        if ($motivo !== 'config' && isset($esperas[$siguiente])) {
            // Mientras le queden intentos no se da por mal digitada: el pago
            // puede estar en tránsito.
            $soportes->registrarVerificacion(
                $plano, $this->operadorPlanillaId, 'verificando',
                "Aún no aparece en el operador; nuevo intento en {$esperas[$siguiente]} min ({$siguiente} de ".count($esperas).').'
            );

            self::dispatch($this->aliadoId, $this->numeroPlanilla, $this->operadorPlanillaId, $this->usuarioId, $siguiente)
                ->delay(now()->addMinutes($esperas[$siguiente]));

            return;
        }

        $operador = DB::table('operadores_planilla')->where('id', $this->operadorPlanillaId)->value('nombre');
        $usuario = $this->usuarioId ? DB::table('users')->where('id', $this->usuarioId)->value('nombre') : null;
        $horas = round(array_sum(array_slice($esperas, 0, $siguiente)) / 60, 1);

        $texto = $motivo === 'no_pagada'
            ? "La planilla {$this->numeroPlanilla} de {$plano->razon_social} ({$operador}) no aparece pagada en el operador después de {$siguiente} intentos en {$horas} h."
                .' Revisa que el número esté bien digitado en la confirmación del pago'.($usuario ? " (la confirmó {$usuario})." : '.')
            : "No se pudieron bajar los soportes de la planilla {$this->numeroPlanilla} de {$plano->razon_social} ({$operador}): {$mensaje}";

        Log::warning('Soportes de planilla: aviso', ['aliado_id' => $this->aliadoId, 'texto' => $texto]);

        foreach (config("planillas.soportes_aviso_whatsapp.{$this->aliadoId}", []) as $numero) {
            $alertas->enviarA($numero, 'Soportes de planilla', $texto);
        }
    }
}
