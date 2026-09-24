<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién cambió una clave de portal, y qué había antes.
 *
 * Hace falta desde que la clave es de la empresa y no del aliado: la misma
 * razón social la comparten varios aliados y cualquiera de ellos puede
 * actualizarla, así que un trámite puede dejar de funcionar por un cambio que
 * hizo otro. Sin esto, ni se sabía quién fue ni se podía volver a la anterior.
 *
 * Guarda el valor anterior en claro, igual que `clave_accesos`: sirve
 * justamente para restaurarlo. Se lee con el mismo permiso que la clave actual
 * (`claves_acceso.ver_contrasena`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clave_acceso_cambios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clave_acceso_id');
            // Desde qué aliado se hizo el cambio y quién lo hizo.
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('usuario_anterior', 190)->nullable();
            $table->string('contrasena_anterior', 190)->nullable();
            $table->string('usuario_nuevo', 190)->nullable();
            $table->string('contrasena_nueva', 190)->nullable();

            // El resto de campos que cambiaron, como {"activo":[true,false]}.
            $table->text('otros_cambios')->nullable();
            $table->string('motivo', 200)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['clave_acceso_id', 'id'], 'IX_clave_cambios_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clave_acceso_cambios');
    }
};
