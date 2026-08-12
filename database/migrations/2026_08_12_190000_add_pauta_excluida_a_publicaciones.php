<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Esta pieza no se pauta", dicho de frente.
 *
 * Hasta ahora el único modo de sacar una pieza de la fila era dejarle puesto un `meta_ad_id`
 * de un anuncio ya borrado: el comando elige candidatas por "publicada y sin meta_ad_id", así
 * que un id muerto la mantenía fuera. Funcionaba, pero deja la base apuntando a anuncios que
 * no existen y no distingue "ya la pauté" de "no la quiero pautar" — que son cosas distintas
 * cuando hay que entender después por qué una pieza nunca se promocionó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->boolean('pauta_excluida')->default(false)->after('pauta_estado');
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('pauta_excluida');
        });
    }
};
