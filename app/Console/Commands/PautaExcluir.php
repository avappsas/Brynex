<?php

namespace App\Console\Commands;

use App\Models\Publicacion;
use Illuminate\Console\Command;

/**
 * Marca piezas para que el piloto no las pague nunca.
 *
 * Una pieza puede estar perfecta para el muro y ser mala para pautar —salió con un defecto que
 * solo se nota pagando alcance, o simplemente no es el mensaje en el que uno quiere gastar—.
 * Esto lo dice de frente, en vez del remiendo de dejarle un `meta_ad_id` muerto para que la
 * consulta de candidatas la saltara.
 */
class PautaExcluir extends Command
{
    protected $signature = 'pauta:excluir
        {piezas* : IDs de las publicaciones}
        {--incluir : Al revés — las vuelve a habilitar para pauta}';

    protected $description = 'Excluye (o vuelve a incluir) publicaciones de la fila de pauta pagada';

    public function handle(): int
    {
        $excluir = !$this->option('incluir');
        $ids = array_map('intval', $this->argument('piezas'));

        $piezas = Publicacion::whereIn('id', $ids)->get();

        if ($piezas->isEmpty()) {
            $this->error('No se encontró ninguna de esas publicaciones.');
            return self::FAILURE;
        }

        foreach ($ids as $id) {
            if (!$piezas->contains('id', $id)) {
                $this->warn("  #{$id} no existe — se omite.");
            }
        }

        foreach ($piezas as $pieza) {
            $pieza->update(['pauta_excluida' => $excluir]);
            $estado = $excluir ? 'excluida de pauta' : 'habilitada para pauta';
            $this->line("  #{$pieza->id} {$estado} — " . mb_substr($pieza->titulo ?: '(sin título)', 0, 50));
        }

        // Avisar si ya tiene un anuncio vivo: excluirla no lo apaga, y creer que sí es
        // justamente la clase de suposición que termina en gasto no querido.
        $conAnuncio = $piezas->filter(fn ($p) => $excluir && $p->meta_ad_id);
        if ($conAnuncio->isNotEmpty()) {
            $this->newLine();
            $this->warn('OJO: ' . $conAnuncio->pluck('id')->map(fn ($i) => "#{$i}")->implode(', ')
                . ' ya tiene(n) un anuncio creado en Meta. Excluirla solo evita que se vuelva a');
            $this->warn('pautar; el anuncio que ya existe hay que pausarlo o borrarlo aparte.');
        }

        return self::SUCCESS;
    }
}
