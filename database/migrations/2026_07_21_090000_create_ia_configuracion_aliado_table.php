<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_configuracion_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->unique();
            $table->string('proveedor', 20)->default('claude'); // claude|openai
            $table->boolean('usa_cuenta_brynex')->default(true);
            $table->text('api_key')->nullable(); // encriptado, solo si usa_cuenta_brynex = false
            $table->string('modelo', 100)->nullable(); // null = usa el modelo global por defecto
            $table->boolean('activo_web')->default(false);
            $table->boolean('activo_whatsapp')->default(false);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_configuracion_aliado');
    }
};
