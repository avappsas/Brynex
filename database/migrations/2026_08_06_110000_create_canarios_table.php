<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registros trampa: datos falsos sembrados a propósito en cada aliado.
 *
 * No sirven para vigilar a nadie por dentro — sirven para el día después. Si un
 * canario aparece en otro sistema, la copia deja de ser sospecha y pasa a ser
 * un hecho demostrable: ese dato no existe en el mundo, solo en esta base.
 *
 * Esta tabla es el inventario, para poder reconocerlos y retirarlos. Sin ella,
 * en seis meses nadie recuerda cuáles de los clientes raros eran trampas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('tipo', 30)->default('cliente');
            // clientes.id es int y sin IDENTITY (tabla legacy), no bigint.
            $table->unsignedInteger('referencia_id')->nullable();
            $table->string('cedula', 20)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->string('notas', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->index(['aliado_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canarios');
    }
};
