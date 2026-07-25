<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca combinaciones plan+modalidad que existen en el sistema pero NUNCA deben ofrecerse
 * por la web pública — solo la IA de WhatsApp puede usarlas, como recurso para no perder un
 * cliente (ej. TP(7-14): plan más económico de Tiempo Parcial, solo si el cliente ya mostró
 * que no quiere pagar el valor normal). Por defecto false = visible en ambos canales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modalidad_planes', function (Blueprint $table) {
            $table->boolean('solo_ia')->default(false)->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('modalidad_planes', function (Blueprint $table) {
            $table->dropColumn('solo_ia');
        });
    }
};
