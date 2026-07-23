<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagina_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('pregunta', 255);
            $table->text('respuesta');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_faqs');
    }
};
