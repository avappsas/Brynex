<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del portal de ARL Sura, con su contraseña.
 *
 * La contraseña es del usuario, no de la empresa: una misma persona entra al
 * portal con su cédula y desde ahí administra varias razones sociales. Cuando
 * estaba guardada por NIT, cambiarla en una empresa dejaba las demás con la
 * vieja, y la siguiente que se consultara fallaba el login.
 *
 * No lleva `aliado_id` a propósito: el usuario es de Sura, no de un aliado, y
 * la misma persona aparece en varios aliados de BryNex. Quién puede usarlo se
 * decide en `arl_credenciales`, que sí sabe de qué empresa y aliado se trata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arl_usuarios_portal', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 4)->default('C'); // el del login: C, N, E…
            $table->string('usuario', 30);                     // número de identificación
            $table->text('contrasena');                        // cifrada por Eloquent
            $table->boolean('activo')->default(true);

            $table->timestamp('ultima_sesion_at')->nullable();
            $table->string('ultimo_error', 300)->nullable();
            $table->timestamps();

            // Una sola contraseña por usuario: es justamente lo que evita que
            // dos empresas guarden versiones distintas de la misma clave.
            $table->unique(['tipo_documento', 'usuario'], 'uq_arl_usuario_portal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arl_usuarios_portal');
    }
};
