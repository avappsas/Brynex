<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que un portal necesita que una persona haga para poder revisarse.
 *
 * S.O.S. pide reCAPTCHA en el login y desde el servidor no hay manera: la
 * sesión la abre una persona en su propio Chrome y la extensión trabaja dentro.
 * Sin una petición guardada, ese "alguien tiene que entrar" no existe en
 * ninguna parte: no se puede avisar, no se sabe desde cuándo espera y no se
 * sabe si ya se atendió.
 *
 * Una fila por entidad y empresa mientras esté abierta; se cierra cuando la
 * revisión se hace o cuando vence sin que nadie entre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_peticiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->string('entidad', 40);                 // 'sos', 'sanitas'…
            $table->string('nit', 20)->nullable();         // null = todas las empresas
            $table->string('empresa', 200)->nullable();
            $table->string('motivo', 300);
            $table->unsignedInteger('pendientes')->default(0);
            $table->string('estado', 20)->default('abierta');   // abierta | atendida | vencida
            $table->timestamp('aviso_enviado_at')->nullable();
            $table->unsignedSmallInteger('avisos')->default(0);
            $table->timestamp('atendida_at')->nullable();
            $table->unsignedBigInteger('atendida_por')->nullable();
            $table->string('resultado', 400)->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('atendida_por')->references('id')->on('users');
            // La consulta de siempre: qué hay abierto de esta entidad.
            $table->index(['entidad', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_peticiones');
    }
};
