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
        Schema::connection('finanzas')->create('finanzas_inversiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 100);
            $table->string('tipo', 30)->default('cripto'); // cripto | trading | otro
            $table->decimal('monto_invertido_cop', 18, 2)->default(0);
            $table->decimal('cantidad_tokens', 18, 8)->nullable();
            $table->decimal('precio_compra_promedio', 18, 4)->nullable(); // COP por token
            $table->decimal('valor_actual_cop', 18, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_inversiones');
    }
};
