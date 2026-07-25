<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eslogan de la marca, para el encabezado de las piezas publicitarias (va debajo del nombre,
 * junto al logo). Si está vacío, el flyer simplemente no lo pinta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('eslogan', 120)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('eslogan');
        });
    }
};
