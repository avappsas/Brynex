<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_gestiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tarea_id')->index();
            $table->unsignedBigInteger('user_id');
            // tramite_realizado, traslado, cambio_estado, nota
            $table->string('tipo_accion', 50)->default('tramite_realizado');
            $table->text('observacion');
            // Recordatorio: cuántos días pedidos + fecha calculada
            $table->unsignedInteger('recordar_dias')->nullable();
            $table->date('fecha_alerta')->nullable();
            // Para trasladados: encargado anterior y nuevo
            $table->unsignedBigInteger('encargado_anterior')->nullable();
            $table->unsignedBigInteger('encargado_nuevo')->nullable();
            // Estado que se registró al momento de la gestión
            $table->string('estado_tarea', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_gestiones');
    }
};
