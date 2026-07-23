<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista negra de marketing: números que no deben volver a recibir campañas. Se
     * consulta SIEMPRE antes de cualquier envío (carga de listas y lanzamiento de tandas).
     * Un número puede entrar aquí por la IA, por un asesor, por el botón "No me interesa"
     * de una plantilla, o por carga manual — origen queda registrado para auditoría.
     */
    public function up(): void
    {
        Schema::create('marketing_bloqueados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('celular', 25);
            $table->string('motivo', 500)->nullable();
            $table->string('origen', 20)->default('carga_manual'); // ia | asesor | boton_no_interesa | carga_manual
            $table->unsignedBigInteger('bloqueado_por')->nullable(); // usuario si el origen fue 'asesor'
            $table->unsignedBigInteger('conversacion_id')->nullable(); // auditoría, si aplica

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('bloqueado_por')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('conversacion_id')->references('id')->on('whatsapp_conversaciones')->noActionOnDelete();

            $table->unique(['aliado_id', 'celular']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_bloqueados');
    }
};
