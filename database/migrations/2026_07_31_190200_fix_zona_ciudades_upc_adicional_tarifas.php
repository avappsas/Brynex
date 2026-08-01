<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migración que sembró `upc_adicional_tarifas` usó 'ciudades' como valor
 * de zona (tal cual el encabezado de columna del PDF fuente), pero la que
 * clasificó `ciudades.zona_upc_adicional` usó 'grandes_ciudades'. Alinea
 * ambas al segundo nombre, que es el que usa el resto del código.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('upc_adicional_tarifas')
            ->where('zona', 'ciudades')
            ->update(['zona' => 'grandes_ciudades']);
    }

    public function down(): void
    {
        DB::table('upc_adicional_tarifas')
            ->where('zona', 'grandes_ciudades')
            ->update(['zona' => 'ciudades']);
    }
};
