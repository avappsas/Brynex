<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros_adicionales_empresa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('empresa_id');
            $table->string('descripcion', 300);
            $table->decimal('valor', 12, 2)->default(0);
            $table->string('tipo', 20)->default('unica_vez'); // 'unica_vez' | 'recurrente'
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');

            $table->index(['aliado_id', 'empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros_adicionales_empresa');
    }
};
