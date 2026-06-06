<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renombrar el tipo 'pago_eps' → 'entrada_incapacidad' en la tabla abonos_incapacidades.
     * Canal 5: Las entradas de dinero de incapacidades (EPS/ARL/AFP) ahora se llaman
     * 'entrada_incapacidad' para reflejar que no solo viene de EPS sino de cualquier entidad.
     */
    public function up(): void
    {
        DB::table('abonos_incapacidades')
            ->where('tipo', 'pago_eps')
            ->update(['tipo' => 'entrada_incapacidad']);
    }

    /**
     * Revertir el cambio (rollback).
     */
    public function down(): void
    {
        DB::table('abonos_incapacidades')
            ->where('tipo', 'entrada_incapacidad')
            ->update(['tipo' => 'pago_eps']);
    }
};
