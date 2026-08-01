<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidación de un contratista independiente puntual (no un lote de empresa):
 * varios independientes comparten la misma razon_social_id "INDEPENDIENTE" del
 * aliado, así que razon_social_id+operador+periodo+n_plano ya no identifica una
 * sola liquidación. plano_id (fila puntual de `planos`) desambigua ese caso;
 * queda NULL para las liquidaciones de empresa (comportamiento sin cambios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->unsignedBigInteger('plano_id')->nullable()->after('razon_social_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->dropColumn('plano_id');
        });
    }
};
