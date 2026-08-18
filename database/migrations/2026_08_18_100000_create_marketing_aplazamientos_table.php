<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Por ahora no" — ni una baja ni un sí.
 *
 * Sin esto solo existían dos estados: contactable o bloqueado. Quien contestaba "por ahora no"
 * quedaba como contactable y la siguiente campaña le volvía a escribir como si nunca hubiera
 * respondido, que es justo lo que convierte un "todavía no" en una baja de verdad.
 *
 * Es aplazamiento y no bloqueo a propósito: el cliente dijo que más adelante, y más adelante
 * llega. Vencida la fecha, vuelve a entrar solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_aplazamientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('telefono', 30);
            $table->date('hasta');
            $table->string('origen', 40)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('conversacion_id')->nullable();
            $table->timestamps();

            $table->index(['aliado_id', 'telefono']);
            $table->foreign('aliado_id')->references('id')->on('aliados');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_aplazamientos');
    }
};
