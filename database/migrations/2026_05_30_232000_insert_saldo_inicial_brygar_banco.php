<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saldos_banco')->insert([
            'aliado_id' => 2,
            'banco_cuenta_id' => 145,
            'fecha' => '2026-05-01',
            'tipo' => 'saldo_inicial',
            'descripcion' => 'Saldo de arrastre de abril Brygar SAS',
            'usuario_id' => 2,
            'valor' => 1750700,
            'saldo_acumulado' => 1750700,
            'created_at' => '2026-05-01 00:00:00',
            'updated_at' => '2026-05-01 00:00:00',
        ]);
    }

    public function down(): void
    {
        DB::table('saldos_banco')
            ->where('aliado_id', 2)
            ->where('banco_cuenta_id', 145)
            ->where('tipo', 'saldo_inicial')
            ->delete();
    }
};
