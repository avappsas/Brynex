<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_consumo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('canal', 20)->default('web'); // web|whatsapp
            $table->unsignedBigInteger('conversacion_id')->nullable();
            $table->string('proveedor', 20);
            $table->string('modelo', 100)->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->decimal('costo_estimado_usd', 10, 5)->default(0);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('conversacion_id')->references('id')->on('ia_conversaciones')->nullOnDelete();
            $table->index(['aliado_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_consumo');
    }
};
