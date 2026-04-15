<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clave_accesos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->index();
            // Puede vincularse a un cliente (por cédula) o a una razón social
            $table->bigInteger('cedula')->nullable()->index();
            $table->unsignedInteger('razon_social_id')->nullable()->index();
            // Clasificación del acceso
            $table->string('tipo', 80)->default('Portal');   // ARL, EPS, CAJA, DIAN, Portal, Correo, Otro
            $table->string('entidad', 150);                  // Nombre del portal / entidad
            // Credenciales
            $table->string('usuario', 150)->nullable();
            $table->string('contrasena', 200)->nullable();
            $table->string('link_acceso', 350)->nullable();
            $table->string('correo_entidad', 150)->nullable();
            $table->string('observacion', 300)->nullable();
            // Control
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índice compuesto para búsquedas frecuentes
            $table->index(['aliado_id', 'cedula']);
            $table->index(['aliado_id', 'razon_social_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clave_accesos');
    }
};
