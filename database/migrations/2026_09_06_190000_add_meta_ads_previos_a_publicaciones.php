<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anuncios anteriores de una pieza, para no perderles el gasto.
 *
 * Un anuncio de Meta no se puede mudar de conjunto: hay que recrearlo en el conjunto nuevo y
 * pausar el viejo. Al separar las piezas de asesores a su propio conjunto (6-sep-2026) las
 * #90 y #91 estrenaron `meta_ad_id`, y como `sincronizarGasto` escribe el gasto del anuncio
 * actual, la siguiente corrida les habría puesto $0 encima de los $1.825 y $310 ya gastados.
 *
 * Guardando los ids anteriores el gasto de la pieza vuelve a ser el de la pieza y no el de su
 * anuncio de turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->json('meta_ads_previos')->nullable()->after('meta_ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('meta_ads_previos');
        });
    }
};
