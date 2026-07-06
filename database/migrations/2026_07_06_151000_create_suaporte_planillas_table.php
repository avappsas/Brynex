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
        // 1. Tabla para almacenar las claves de API / credenciales dinámicas por Operador y Razón Social (Multiservicio)
        if (!Schema::hasTable('operadores_credenciales')) {
            Schema::create('operadores_credenciales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aliado_id')->index();
                $table->unsignedBigInteger('razon_social_id')->index();
                $table->unsignedBigInteger('operador_planilla_id')->index();
                
                $table->string('usuario')->nullable(); // Ej: NIT de la Razón Social / Aportante o ID de usuario
                $table->text('clave_secreta'); // La clave secreta/API Key de uso de APIS
                
                // Parámetros dinámicos en JSON por si otros operadores piden campos extra (ej. client_secret, canal, ambiente, etc.)
                $table->json('config')->nullable(); 
                
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Tabla Genérica para almacenar la respuesta e historial de planillas liquidadas en cualquier API (Suaporte, Aportes en Línea, etc.)
        if (!Schema::hasTable('operador_planillas_api')) {
            Schema::create('operador_planillas_api', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aliado_id')->index();
                $table->unsignedBigInteger('razon_social_id')->index();
                $table->unsignedBigInteger('operador_planilla_id')->index();
                
                $table->integer('anio');
                $table->integer('mes');
                $table->integer('n_plano');
                
                // Metadatos genéricos de la liquidación en el API del operador
                $table->string('api_planilla_id')->nullable()->index(); // ID interno retornado por la API del operador
                $table->string('numero_planilla')->nullable()->index(); // Número de planilla oficial PILA generado
                $table->decimal('valor_total', 15, 2)->nullable();
                $table->text('url_pago')->nullable(); // URL del PSE
                
                // Estados del flujo: 'pendiente_envio', 'procesando', 'validada', 'pagada', 'error'
                $table->string('estado', 50)->default('pendiente_envio');
                $table->text('mensaje_error')->nullable();
                $table->json('response_log')->nullable(); // JSON con la respuesta completa para trazabilidad

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
        Schema::dropIfExists('operador_planillas_api');
        Schema::dropIfExists('operadores_credenciales');
    }
};
