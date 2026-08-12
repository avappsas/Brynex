<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\PautaConfig;
use App\Models\Publicacion;
use App\Services\RedesSociales\MetaAdsService;
use Illuminate\Console\Command;

/**
 * Mantiene el conjunto permanente de pauta: mete la pieza publicada más reciente como
 * creatividad nueva (si queda cupo semanal) y pausa las que ya no compiten.
 *
 * No enciende gasto: si el conjunto está en pausa, las creatividades entran en pausa. El
 * único acto que abre la llave es activar el conjunto, y eso es manual a propósito.
 */
class PautaCreatividades extends Command
{
    protected $signature = 'marketing:pauta-creatividades {--aliado= : Slug del aliado (por defecto, todos los que tengan pauta activa)}';

    protected $description = 'Agrega la pieza del día al conjunto permanente de pauta y rota las creatividades';

    public function handle(): int
    {
        $aliados = $this->option('aliado')
            ? Aliado::where('slug', $this->option('aliado'))->get()
            : Aliado::whereIn('id', PautaConfig::where('activo', true)->pluck('aliado_id'))->get();

        if ($aliados->isEmpty()) {
            $this->warn('No hay aliados con pauta activa.');
            return self::SUCCESS;
        }

        foreach ($aliados as $aliado) {
            $this->line("── {$aliado->nombre}");
            $config = PautaConfig::paraAliado($aliado->id);

            $conjunto = MetaAdsService::asegurarConjuntoPermanente($config, $aliado->id);
            if (!$conjunto['ok']) {
                $this->error("   {$conjunto['mensaje']}");
                continue;
            }
            $this->line("   {$conjunto['mensaje']}");

            // Candidata: la pieza publicada más reciente que todavía no esté pautada. Solo
            // se pauta lo que ya salió en orgánico — si una pieza no era digna de publicarse,
            // menos de pagarla.
            $candidata = Publicacion::where('aliado_id', $aliado->id)
                ->whereNotNull('publicada_at')
                ->whereNull('meta_ad_id')
                ->where('pauta_excluida', false)
                ->orderByDesc('publicada_at')
                ->first();

            if (!$candidata) {
                $this->line('   Sin piezas nuevas para pautar.');
            } else {
                $r = MetaAdsService::agregarPieza($candidata);
                $this->line('   ' . ($r['ok'] ? "✅ {$r['mensaje']}" : "⏭  {$r['mensaje']}"));
            }

            $rotacion = MetaAdsService::rotarCreatividades($config->fresh(), $aliado->id);
            $this->line("   {$rotacion['mensaje']}");
        }

        return self::SUCCESS;
    }
}
