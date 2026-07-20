<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->unsignedBigInteger('planilla_envio_plantilla_id')->nullable()->after('cobro_header_imagen');

            $table->foreign('planilla_envio_plantilla_id')
                ->references('id')
                ->on('whatsapp_plantillas')
                ->noActionOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->dropForeign(['planilla_envio_plantilla_id']);
            $table->dropColumn(['planilla_envio_plantilla_id']);
        });
    }
};
