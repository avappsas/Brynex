<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales del portal de ARL Sura, una por aliado.
 *
 * Una sola sesión sirve para todas las pólizas a las que ese usuario tenga
 * acceso —el portal cambia de contexto con el header `x-auth-poliza`—, así que
 * no hace falta una credencial por razón social.
 *
 * La contraseña va cifrada por Eloquent (cast `encrypted`), igual que en
 * `operadores_credenciales`. No se guarda la cookie aquí: vive en caché porque
 * caduca en media hora y no tiene sentido persistirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arl_credenciales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');

            $table->string('tipo_documento', 4)->default('C'); // el del login: C, N, E…
            $table->string('usuario', 30);                     // número de identificación
            $table->text('contrasena');                         // cifrada
            $table->boolean('activo')->default(true);

            $table->timestamp('ultima_sesion_at')->nullable();
            $table->string('ultimo_error', 300)->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->unique('aliado_id', 'uq_arl_credencial_aliado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arl_credenciales');
    }
};
