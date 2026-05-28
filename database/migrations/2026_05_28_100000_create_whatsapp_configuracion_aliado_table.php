<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de credenciales de WhatsApp Business API por aliado.
     *
     * Modelo híbrido:
     *  - usa_cuenta_brynex = true  → usa las credenciales globales de Brynex (del .env)
     *  - usa_cuenta_brynex = false → usa las credenciales propias del aliado
     *
     * Solo el superadmin de Brynex puede configurar estos datos.
     * El access_token se almacena encriptado con encrypt() de Laravel.
     */
    public function up(): void
    {
        Schema::create('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->unique();

            // Credenciales de Meta Cloud API
            $table->string('waba_id', 100)->nullable();          // WhatsApp Business Account ID
            $table->string('phone_number_id', 100)->nullable();  // ID del número en Meta
            $table->text('access_token')->nullable();            // Token permanente (encriptado)
            $table->string('numero_telefono', 25)->nullable();   // Ej: +573001234567
            $table->string('nombre_cuenta', 150)->nullable();    // Nombre del perfil WA Business

            // Control de cuenta
            $table->boolean('usa_cuenta_brynex')->default(true); // true = heredar credenciales Brynex
            $table->boolean('activo')->default(true);
            $table->boolean('webhook_verificado')->default(false);

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_configuracion_aliado');
    }
};
