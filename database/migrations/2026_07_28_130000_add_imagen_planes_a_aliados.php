<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen de la tabla de planes (marca/niveles ARL/precios) que el Asistente IA puede enviar
 * por WhatsApp cuando el cliente quiere ver las opciones escritas o de un vistazo, en vez de
 * solo escuchar un valor. Es una imagen de referencia/orientación: el valor exacto que se
 * ofrece siempre sale de cotizar_plan, nunca de esta imagen (puede quedar desactualizada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('imagen_planes')->nullable()->after('logo_marca_claro');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('imagen_planes');
        });
    }
};
