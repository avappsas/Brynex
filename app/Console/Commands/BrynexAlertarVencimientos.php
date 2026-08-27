<?php

namespace App\Console\Commands;

use App\Models\BrynexObligacion;
use App\Models\BrynexObligacionCatalogo;
use App\Models\User;
use App\Services\AlertaOperativaService;
use Illuminate\Console\Command;

/**
 * Avisa por WhatsApp los vencimientos tributarios de las razones sociales en
 * seguimiento.
 *
 * Manda UN mensaje por contador con el resumen, no uno por obligación: un
 * contador con 20 razones sociales recibiría 60 mensajes en un día de
 * vencimientos y dejaría de leerlos.
 *
 * Las obligaciones sin `fecha_vencimiento` (los años viejos, sin calendario
 * cargado) quedan fuera: no hay contra qué medirlas.
 */
class BrynexAlertarVencimientos extends Command
{
    protected $signature = 'brynex:alertar-vencimientos
                            {--dias=7 : Avisar de lo que vence dentro de estos días}
                            {--seco : Muestra qué se enviaría, sin enviar nada}';

    protected $description = 'Avisa por WhatsApp al contador los vencimientos tributarios próximos y vencidos';

    public function handle(AlertaOperativaService $alertas): int
    {
        $dias = (int) $this->option('dias');
        $seco = (bool) $this->option('seco');

        $catalogo = BrynexObligacionCatalogo::pluck('nombre', 'codigo');

        $base = fn () => BrynexObligacion::query()
            ->join('brynex_razones_sociales as f', 'f.id', '=', 'brynex_obligaciones.ficha_id')
            ->whereNull('f.deleted_at')
            ->where('f.en_seguimiento', true)
            ->where('f.estado', 'activa')
            ->select('brynex_obligaciones.*', 'f.razon_social', 'f.contador_id');

        $vencidas = $base()->vencidas()->get();
        $porVencer = $base()->porVencer($dias)->get();

        if ($vencidas->isEmpty() && $porVencer->isEmpty()) {
            $this->info('Nada vencido ni por vencer. No se envía nada.');

            return self::SUCCESS;
        }

        // Agrupar por contador. Las razones sociales sin contador asignado se
        // juntan bajo la llave 0 y salen al número de guardia: mejor que nadie
        // se entere de que a nadie le avisaron.
        $porContador = $vencidas->concat($porVencer)->groupBy(fn ($o) => (int) $o->contador_id);

        $enviados = 0;

        foreach ($porContador as $contadorId => $obligaciones) {
            $contador = $contadorId ? User::find($contadorId) : null;
            $numero = $contador?->telefono ?: $alertas->numeroDestino();

            if (! $numero) {
                $this->warn("Contador {$contadorId} sin teléfono: se omite.");

                continue;
            }

            $mensaje = $this->armarMensaje($obligaciones, $catalogo, $dias);
            $destinatario = $contador?->nombre ?? 'guardia (sin contador asignado)';

            $this->line("→ {$destinatario} ({$numero}): {$mensaje}");

            if ($seco) {
                continue;
            }

            if ($alertas->enviarA($numero, 'Vencimientos tributarios', $mensaje)) {
                $enviados++;
            } else {
                $this->error("No se pudo avisar a {$destinatario}.");
            }
        }

        $this->info($seco
            ? 'Corrida en seco: no se envió nada.'
            : "Avisos enviados: {$enviados}.");

        return self::SUCCESS;
    }

    /**
     * Un solo párrafo: cuántas vencidas, cuántas por vencer y las tres más
     * urgentes con nombre y apellido. El detalle completo está en el tablero.
     */
    private function armarMensaje($obligaciones, $catalogo, int $dias): string
    {
        $hoy = now()->startOfDay();

        $vencidas = $obligaciones->filter(
            fn ($o) => $o->fecha_vencimiento->lt($hoy)
                && ! in_array($o->estado, BrynexObligacion::ESTADOS_CERRADOS, true)
                && $o->estado !== 'presentada'
        );
        $proximas = $obligaciones->filter(fn ($o) => $o->fecha_vencimiento->gte($hoy));

        $partes = [];

        if ($vencidas->count()) {
            $partes[] = "{$vencidas->count()} obligación(es) VENCIDA(S)";
        }

        if ($proximas->count()) {
            $partes[] = "{$proximas->count()} vence(n) en los próximos {$dias} días";
        }

        $urgentes = $obligaciones->sortBy('fecha_vencimiento')->take(3)
            ->map(fn ($o) => sprintf(
                '%s · %s %s (%s)',
                $o->razon_social,
                $catalogo[$o->obligacion_codigo] ?? $o->obligacion_codigo,
                $o->periodo_etiqueta,
                $o->fecha_vencimiento->format('d/M')
            ))
            ->implode('; ');

        return implode(' y ', $partes).'. Lo más urgente: '.$urgentes;
    }
}
