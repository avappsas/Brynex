<?php

namespace App\Console\Commands;

use App\Models\PlanillaEnvioWhatsappDetalle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve envíos de planilla a «pendiente» para poder reenviarlos en lote.
 *
 * Hace falta cuando el destinatario resulta ser el equivocado: las filas
 * quedaron en «enviado» —Meta las aceptó— y con ese estado el envío masivo no
 * las vuelve a tomar, porque solo procesa pendientes, fallidas y omitidas.
 *
 * El botón «Enviar» individual no necesita esto: reenvía esté como esté. Este
 * comando es para cuando son ciento y pico y no se van a clicar una por una.
 *
 * No borra el rastro: el estado anterior y el wamid quedan anotados en `error`,
 * porque una fila que dice «pendiente» sin más estaría mintiendo sobre que ya
 * hubo un intento.
 */
class PlanillasReabrirEnvios extends Command
{
    protected $signature = 'planillas:reabrir-envios
                            {--aplicar : Escribe los cambios. Sin esta bandera solo informa.}
                            {--empresa= : Nombre (o parte) de la empresa destinataria}
                            {--numero= : Celular al que se enviaron}
                            {--anio= : Limitar a un año de periodo}
                            {--mes= : Limitar a un mes de periodo}
                            {--limite= : Reabrir solo las primeras N, para probar}';

    protected $description = 'Devuelve a pendiente los envíos de planilla ya despachados, para reenviarlos';

    public function handle(): int
    {
        if (! $this->option('empresa') && ! $this->option('numero')) {
            $this->error('Indica --empresa o --numero: reabrir todo el histórico no es algo que se quiera hacer sin querer.');

            return self::FAILURE;
        }

        $query = PlanillaEnvioWhatsappDetalle::query()
            ->whereIn('estado', ['enviado', 'entregado', 'leido']);

        if ($this->option('empresa')) {
            $ids = DB::table('empresas')
                ->where('empresa', 'like', '%'.$this->option('empresa').'%')
                ->pluck('id');

            if ($ids->isEmpty()) {
                $this->error('Ninguna empresa coincide con «'.$this->option('empresa').'».');

                return self::FAILURE;
            }

            $query->whereIn('empresa_id', $ids->all());
        }

        if ($this->option('numero')) {
            $query->where('wa_numero', 'like', '%'.preg_replace('/[^0-9]/', '', $this->option('numero')).'%');
        }

        foreach (['anio' => 'periodo_anio', 'mes' => 'periodo_mes'] as $opcion => $columna) {
            if ($this->option($opcion)) {
                $query->where($columna, (int) $this->option($opcion));
            }
        }

        $query->orderBy('id');

        if ($this->option('limite')) {
            $query->limit((int) $this->option('limite'));
        }

        $detalles = $query->get();

        if ($detalles->isEmpty()) {
            $this->warn('No hay envíos despachados que coincidan con esos filtros.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Destinatario', 'Celular', 'Planilla', 'Periodo', 'Estado'],
            $detalles->take(15)->map(fn ($d) => [
                mb_strimwidth($d->nombre_destinatario ?? '', 0, 34, '…'),
                $d->wa_numero,
                $d->numero_planilla,
                "{$d->periodo_mes}/{$d->periodo_anio}",
                $d->estado,
            ])->all()
        );

        if ($detalles->count() > 15) {
            $this->line('  … y '.($detalles->count() - 15).' más.');
        }

        $this->newLine();
        $this->line('Envíos a reabrir: '.$detalles->count());

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('Nada se escribió. Para aplicarlo, repite el comando con --aplicar');

            return self::SUCCESS;
        }

        $sello = now()->format('Y-m-d H:i');

        foreach ($detalles as $detalle) {
            $detalle->update([
                'estado' => 'pendiente',
                'error'  => mb_substr(
                    "Reabierto {$sello}: estaba en «{$detalle->estado}»"
                    .($detalle->wa_message_id ? " (wamid {$detalle->wa_message_id})" : ''),
                    0,
                    500
                ),
            ]);
        }

        $this->newLine();
        $this->info('Reabiertos '.$detalles->count().' envíos. Ya salen en el filtro «Pendientes / Fallidos».');

        return self::SUCCESS;
    }
}
