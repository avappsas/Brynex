<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listas nombradas ("Base fría julio") — subconjuntos del pool de marketing_contactos.
     * marketing_lista_contacto es el pivote: un contacto puede estar en varias listas.
     */
    public function up(): void
    {
        Schema::create('marketing_listas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('creado_por')->references('id')->on('users')->noActionOnDelete();

            $table->unique(['aliado_id', 'nombre']);
        });

        Schema::create('marketing_lista_contacto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lista_id');
            $table->unsignedBigInteger('contacto_id');
            $table->timestamps();

            $table->foreign('lista_id')->references('id')->on('marketing_listas')->cascadeOnDelete();
            $table->foreign('contacto_id')->references('id')->on('marketing_contactos')->cascadeOnDelete();

            $table->unique(['lista_id', 'contacto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lista_contacto');
        Schema::dropIfExists('marketing_listas');
    }
};
