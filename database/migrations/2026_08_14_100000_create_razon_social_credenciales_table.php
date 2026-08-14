<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de portales críticos de la razón social: DIAN, bancos y cámara de
 * comercio.
 *
 * Va en tabla aparte de `clave_accesos` a propósito. Esa tabla guarda las
 * claves de EPS/ARL/caja, las trae completas el rol `usuario` (el trabajador
 * las necesita para afiliar) y la contraseña está en texto plano. Una clave
 * de Bancolombia no puede vivir bajo esas dos reglas: aquí la contraseña se
 * guarda cifrada (cast `encrypted`) y el acceso pasa por `credenciales_rs.*`,
 * que ningún rol hereda salvo el superadmin del aliado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('razon_social_credenciales')) {
            return;
        }

        Schema::create('razon_social_credenciales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->index();
            $table->unsignedBigInteger('razon_social_id')->index();

            // DIAN | BANCO | CAMARA_COMERCIO | OTRO — agrupa y filtra.
            $table->string('tipo', 40)->default('OTRO');
            // Nombre concreto del portal: Bancolombia, Nequi, Banco de Bogotá…
            $table->string('entidad', 150);

            $table->string('link_acceso', 350)->nullable();
            $table->string('usuario', 150)->nullable();
            // Cifrada: el texto cifrado de Laravel pasa largo de 200 chars.
            $table->text('contrasena')->nullable();
            $table->string('observacion', 500)->nullable();

            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['aliado_id', 'razon_social_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razon_social_credenciales');
    }
};
