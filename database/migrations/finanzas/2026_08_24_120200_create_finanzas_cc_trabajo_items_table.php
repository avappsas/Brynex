<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose de un trabajo: "4 cámaras a $180.000", "1 DVR a $450.000",
     * "mano de obra $300.000". La suma de los ítems arma el total del trabajo,
     * que es el `monto_original` del préstamo asociado.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_cc_trabajo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prestamo_id'); // el trabajo
            $table->string('descripcion', 150);
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('valor_unitario', 18, 2)->default(0);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['prestamo_id', 'orden'], 'ix_cc_item_trabajo');
        });
    }

    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_cc_trabajo_items');
    }
};
