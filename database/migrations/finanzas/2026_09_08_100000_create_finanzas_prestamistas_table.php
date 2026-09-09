<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del MUTUANTE (acreedor) que encabeza los documentos legales de un
 * préstamo formal. Se guardan una sola vez para no volver a escribirlos en
 * cada expediente: el préstamo sale a nombre de un tercero (la madre del
 * dueño), no del usuario de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_prestamistas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 120);
            $table->string('cedula', 20)->nullable();
            $table->string('expedida_en', 60)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('ciudad', 60)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 120)->nullable();
            $table->boolean('por_defecto')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'activo'], 'ix_prestamista_user_activo');
        });
    }

    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_prestamistas');
    }
};
