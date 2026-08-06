<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de accesos al panel: quién entró, desde dónde y con qué máquina.
 *
 * Sin esto no se puede responder si una cuenta se está usando, ni desde dónde.
 * `dispositivo_id` es un UUID que el servidor deja en una cookie de larga
 * duración; `huella` es un hash de las características del navegador. La MAC
 * no se puede leer desde la web — la combinación de ambos es lo que permite
 * reconocer la misma máquina aunque cambie de IP o entre por VPN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_usuario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('user_id');
            $table->string('ip', 45)->nullable();
            // Cookie persistente: identifica el equipo aunque cambie la red.
            $table->string('dispositivo_id', 64)->nullable();
            // Huella del navegador: sobrevive al borrado de cookies.
            $table->string('huella', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            // Lista separada por comas: dispositivo_nuevo, ip_nueva, red_nueva…
            $table->string('anomalias', 200)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('user_id')->references('id')->on('users');

            // El acceso se consulta siempre por usuario y en orden cronológico.
            $table->index(['user_id', 'created_at']);
            // Para cruzar un mismo equipo entre cuentas distintas: es la
            // consulta que delata una cuenta compartida o un tercero operando
            // varias cuentas del mismo aliado.
            $table->index('dispositivo_id');
            $table->index(['aliado_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_usuario');
    }
};
