<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de las consultas a BDUA/RUAF que completan EPS, pensión y nombres
 * de los clientes (comando `clientes:completar-ruaf`).
 *
 * Existe por tres razones:
 *
 *  1. **Reanudar.** Son ~31.000 clientes procesados en tandas para no
 *     disparar el bloqueo del operador. Cada corrida toma los que todavía no
 *     tienen fila aquí, así que se puede parar y seguir sin repetir consultas.
 *
 *  2. **El informe por aliado.** Las diferencias entre lo que tiene Brynex y
 *     lo que dice el registro no se aplican solas: quedan guardadas para
 *     exportarlas y que cada aliado decida.
 *
 *  3. **Auditoría.** Queda registrado qué se escribió y qué no, y por qué.
 *     `payload` guarda la respuesta cruda del operador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruaf_consultas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('aliado_id');

            // Con qué se preguntó. El tipo importa: el registro responde por
            // tipo + número, y el mismo número con otro tipo es otra persona.
            $table->string('tipo_doc', 10)->nullable();
            $table->string('cedula', 30)->nullable();
            $table->string('operador', 20)->nullable();

            // hallado | no_hallado | error | sin_credencial
            $table->string('estado', 20)->index();

            // Salvaguarda de identidad: qué tanto se parece el nombre que
            // devolvió el registro al que ya tenía Brynex (0-100). Por debajo
            // del umbral no se escribe nada, porque probablemente sea otra
            // persona (tipo de documento equivocado).
            $table->unsignedTinyInteger('similitud_nombre')->nullable();
            $table->boolean('identidad_dudosa')->default(false)->index();

            // Lo que decía Brynex antes de tocar nada.
            $table->unsignedBigInteger('eps_id_antes')->nullable();
            $table->unsignedBigInteger('pension_id_antes')->nullable();
            $table->string('nombre_antes', 255)->nullable();

            // Lo que dice el registro oficial.
            $table->string('eps_codigo', 20)->nullable();
            $table->unsignedBigInteger('eps_id_ruaf')->nullable();
            $table->string('pension_codigo', 20)->nullable();
            $table->unsignedBigInteger('pension_id_ruaf')->nullable();
            $table->string('nombre_ruaf', 255)->nullable();

            // Qué se hizo: lleno | coincide | difiere | sin_dato | omitido
            $table->string('accion_eps', 20)->nullable()->index();
            $table->string('accion_pension', 20)->nullable()->index();
            $table->string('accion_nombre', 20)->nullable()->index();

            // Campos de nombre efectivamente escritos, para poder revertir.
            $table->string('campos_escritos', 120)->nullable();

            $table->string('mensaje', 255)->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->unique('cliente_id');
            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruaf_consultas');
    }
};
