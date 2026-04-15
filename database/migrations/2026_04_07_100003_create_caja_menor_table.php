<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_menor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('usuario_id');
            $table->integer('monto');
            $table->date('fecha');
            $table->unsignedBigInteger('asignado_por');
            $table->boolean('activo')->default(true);
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->index(['aliado_id', 'usuario_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_menor');
    }
};
