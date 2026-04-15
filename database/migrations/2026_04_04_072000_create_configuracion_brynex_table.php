<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_brynex', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->string('valor', 500)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        // ── Valores iniciales ──
        $now = now();
        DB::table('configuracion_brynex')->insert([
            // Salario mínimo 2025
            ['clave' => 'salario_minimo',                    'valor' => '1423500',  'descripcion' => 'Salario mínimo mensual legal vigente (SMMLV)', 'created_at' => $now, 'updated_at' => $now],

            // Dependientes
            ['clave' => 'pct_salud_dependiente',             'valor' => '4.00',     'descripcion' => 'Porcentaje EPS trabajador dependiente (%)',       'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'pct_pension_dependiente',           'valor' => '16.00',    'descripcion' => 'Porcentaje pensión trabajador dependiente (%)',    'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'pct_caja_dependiente',              'valor' => '4.00',     'descripcion' => 'Porcentaje caja compensación dependiente (%)',     'created_at' => $now, 'updated_at' => $now],

            // Independientes
            ['clave' => 'pct_salud_independiente',           'valor' => '12.50',    'descripcion' => 'Porcentaje EPS trabajador independiente (%)',      'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'pct_pension_independiente',         'valor' => '16.00',    'descripcion' => 'Porcentaje pensión trabajador independiente (%)',  'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'pct_caja_independiente_alto',       'valor' => '2.00',     'descripcion' => 'Porcentaje caja compensación independiente ALTO', 'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'pct_caja_independiente_bajo',       'valor' => '0.60',     'descripcion' => 'Porcentaje caja compensación independiente BAJO', 'created_at' => $now, 'updated_at' => $now],

            // IBC sugerido para independientes
            ['clave' => 'pct_ibc_independiente_sugerido',   'valor' => '40.00',    'descripcion' => 'Porcentaje sugerido del salario para calcular IBC del independiente (%)', 'created_at' => $now, 'updated_at' => $now],

            // IVA
            ['clave' => 'porcentaje_iva',                    'valor' => '19.00',    'descripcion' => 'Porcentaje IVA que aplica sobre la administración (solo clientes con IVA=SI)', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_brynex');
    }
};
