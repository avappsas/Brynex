<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('factura_id')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('fecha_llamada')->useCurrent();
            $table->enum('resultado', [
                'no_contesta',
                'promesa_pago',
                'pagado',
                'numero_errado',
                'otro',
            ])->default('no_contesta');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['aliado_id', 'contrato_id']);
            $table->index('fecha_llamada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_cobros');
    }
};
