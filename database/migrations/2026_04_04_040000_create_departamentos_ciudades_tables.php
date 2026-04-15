<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary(); // Código DANE del departamento
            $table->string('nombre', 100);
            $table->string('dept_aportes', 100)->nullable();
            $table->string('dept_asopagos', 100)->nullable();
        });

        Schema::create('ciudades', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // Código DANE completo (ej: 76001)
            $table->unsignedSmallInteger('departamento_id');
            $table->string('nombre', 100);
            $table->string('ciudad_aportes', 100)->nullable();
            $table->string('ciudad_asopagos', 100)->nullable();

            $table->foreign('departamento_id')->references('id')->on('departamentos');
            $table->index('departamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
        Schema::dropIfExists('departamentos');
    }
};
