<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarPpAPt extends Command
{
    protected $signature   = 'migrar:pp-a-pt';
    protected $description = 'Cambia tipo_doc PP → PT en clientes y planos';

    public function handle(): int
    {
        $clientes = DB::table('clientes')->where('tipo_doc', 'PP')->update(['tipo_doc' => 'PT']);
        $this->info("Clientes actualizados: {$clientes}");

        // También planos si tiene esa columna
        try {
            $planos = DB::table('planos')->where('tipo_doc', 'PP')->update(['tipo_doc' => 'PT']);
            $this->info("Planos actualizados:   {$planos}");
        } catch (\Throwable $e) {
            $this->warn('Tabla planos sin columna tipo_doc o no existe.');
        }

        $this->info('✅ Migración PP → PT completada.');
        return 0;
    }
}
