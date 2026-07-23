<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pool único de contactos de marketing por aliado. Un número existe una sola vez
     * aquí aunque pertenezca a varias listas (marketing_listas) — así el historial de
     * cuántas campañas ha recibido y cuándo fue la última no se fragmenta entre listas.
     */
    public function up(): void
    {
        Schema::create('marketing_contactos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');

            $table->string('celular', 25); // normalizado +57...
            $table->bigInteger('cedula')->nullable();
            $table->string('nombres', 200)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('observacion', 500)->nullable();

            // Historial acumulado — se actualiza cada vez que se le envía una campaña.
            $table->unsignedInteger('veces_contactado')->default(0);
            $table->timestamp('ultima_campana_at')->nullable();
            $table->boolean('respondio_alguna_vez')->default(false);

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();

            $table->unique(['aliado_id', 'celular']);
            $table->index(['aliado_id', 'departamento']);
            $table->index(['aliado_id', 'ciudad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_contactos');
    }
};
