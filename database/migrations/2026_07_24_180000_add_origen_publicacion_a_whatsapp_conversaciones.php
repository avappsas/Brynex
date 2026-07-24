<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribución de conversaciones de WhatsApp a una pieza de marketing concreta: cada pieza
 * lleva un link wa.me con un código de referencia ("ref: P{id}") en el mensaje precargado;
 * si el primer mensaje entrante de un contacto nuevo trae ese código, se guarda aquí — así
 * sabemos EXACTAMENTE qué publicación trajo cada cliente real, no solo una correlación por
 * ventana de tiempo. Solo aplica a conversaciones nuevas (no pisa el origen si ya existía).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('origen_publicacion_id')->nullable()->after('origen_campana_id');
            $table->foreign('origen_publicacion_id')->references('id')->on('publicaciones')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropForeign(['origen_publicacion_id']);
            $table->dropColumn('origen_publicacion_id');
        });
    }
};
