<?php

namespace App\Console\Commands;

use App\Models\Publicacion;
use App\Services\Publicidad\PublicacionPublisher;
use Illuminate\Console\Command;

/**
 * Publica las piezas aprobadas cuya fecha de programación ya llegó (o que no tenían fecha
 * — esas ya se publican al aprobar, así que en la práctica este comando solo atiende las
 * programadas). Registrado cada 5 min en Kernel::schedule().
 */
class DespacharPublicaciones extends Command
{
    protected $signature = 'publicaciones:despachar';

    protected $description = 'Publica las piezas de publicidad aprobadas cuya fecha de programación ya llegó';

    public function handle(): int
    {
        $pendientes = Publicacion::where('estado', Publicacion::ESTADO_APROBADA)
            ->where(function ($q) {
                $q->whereNull('programada_at')->orWhere('programada_at', '<=', now());
            })
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay piezas por publicar.');
            return self::SUCCESS;
        }

        foreach ($pendientes as $publicacion) {
            $this->info("Publicando #{$publicacion->id} — {$publicacion->titulo}");
            PublicacionPublisher::publicar($publicacion);
        }

        return self::SUCCESS;
    }
}
