<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BancoCuentasSeeder extends Seeder
{
    public function run(): void
    {
        // Leer datos legacy de BANCOS_CUENTAS
        $legacy = DB::connection('sqlsrv_legacy')
            ->table('BANCOS_CUENTAS')
            ->get();

        if ($legacy->isEmpty()) {
            $this->command->warn('No se encontraron registros en BANCOS_CUENTAS.');
            return;
        }

        // Obtener el aliado_id por defecto (el primero activo)
        $aliadoId = DB::table('aliados')->where('activo', 1)->value('id') ?? 1;

        $rows = $legacy->map(fn($r) => [
            'aliado_id'     => $aliadoId,
            'nombre'        => $r->NOMBRE    ?? 'Sin nombre',
            'nit'           => $r->NIT        ?? null,
            'banco'         => $r->BANCO      ?? 'Desconocido',
            'tipo_cuenta'   => $r->TIPO       ?? null,
            'numero_cuenta' => $r->NUMERO     ?? null,
            'activo'        => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ])->toArray();

        DB::table('banco_cuentas')->insert($rows);

        $this->command->info("✅ {$legacy->count()} cuentas bancarias migradas.");
    }
}
