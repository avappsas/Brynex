<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            // Para llamadas a nivel empresa (no contrato individual)
            $table->unsignedBigInteger('razon_social_id')->nullable()->after('aliado_id');
            $table->index(['aliado_id', 'razon_social_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            $table->dropIndex(['aliado_id', 'razon_social_id']);
            $table->dropColumn('razon_social_id');
        });
    }
};
