<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuadres', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('abierto'); // abierto | cerrado
            $table->integer('saldo_apertura')->default(0);
            $table->integer('saldo_cierre')->nullable();
            $table->unsignedBigInteger('cerrado_por')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['aliado_id', 'usuario_id', 'estado']);
            $table->index(['aliado_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadres');
    }
};
