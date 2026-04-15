<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion', 20);                 // created | updated | deleted
            $table->string('modelo', 50);                 // Cliente | Beneficiario | Documento
            $table->bigInteger('registro_id')->nullable(); // ID del registro afectado
            $table->string('descripcion', 255);           // Resumen legible
            $table->text('detalle')->nullable();           // JSON: campos cambiados / snapshot deleted
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent(); // Solo insert, sin updated_at

            // Índice optimizado para consultas por aliado + módulo + fecha
            $table->index(['aliado_id', 'modelo', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
