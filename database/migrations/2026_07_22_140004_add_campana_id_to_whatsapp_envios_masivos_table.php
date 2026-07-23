<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula cada tanda de envío masivo a la campaña de marketing que la originó.
     * Nullable: los envíos de cobro existentes (planillas, recordatorios) no tienen campaña.
     */
    public function up(): void
    {
        Schema::table('whatsapp_envios_masivos', function (Blueprint $table) {
            $table->unsignedBigInteger('campana_id')->nullable()->after('plantilla_id');
            $table->foreign('campana_id')->references('id')->on('marketing_campanas')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_envios_masivos', function (Blueprint $table) {
            $table->dropForeign(['campana_id']);
            $table->dropColumn('campana_id');
        });
    }
};
