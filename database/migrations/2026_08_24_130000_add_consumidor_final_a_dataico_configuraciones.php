<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ¿Se emiten a consumidor final los adquirientes sin documento?
 *
 * Va como interruptor y no cableado en el código a propósito. BRYGAR no había
 * usado nunca esa figura — de las 1.128 facturas que ya tiene ante la DIAN,
 * ninguna lleva el documento 222222222222 — así que estrenarla es una decisión
 * del dueño (24-ago-2026, para las empresas cuyo documento no se pudo
 * conseguir), no un comportamiento por defecto. Con el interruptor apagado
 * esos grupos quedan retenidos y listados en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->boolean('consumidor_final')->default(false)->after('enviar_email');
        });
    }

    public function down(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->dropColumn('consumidor_final');
        });
    }
};
