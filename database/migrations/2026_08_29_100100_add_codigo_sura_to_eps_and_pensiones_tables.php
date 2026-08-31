<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sura no usa los mismos códigos que traemos: EMSSANAR es `ESSC18` en BryNex y
 * `148` en Sura; PORVENIR es `230301` (PILA) y `003` en Sura. Sin esta
 * equivalencia el afiliar rechaza la EPS y la AFP.
 * Se pueblan con `arl:sincronizar-catalogos` desde /sel-services/eps/listado y
 * /sel-services/afp/listado, que devuelven código y NIT de cada entidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eps', function (Blueprint $table) {
            $table->string('codigo_sura', 10)->nullable()->after('codigo');
            $table->string('dni_sura', 20)->nullable()->after('codigo_sura');
        });

        Schema::table('pensiones', function (Blueprint $table) {
            $table->string('codigo_sura', 10)->nullable()->after('codigo');
            $table->string('dni_sura', 20)->nullable()->after('codigo_sura');
        });
    }

    public function down(): void
    {
        Schema::table('eps', function (Blueprint $table) {
            $table->dropColumn(['codigo_sura', 'dni_sura']);
        });

        Schema::table('pensiones', function (Blueprint $table) {
            $table->dropColumn(['codigo_sura', 'dni_sura']);
        });
    }
};
