<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operadores_planilla', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->nullable()->index();
            $table->string('nombre', 100);
            $table->string('codigo', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(99);
            $table->timestamps();
        });

        // Operadores predeterminados globales (aliado_id = NULL → aplican a todos)
        $now = now();
        DB::table('operadores_planilla')->insert([
            ['aliado_id' => null, 'nombre' => 'SOI',                'codigo' => 'SOI',     'activo' => 1, 'orden' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nombre' => 'Aportes en Línea',   'codigo' => 'APL',     'activo' => 1, 'orden' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nombre' => 'Simple',             'codigo' => 'SIMPLE',  'activo' => 1, 'orden' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nombre' => 'Mi Planilla',        'codigo' => 'MIPLANI', 'activo' => 1, 'orden' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nombre' => 'Asopagos',           'codigo' => 'ASOPAGO', 'activo' => 1, 'orden' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operadores_planilla');
    }
};
