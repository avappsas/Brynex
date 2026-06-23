<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_gestiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // Asesor que atendió
            
            $table->string('tipo_gestion', 20)->nullable(); // llamada, whatsapp, visita, correo, otro
            $table->text('descripcion')->nullable(); // Qué dijo el cliente
            $table->string('resultado', 30)->nullable(); // interesado, no_interesado, sin_respuesta, pendiente_resp
            
            $table->date('proxima_llamada')->nullable();
            
            $table->timestamps();
            
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones_prospectos')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_gestiones');
    }
};
