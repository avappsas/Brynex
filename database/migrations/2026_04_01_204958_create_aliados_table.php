<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: aliados
     * Almacena las empresas aliadas que contratan servicios de BryNex.
     * Cada aliado tiene su propia base de datos legada en Brygar_BD.
     */
    public function up(): void
    {
        Schema::create('aliados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('nit', 20)->unique()->nullable();
            $table->string('razon_social', 200)->nullable();
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('celular', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->string('logo', 255)->nullable();                  // ruta del logo
            $table->string('color_primario', 10)->nullable();         // personalización visual (#HEX)
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();                                     // eliminación lógica
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliados');
    }
};
