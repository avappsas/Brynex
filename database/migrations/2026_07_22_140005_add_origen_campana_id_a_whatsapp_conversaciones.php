<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo fuerte a la campaña (además de origen_campana/origen_campana_categoria, que
     * solo guardan texto). Con esto la IA puede leer descripcion_ia/objetivo/guia_botones
     * de la campaña real en vez de solo el nombre de la plantilla. Nullable: conversaciones
     * orgánicas o de cobro no tienen campaña.
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('origen_campana_id')->nullable()->after('origen_campana_categoria');
            $table->foreign('origen_campana_id')->references('id')->on('marketing_campanas')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropForeign(['origen_campana_id']);
            $table->dropColumn('origen_campana_id');
        });
    }
};
