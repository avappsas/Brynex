<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Agregar aliado_id a clientes ──
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('aliado_id')->nullable()->after('id');
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->index('aliado_id');
        });

        // ── Agregar aliado_id a contratos ──
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('aliado_id')->nullable()->after('id');
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->index('aliado_id');
        });

        // ── Agregar aliado_id a razones_sociales ──
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->unsignedBigInteger('aliado_id')->nullable()->after('id');
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->index('aliado_id');
        });

        // ── Asignar BRYGAR (id=2, NIT 901918923) a todos los registros existentes ──
        $brygarId = DB::table('aliados')->where('nit', '901918923')->value('id');
        if ($brygarId) {
            DB::table('clientes')->whereNull('aliado_id')->update(['aliado_id' => $brygarId]);
            DB::table('contratos')->whereNull('aliado_id')->update(['aliado_id' => $brygarId]);
            DB::table('razones_sociales')->whereNull('aliado_id')->update(['aliado_id' => $brygarId]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['aliado_id']);
            $table->dropColumn('aliado_id');
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['aliado_id']);
            $table->dropColumn('aliado_id');
        });
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropForeign(['aliado_id']);
            $table->dropColumn('aliado_id');
        });
    }
};
