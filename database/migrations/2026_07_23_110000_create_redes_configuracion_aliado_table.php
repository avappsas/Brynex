<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales de redes sociales por aliado (Facebook, Instagram — extensible a otras vía
     * la columna `red`). Mismo patrón de token encriptado que whatsapp_configuracion_aliado.
     */
    public function up(): void
    {
        Schema::create('redes_configuracion_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('red', 30);                 // facebook|instagram|... (extensible)
            $table->string('identificador', 100)->nullable(); // page_id / ig_business_account_id
            $table->text('access_token')->nullable();  // encriptado
            $table->string('nombre_cuenta', 150)->nullable();
            $table->json('extra')->nullable();          // campos específicos de futuras redes
            $table->boolean('activo')->default(false);
            $table->timestamp('verificado_en')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->unique(['aliado_id', 'red']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_configuracion_aliado');
    }
};
