<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function viewUp(): void
    {
        // 1. Tabla para almacenar las plantillas globales por Operador de Planilla (Overlay PDF)
        if (!Schema::hasTable('operador_planillas_templates')) {
            Schema::create('operador_planillas_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('operador_planilla_id')->unique(); // 1 plantilla por operador
                $table->string('nombre'); // ej. "Plantilla Enlace Operativo"
                $table->string('formulario_pdf')->nullable(); // Archivo PDF en storage (ej. ENLACE.pdf)
                $table->json('formulario_campos')->nullable(); // Coordenadas y formato JSON
                
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operador_planillas_templates');
    }
    
    // Método alternativo para cumplir con el framework local de Brynex
    public function up(): void
    {
        $this->viewUp();
    }
};
