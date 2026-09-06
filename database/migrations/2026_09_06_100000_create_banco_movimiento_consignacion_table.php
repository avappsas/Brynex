<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre lo que dice el banco y lo que dice el libro de BryNex.
 *
 * Es tabla pivote y no una columna porque el cruce no es uno a uno en ninguna
 * de las dos direcciones:
 *
 *   - Un cobro grande llega partido en varias transferencias. Bre-B tiene tope
 *     por transacción ($12.110.000 en 2026), así que quien paga una planilla
 *     grande manda dos o tres seguidas: varios movimientos, una consignación.
 *   - Un cliente con tres facturas hace una sola transferencia: un movimiento,
 *     varias consignaciones.
 *
 * `valor_aplicado` es cuánto de ese movimiento se le imputa a esa consignación,
 * igual que en `consignacion_factura`. La suma por movimiento nunca debería
 * pasarse de su valor.
 *
 * `regla` guarda con qué criterio se emparejaron. Sirve para dos cosas: revisar
 * los cruces flojos antes de confiar en ellos, y medir qué regla está haciendo
 * el trabajo cuando llegue el extracto real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banco_movimiento_consignacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->unsignedBigInteger('banco_movimiento_id');
            $table->unsignedBigInteger('consignacion_id');

            $table->decimal('valor_aplicado', 18, 2);

            // referencia | fecha_valor | valor_cercano | partido | agrupado | manual
            $table->string('regla', 30);

            // Qué tan lejos quedó la fecha del banco de la del libro. Con el
            // extracto real dirá si la tolerancia por defecto se queda corta.
            $table->integer('dias_diferencia')->default(0);

            $table->unsignedInteger('usuario_id')->nullable();  // null = automático
            $table->timestamps();

            $table->foreign('banco_movimiento_id')->references('id')->on('banco_movimientos');
            $table->foreign('consignacion_id')->references('id')->on('consignaciones');

            // Un movimiento no se le imputa dos veces a la misma consignación.
            $table->unique(['banco_movimiento_id', 'consignacion_id'], 'ux_bmc_movimiento_consignacion');
            $table->index('consignacion_id', 'ix_bmc_consignacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_movimiento_consignacion');
    }
};
