<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot: qué operadores de planilla tiene activos cada aliado.
 *
 * Si un aliado NO tiene ningún registro → se muestran TODOS los operadores globales.
 * Si tiene registros → solo se muestran los que tienen activo = true.
 * Esto permite activar/desactivar operadores por aliado sin duplicar filas en operadores_planilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliado_operadores_planilla', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('operador_id'); // FK → operadores_planilla.id
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['aliado_id', 'operador_id']);
            $table->foreign('operador_id')
                  ->references('id')->on('operadores_planilla')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliado_operadores_planilla');
    }
};
