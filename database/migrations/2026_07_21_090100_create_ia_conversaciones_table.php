<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('canal', 20)->default('web'); // web|whatsapp
            $table->unsignedBigInteger('user_id')->nullable();   // canal web
            $table->string('telefono', 30)->nullable();          // canal whatsapp (fase 3)
            $table->boolean('bot_activo')->default(true);        // handoff bot/humano (fase 3)
            $table->timestamp('ultima_actividad')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['aliado_id', 'canal', 'user_id']);
        });

        Schema::create('ia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id');
            $table->string('rol', 20); // user|assistant|tool
            $table->longText('contenido')->nullable();
            $table->string('tool_name', 100)->nullable();
            $table->json('tool_data')->nullable(); // ej: {ruta: 'admin.facturacion.index'} para botón "Abrir"
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_salida')->nullable();
            $table->timestamps();

            $table->foreign('conversacion_id')->references('id')->on('ia_conversaciones')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_mensajes');
        Schema::dropIfExists('ia_conversaciones');
    }
};
