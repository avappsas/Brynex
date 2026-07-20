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
        Schema::create('planilla_envios_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('plantilla_id')->nullable();

            $table->tinyInteger('mes')->unsigned();
            $table->smallInteger('anio')->unsigned();
            $table->string('tipo_envio', 20)->default('individual'); // individual | empleado_empresa | contacto_empresa

            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_fallidos')->default(0);
            $table->unsignedInteger('total_omitidos')->default(0);

            $table->string('estado', 20)->default('pendiente'); // pendiente, procesando, completado, fallido
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('plantilla_id')->references('id')->on('whatsapp_plantillas')->noActionOnDelete();

            $table->index(['aliado_id', 'estado']);
            $table->index(['aliado_id', 'mes', 'anio']);
        });

        Schema::create('planilla_envios_whatsapp_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('envio_id');
            $table->unsignedBigInteger('plano_id')->nullable();
            $table->unsignedInteger('contrato_id')->nullable();
            $table->string('cliente_cedula', 20)->nullable();
            $table->unsignedInteger('empresa_id')->nullable();

            $table->string('wa_numero', 25);
            $table->string('nombre_destinatario', 200);
            $table->string('numero_planilla', 80)->nullable();
            $table->string('operador_nombre', 50)->nullable();
            $table->tinyInteger('periodo_mes')->unsigned();
            $table->smallInteger('periodo_anio')->unsigned();

            $table->string('estado', 20)->default('pendiente'); // pendiente, enviado, fallido, omitido
            $table->string('wa_message_id', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->timestamps();

            $table->foreign('envio_id')->references('id')->on('planilla_envios_whatsapp')->noActionOnDelete();
            $table->foreign('plano_id')->references('id')->on('planos')->noActionOnDelete();

            $table->index('envio_id');
            $table->index(['cliente_cedula', 'periodo_mes', 'periodo_anio', 'estado'], 'idx_planilla_envio_dup');
            $table->index(['plano_id', 'estado']);
            $table->index(['wa_numero', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planilla_envios_whatsapp_detalle');
        Schema::dropIfExists('planilla_envios_whatsapp');
    }
};
