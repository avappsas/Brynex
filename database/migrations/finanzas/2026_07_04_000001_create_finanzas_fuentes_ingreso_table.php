<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_fuentes_ingreso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // FK lógica a la tabla de usuarios en la base de datos principal
            $table->string('nombre', 100);
            $table->string('tipo', 30)->default('fijo'); // fijo | proyecto | esporadico
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            // Índices para búsquedas eficientes
            $table->index(['user_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_fuentes_ingreso');
    }
};
