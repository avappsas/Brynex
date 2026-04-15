<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivot: aliado_user
     * Permite que usuarios BryNex (es_brynex=true) accedan a múltiples aliados.
     * También puede usarse en el futuro para usuarios con acceso a varios aliados.
     * El rol aquí es el rol específico que el usuario tiene EN ESE aliado.
     */
    public function up(): void
    {
        Schema::create('aliado_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('aliado_id');
            $table->string('rol', 50)->default('usuario');            // rol en este aliado específico
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->unique(['user_id', 'aliado_id']);                 // un user no puede estar 2x en mismo aliado
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliado_user');
    }
};
