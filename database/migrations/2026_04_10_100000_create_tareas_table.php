<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable()->index();
            // Tipo de tarea
            $table->string('tipo', 50); // traslado_eps, inclusion_beneficiarios, exclusion, subsidios, actualizar_documentos, devolucion_aportes, solicitud_documentos, otros
            // Estado: pendiente, en_gestion, en_espera, cerrada
            $table->string('estado', 30)->default('pendiente')->index();
            // Resultado al cerrar: null, positivo, negativo
            $table->string('resultado', 20)->nullable();
            // Cliente
            $table->string('cedula', 20)->index();
            // Vínculos opcionales
            $table->unsignedBigInteger('contrato_id')->nullable()->index();
            $table->unsignedBigInteger('razon_social_id')->nullable()->index();
            // Entidad donde se realiza el trámite (EPS, ARL, Caja, nombre libre)
            $table->string('entidad', 150)->nullable();
            // Contenido
            $table->text('tarea');
            $table->text('observacion')->nullable();
            // Asignación
            $table->unsignedBigInteger('encargado_id')->index();
            $table->unsignedBigInteger('creado_por');
            // Semáforo y alertas
            $table->date('fecha_limite')->nullable();
            $table->date('fecha_alerta')->nullable();
            // Radicado
            $table->date('fecha_radicado')->nullable();
            $table->string('numero_radicado', 80)->nullable();
            $table->string('correo', 150)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
