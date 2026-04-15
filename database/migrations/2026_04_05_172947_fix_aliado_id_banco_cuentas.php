<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Las cuentas bancarias quedaron con aliado_id=1 (legacy)
        // Brygar es aliado_id=2, se corrige aquí.
        DB::table('banco_cuentas')
            ->where('aliado_id', 1)
            ->update(['aliado_id' => 2]);
    }

    public function down(): void
    {
        DB::table('banco_cuentas')
            ->where('aliado_id', 2)
            ->update(['aliado_id' => 1]);
    }
};
