<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_asesores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('asesor_id');

            // Referencia al contrato (cuando el módulo de contratos exista)
            $table->string('contrato_ref', 50)->nullable(); // CC o número del contrato

            // Tipo de comisión
            $table->string('tipo', 30); // 'afiliacion' | 'administracion'

            // Periodo al que corresponde (siempre el primer día del mes)
            $table->date('periodo'); // Ej: 2026-04-01 = abril 2026

            // Valores
            $table->decimal('valor_base', 12, 2)->default(0);  // Valor del contrato o cuota admin
            $table->string('tipo_calculo', 20)->default('fijo'); // 'fijo' | 'porcentaje'
            $table->decimal('valor_comision', 12, 2)->default(0); // Resultado final calculado

            // Estado de pago
            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();
            $table->string('observacion', 255)->nullable();

            $table->timestamps();

            // FK sin cascade (SQL Server)
            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('asesor_id')->references('id')->on('asesores');

            // Índice para evitar duplicar la misma comisión del mismo tipo/periodo/contrato
            $table->index(['asesor_id', 'tipo', 'periodo', 'contrato_ref'],
                'comisiones_asesor_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_asesores');
    }
};
