<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('categoria_id');
            $table->date('fecha');
            $table->decimal('monto', 18, 2);
            $table->string('descripcion', 255)->nullable();
            $table->string('tipo_movimiento', 20)->default('gasto'); // Gasto habitual, préstamo, inversión, etc.
            $table->boolean('es_patrimonio')->default(false);
            $table->unsignedBigInteger('patrimonio_id')->nullable(); // Se enlazará en la app con la tabla patrimonio
            $table->timestamps();

            // Claves foráneas internas
            $table->foreign('categoria_id')->references('id')->on('finanzas_categorias_gasto')->onDelete('restrict');

            // Índices para búsquedas eficientes
            $table->index(['user_id', 'fecha'], 'ix_gasto_user_fecha');
            $table->index(['user_id', 'categoria_id', 'fecha'], 'ix_gasto_user_cat_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_gastos');
    }
};
