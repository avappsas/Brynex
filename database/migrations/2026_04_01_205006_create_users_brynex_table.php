<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: users
     * Usuarios del sistema BryNex.
     * - aliado_id: empresa a la que pertenece el usuario por defecto
     * - es_brynex: si true, puede cambiar de aliado sin cambiar rol
     * Las contraseñas del sistema legado (Access) se migran con hash bcrypt.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');                   // empresa principal del usuario
            $table->string('nombre', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('cedula', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('es_brynex')->default(false);              // puede cambiar de aliado
            $table->boolean('activo')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->index('aliado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
