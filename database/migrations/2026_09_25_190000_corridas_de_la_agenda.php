<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué corrió de la agenda, cuándo y cómo salió.
 *
 * La agenda tiene treinta comandos que escriben su propio log y nadie los lee:
 * un comando que empieza a fallar de madrugada puede pasar semanas sin que se
 * note. Aquí queda el resultado de cada corrida —código de salida y las últimas
 * líneas que imprimió—, que es lo que hace falta para poder avisar.
 *
 * No guarda el detalle del trabajo (eso sigue en el log de cada comando), solo
 * si salió bien y qué dijo al final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corridas_programadas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);            // el ->name() de la agenda, o el comando
            $table->string('comando', 400);
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();      // null = quedó corriendo (o se murió)
            $table->unsignedInteger('segundos')->nullable();
            $table->integer('exit_code')->nullable();
            $table->boolean('exitosa')->nullable();
            $table->string('motivo', 400)->nullable(); // por qué falló, cuando se sabe
            $table->text('salida')->nullable();        // cola de lo que imprimió
            $table->timestamps();

            // La consulta de siempre: qué corrió en tal ventana de tiempo.
            $table->index(['inicio']);
            $table->index(['nombre', 'inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corridas_programadas');
    }
};
