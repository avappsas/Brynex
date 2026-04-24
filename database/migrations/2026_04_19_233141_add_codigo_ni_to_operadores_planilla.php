<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Reestructura operadores_planilla:
 *  - Agrega columna codigo_ni (código numérico PILA para el Excel de planilla)
 *  - Limpia y resiembra los 8 operadores estándar con sus códigos correctos
 *    (solo ARUS Enlace tiene código conocido = 89; los demás quedan null)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna codigo_ni si no existe
        Schema::table('operadores_planilla', function (Blueprint $table) {
            $table->unsignedSmallInteger('codigo_ni')->nullable()->after('codigo')
                  ->comment('Código numérico del operador en el sistema PILA (ej: 89 ARUS)');
        });

        // 2. Limpiar registros anteriores (eran solo 5, sin estructura estándar)
        DB::table('operadores_planilla')->whereNull('aliado_id')->delete();

        // 3. Insertar los 8 operadores estándar globales
        DB::table('operadores_planilla')->insert([
            [
                'aliado_id'  => null,
                'nombre'     => 'Simple',
                'codigo'     => 'SIMPLE',
                'codigo_ni'  => null,   // pendiente de confirmar
                'activo'     => true,
                'orden'      => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'Mi Planilla',
                'codigo'     => 'MIPLANI',
                'codigo_ni'  => null,
                'activo'     => true,
                'orden'      => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'Asopagos',
                'codigo'     => 'ASOPAGO',
                'codigo_ni'  => null,
                'activo'     => true,
                'orden'      => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'Aportes en Línea',
                'codigo'     => 'APL',
                'codigo_ni'  => null,
                'activo'     => true,
                'orden'      => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'ARUS Enlace',
                'codigo'     => 'ARUS',
                'codigo_ni'  => 89,     // ✅ código PILA confirmado
                'activo'     => true,
                'orden'      => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'Enlace',
                'codigo'     => 'ENLACE',
                'codigo_ni'  => 89,     // comparte código con ARUS
                'activo'     => true,
                'orden'      => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'SOI',
                'codigo'     => 'SOI',
                'codigo_ni'  => null,
                'activo'     => true,
                'orden'      => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'aliado_id'  => null,
                'nombre'     => 'Otros',
                'codigo'     => 'OTROS',
                'codigo_ni'  => null,
                'activo'     => true,
                'orden'      => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('operadores_planilla', function (Blueprint $table) {
            $table->dropColumn('codigo_ni');
        });
    }
};
