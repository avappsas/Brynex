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
        Schema::connection('finanzas')->create('finanzas_proyecto_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proyecto_id');
            $table->string('tipo', 10); // entrada | salida
            $table->date('fecha');
            $table->decimal('monto', 18, 2);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            // Clave foránea interna
            $table->foreign('proyecto_id')->references('id')->on('finanzas_proyectos')->onDelete('cascade');

            $table->index(['proyecto_id', 'fecha'], 'ix_proy_mov_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_proyecto_movimientos');
    }
};
