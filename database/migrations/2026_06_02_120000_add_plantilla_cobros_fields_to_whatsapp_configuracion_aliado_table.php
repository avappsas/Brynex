<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->unsignedBigInteger('cobro_plantilla_id')->nullable()->after('webhook_verificado');
            $table->string('cobro_header_imagen', 500)->nullable()->after('cobro_plantilla_id');

            $table->foreign('cobro_plantilla_id')
                ->references('id')
                ->on('whatsapp_plantillas')
                ->onDelete('no action');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->dropForeign(['cobro_plantilla_id']);
            $table->dropColumn(['cobro_plantilla_id', 'cobro_header_imagen']);
        });
    }
};
