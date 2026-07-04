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
        Schema::connection('finanzas')->create('finanzas_patrimonio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 100);
            $table->string('categoria', 30); // inmueble | vehiculo | electronico | joya | otro
            $table->decimal('valor_compra', 18, 2);
            $table->date('fecha_adquisicion');
            $table->decimal('valor_actual', 18, 2)->nullable(); // Actualizable manualmente
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_patrimonio');
    }
};
