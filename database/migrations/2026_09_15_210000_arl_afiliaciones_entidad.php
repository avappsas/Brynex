<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `arl_afiliaciones` nació solo para ARL Sura. Ahora también guarda los
 * movimientos de ARL Colmena, así que cada fila dice ante qué ARL se hizo.
 *
 * Todo lo que ya existe es de Sura, de ahí el valor por defecto: no hay nada
 * que rellenar a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arl_afiliaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('arl_afiliaciones', 'entidad')) {
                $table->string('entidad', 20)->default('arl_sura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('arl_afiliaciones', function (Blueprint $table) {
            $table->dropColumn('entidad');
        });
    }
};
