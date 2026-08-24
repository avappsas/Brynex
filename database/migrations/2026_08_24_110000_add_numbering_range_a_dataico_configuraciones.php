<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rango de numeración DIAN con el que Dataico emite.
 *
 * El número de la factura lo asigna Dataico (se manda `number` vacío), pero
 * una cuenta puede tener varias resoluciones vigentes, así que hay que decirle
 * cuál usar. Se guarda el `numbering_range_id`; `prefijo` y `resolucion` son
 * solo para mostrar en pantalla cuál quedó configurada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->string('numbering_range_id', 100)->nullable()->after('auth_token');
            $table->string('prefijo', 20)->nullable()->after('numbering_range_id');
            $table->string('resolucion', 50)->nullable()->after('prefijo');
        });
    }

    public function down(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['numbering_range_id', 'prefijo', 'resolucion']);
        });
    }
};
