<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Paga mes actual" deja de ser una modalidad y pasa a ser un atributo del
 * contrato: dice CUÁNDO se paga, no QUÉ se cotiza, así que es ortogonal a la
 * modalidad y sirve igual para Independientes (10), Y (8) y Exterior (14).
 *
 * El plano se lleva su propia copia porque es un snapshot: el contrato puede
 * cambiar después y el período que cubrió ese plano no se puede recalcular.
 *
 * Esta migración NO cambia comportamiento: solo deja el campo poblado con lo
 * que hoy significa la modalidad 11. El código sigue leyendo `= 11` hasta la
 * fase siguiente, y los datos de la 11 no se mueven hasta que ya nadie la lea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('paga_mes_actual')->default(false);
        });

        Schema::table('planos', function (Blueprint $table) {
            $table->boolean('paga_mes_actual')->default(false);
        });

        // Backfill: hoy el mes actual es exactamente la modalidad 11.
        DB::table('contratos')->where('tipo_modalidad_id', 11)->update(['paga_mes_actual' => true]);
        DB::table('planos')->where('tipo_modalidad_id', 11)->update(['paga_mes_actual' => true]);
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('paga_mes_actual');
        });

        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('paga_mes_actual');
        });
    }
};
