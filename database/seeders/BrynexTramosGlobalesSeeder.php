<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea los tramos de tarifa globales para el módulo de Administración.
 *
 * Tabla de precios confirmada:
 *  Tramo 1: 0 – 100 contratos   → sin tarifa por unidad, mínimo $200.000
 *  Tramo 2: 101 – 999 contratos  → $650/contrato, mínimo $200.000
 *  Tramo 3: 1.000 – 1.999        → $600/contrato, mínimo $200.000
 *  Tramo 4: 2.000 en adelante    → $550/contrato, mínimo $200.000
 *
 * Regla del mínimo: cobro_final = MAX($200.000, cantidad × tarifa_unidad)
 * El mínimo aplica a TODOS los tramos (es un piso general del módulo).
 */
class BrynexTramosGlobalesSeeder extends Seeder
{
    public function run(): void
    {
        $moduloAdminId = 1; // 'administracion'
        $now = now();
        $vigente = '2026-01-01';

        $tramos = [
            [
                'modulo_id'      => $moduloAdminId,
                'aliado_id'      => null,   // global para todos
                'desde_cant'     => 0,
                'hasta_cant'     => 100,
                'tarifa_unidad'  => null,   // no aplica tarifa por unidad en este tramo
                'tarifa_minima'  => 200000,
                'vigente_desde'  => $vigente,
                'vigente_hasta'  => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'modulo_id'      => $moduloAdminId,
                'aliado_id'      => null,
                'desde_cant'     => 101,
                'hasta_cant'     => 999,
                'tarifa_unidad'  => 650,
                'tarifa_minima'  => 200000,
                'vigente_desde'  => $vigente,
                'vigente_hasta'  => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'modulo_id'      => $moduloAdminId,
                'aliado_id'      => null,
                'desde_cant'     => 1000,
                'hasta_cant'     => 1999,
                'tarifa_unidad'  => 600,
                'tarifa_minima'  => 200000,
                'vigente_desde'  => $vigente,
                'vigente_hasta'  => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'modulo_id'      => $moduloAdminId,
                'aliado_id'      => null,
                'desde_cant'     => 2000,
                'hasta_cant'     => null, // sin límite
                'tarifa_unidad'  => 550,
                'tarifa_minima'  => 200000,
                'vigente_desde'  => $vigente,
                'vigente_hasta'  => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];

        // Limpiar tramos globales del módulo admin para evitar duplicados
        DB::table('brynex_tramos_tarifa')
            ->where('modulo_id', $moduloAdminId)
            ->whereNull('aliado_id')
            ->delete();

        DB::table('brynex_tramos_tarifa')->insert($tramos);
    }
}
