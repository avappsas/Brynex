<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Services\Publicidad\SegmentosAudiencia;
use App\Services\RedesSociales\MetaAudienciaService;
use Illuminate\Console\Command;

/**
 * Sube los segmentos de clientes a Meta como Custom Audience, para pautarles anuncios en vez
 * de escribirles por WhatsApp sin autorización.
 *
 * Pensado para correrse periódicamente: la gente entra y sale de los segmentos sola (alguien
 * que se retiró hace 2 meses entra a "ventana dorada" y a los 4 sale), así que una audiencia
 * subida una vez y nunca actualizada envejece rápido.
 */
class SincronizarAudienciasMeta extends Command
{
    protected $signature = 'marketing:audiencias
                            {--aliado= : Slug o id del aliado (por defecto, todos los que tengan pauta activa)}
                            {--segmento= : Sincronizar solo este segmento}
                            {--dry-run : Solo muestra los tamaños, sin subir nada a Meta}';

    protected $description = 'Sincroniza los segmentos de clientes con las Custom Audiences de Meta Ads';

    public function handle(): int
    {
        $aliados = $this->resolverAliados();
        if ($aliados->isEmpty()) {
            $this->warn('No hay aliados con pauta configurada.');
            return self::SUCCESS;
        }

        $segmentos = $this->option('segmento')
            ? [$this->option('segmento')]
            : array_keys(SegmentosAudiencia::SEGMENTOS);

        foreach ($aliados as $aliado) {
            $this->info("── {$aliado->nombre} ──");

            foreach ($segmentos as $segmento) {
                if (!isset(SegmentosAudiencia::SEGMENTOS[$segmento])) {
                    $this->error("  Segmento desconocido: {$segmento}");
                    continue;
                }

                if ($this->option('dry-run')) {
                    $n = count(SegmentosAudiencia::telefonos($segmento, $aliado->id));
                    $this->line("  [simulación] {$segmento}: {$n} contactos");
                    continue;
                }

                $r = MetaAudienciaService::sincronizar($aliado->id, $segmento);
                $r['ok']
                    ? $this->info("  ✅ {$segmento}: {$r['mensaje']}")
                    : $this->error("  ❌ {$segmento}: {$r['mensaje']}");
            }
        }

        return self::SUCCESS;
    }

    private function resolverAliados()
    {
        if ($opcion = $this->option('aliado')) {
            return Aliado::where('slug', $opcion)->orWhere('id', (int) $opcion)->get();
        }

        $conPauta = \App\Models\PautaConfig::where('activo', true)->pluck('aliado_id');

        return Aliado::whereIn('id', $conPauta)->get();
    }
}
