<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Anticipos (Pagos sin Factura).
 *
 * Registra dinero recibido ANTES de que exista la factura del mes.
 * Al facturar, los anticipos se aplican a la factura y quedan trazados
 * mediante factura_id.
 *
 * Flujo contable:
 *   Abril → anticipo registrado (estado=disponible) → ingreso en cuadre de abril
 *   Mayo  → al facturar, anticipo aplicado (estado=aplicado, factura_id=X)
 *         → factura.anticipo_aplicado = suma de anticipos
 *         → cuadre de mayo NO vuelve a contar ese dinero
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anticipos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();

            // Vínculo con cliente individual o empresa (bloque)
            $table->unsignedBigInteger('cedula')->nullable()->index();
            $table->unsignedInteger('contrato_id')->nullable()->index();  // contratos.id = unsignedInteger
            $table->unsignedInteger('empresa_id')->nullable()->index();   // empresas.id  = integer

            // Datos del pago
            $table->date('fecha_pago');                              // fecha REAL que llegó el dinero
            $table->decimal('valor', 14, 0);                        // monto total registrado
            $table->decimal('valor_aplicado', 14, 0)->default(0);   // cuánto ya se aplicó a facturas

            // Forma de pago
            $table->string('forma_pago', 30);                       // efectivo|nequi|consignacion|transferencia
            $table->unsignedInteger('banco_cuenta_id')->nullable();  // si consignacion/transferencia
            $table->string('referencia', 100)->nullable();           // número nequi, referencia banco

            $table->string('observacion', 300)->nullable();

            // Estado del anticipo
            // disponible → dinero sin usar
            // parcial    → parte aplicada, parte disponible (valor_aplicado < valor)
            // aplicado   → 100% aplicado a factura
            // devuelto   → devuelto al cliente
            $table->string('estado', 20)->default('disponible')->index();

            // Trazabilidad: se llena cuando se aplica a una factura
            $table->unsignedBigInteger('factura_id')->nullable()->index();

            $table->unsignedInteger('usuario_id')->nullable();       // quién lo registró
            $table->timestamps();

            $table->foreign('contrato_id')->references('id')->on('contratos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('factura_id')->references('id')->on('facturas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipos');
    }
};
