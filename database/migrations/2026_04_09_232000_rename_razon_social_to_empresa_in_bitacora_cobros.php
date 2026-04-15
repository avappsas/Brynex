<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            // Renombrar razon_social_id → empresa_id (apunta a tabla empresas, no razones_sociales)
            $table->dropIndex(['aliado_id', 'razon_social_id']);
            $table->renameColumn('razon_social_id', 'empresa_id');
            $table->index(['aliado_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            $table->dropIndex(['aliado_id', 'empresa_id']);
            $table->renameColumn('empresa_id', 'razon_social_id');
            $table->index(['aliado_id', 'razon_social_id']);
        });
    }
};
