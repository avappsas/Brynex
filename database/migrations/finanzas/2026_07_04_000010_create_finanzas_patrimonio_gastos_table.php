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
        Schema::connection('finanzas')->create('finanzas_patrimonio_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patrimonio_id');
            $table->string('concepto', 100); // SOAT, predial, mantenimiento...
            $table->decimal('monto', 18, 2);
            $table->date('fecha');
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            // Clave foránea interna
            $table->foreign('patrimonio_id')->references('id')->on('finanzas_patrimonio')->onDelete('cascade');

            $table->index(['patrimonio_id', 'fecha'], 'ix_pat_gasto_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_patrimonio_gastos');
    }
};
