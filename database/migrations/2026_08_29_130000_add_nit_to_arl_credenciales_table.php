<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las credenciales del portal pasan a poder ser de una EMPRESA, no solo de un
 * aliado.
 *
 * Cada empresa entra al portal de ARL Sura con su propio usuario, y la misma
 * empresa está registrada como razón social en varios aliados a la vez. Atando
 * la credencial al NIT, cargarla una vez sirve para todos: si alguien la cambia,
 * queda cambiada en todas partes.
 *
 * `nit` en NULL mantiene el significado anterior: la credencial del aliado, que
 * se usa como respaldo cuando la empresa no tiene una propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arl_credenciales', function (Blueprint $table) {
            $table->string('nit', 20)->nullable()->after('aliado_id');
            $table->string('poliza', 20)->nullable()->after('nit');
        });

        // El unique por aliado ya no vale: un aliado puede tener la suya y
        // además las de varias empresas.
        DB::statement('DROP INDEX uq_arl_credencial_aliado ON arl_credenciales');
        DB::statement('CREATE INDEX ix_arl_credencial_aliado ON arl_credenciales (aliado_id)');
        DB::statement('CREATE INDEX ix_arl_credencial_nit ON arl_credenciales (nit)');
    }

    public function down(): void
    {
        Schema::table('arl_credenciales', function (Blueprint $table) {
            $table->dropColumn(['nit', 'poliza']);
        });
    }
};
