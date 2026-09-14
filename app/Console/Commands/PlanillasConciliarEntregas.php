<?php

namespace App\Console\Commands;

use App\Models\PlanillaEnvioWhatsappDetalle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye el estado de entrega de las planillas ya enviadas.
 *
 * El webhook lleva el estado de Meta al detalle de planillas desde el
 * 14-sep-2026. Lo mandado antes se quedó en «enviado» para siempre, aunque
 * Meta hubiera reportado la entrega o el rebote: ese dato sí se guardó, pero
 * en `whatsapp_mensajes`, que es la tabla del chat. Aquí se cruza por
 * `wa_message_id` y se pone al día lo viejo.
 *
 * Meta no reenvía estados pasados, así que esto es lo único que puede
 * responder cuántas de las que salieron llegaron de verdad.
 */
class PlanillasConciliarEntregas extends Command
{
    protected $signature = 'planillas:conciliar-entregas
                            {--aplicar : Escribe los cambios. Sin esta bandera solo informa.}
                            {--anio= : Limitar a un año de periodo}
                            {--mes= : Limitar a un mes de periodo}
                            {--numero= : Limitar a un celular destino}';

    protected $description = 'Cruza el estado real de Meta (whatsapp_mensajes) con el detalle de envíos de planillas';

    public function handle(): int
    {
        $query = PlanillaEnvioWhatsappDetalle::query()
            ->whereNotNull('wa_message_id')
            ->where('wa_message_id', '!=', '');

        foreach (['anio' => 'periodo_anio', 'mes' => 'periodo_mes'] as $opcion => $columna) {
            if ($this->option($opcion)) {
                $query->where($columna, (int) $this->option($opcion));
            }
        }

        if ($this->option('numero')) {
            $query->where('wa_numero', 'like', '%'.preg_replace('/[^0-9]/', '', $this->option('numero')).'%');
        }

        $detalles = $query->get();

        if ($detalles->isEmpty()) {
            $this->warn('No hay envíos con wa_message_id para esos filtros.');

            return self::SUCCESS;
        }

        // Una sola consulta: los detalles pueden ser miles.
        $estadosMeta = DB::table('whatsapp_mensajes')
            ->whereIn('wa_message_id', $detalles->pluck('wa_message_id')->all())
            ->pluck('estado', 'wa_message_id');

        $aplicar = (bool) $this->option('aplicar');
        $resumen = [];
        $cambiados = 0;
        $sinRastro = 0;

        foreach ($detalles as $detalle) {
            $estadoMeta = $estadosMeta[$detalle->wa_message_id] ?? null;

            if (! $estadoMeta) {
                $sinRastro++;
                continue;
            }

            $clave = "{$detalle->estado} → {$estadoMeta}";
            $resumen[$clave] = ($resumen[$clave] ?? 0) + 1;

            if ($aplicar && $detalle->aplicarEstadoDeMeta($estadoMeta)) {
                $cambiados++;
            }
        }

        $this->newLine();
        $this->line("Envíos revisados: {$detalles->count()}");

        if ($sinRastro) {
            // Sin fila en whatsapp_mensajes no hay nada que Meta haya contado.
            $this->line("Sin rastro en el chat: {$sinRastro} (no se pueden conciliar)");
        }

        $this->newLine();
        $this->table(
            ['Estado en planillas → Estado real en Meta', 'Cantidad'],
            collect($resumen)->map(fn ($n, $k) => [$k, $n])->sortByDesc(1)->values()->all()
        );

        $llegaron = ($resumen['enviado → entregado'] ?? 0) + ($resumen['enviado → leido'] ?? 0);
        $rebotaron = $resumen['enviado → fallido'] ?? 0;
        $enElLimbo = $resumen['enviado → enviado'] ?? 0;

        $this->newLine();
        $this->info("Llegaron de verdad : {$llegaron}");
        $this->error("Rebotaron          : {$rebotaron}");
        $this->warn("Sin confirmar      : {$enElLimbo}  (Meta las aceptó y nunca confirmó entrega)");
        $this->newLine();

        if ($aplicar) {
            $this->info("Actualizados {$cambiados} registros.");
        } else {
            $this->comment('Nada se escribió. Para aplicarlo: php artisan planillas:conciliar-entregas --aplicar');
        }

        return self::SUCCESS;
    }
}
