<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla abonos_incapacidades para gestionar el flujo financiero
 * de cada incapacidad:
 *
 *   tipo = 'abono'        → Préstamo/anticipo del aliado al cliente (dinero personal/caja)
 *                           Solo informativo: NO descuenta el saldo_pendiente.
 *                           Le indica al aliado cuánto dinero personal tiene comprometido.
 *
 *   tipo = 'pago_eps'     → Lo que la EPS/ARL/AFP consignó a la Razón Social.
 *                           Sí descuenta el saldo_pendiente.
 *                           Genera además un registro en `consignaciones`.
 *
 *   tipo = 'pago_cliente' → Lo que el aliado transfirió al cliente o empresa.
 *                           Sí descuenta el saldo_pendiente.
 *
 * Fórmulas:
 *   saldo_pendiente = valor_esperado - SUM(pago_eps) - SUM(pago_cliente)
 *   total_prestado  = SUM(abono)  ← informativo para el aliado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_incapacidades', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('aliado_id')->index();
            $table->unsignedBigInteger('incapacidad_id')->index();

            // Razón Social involucrada (a quién llegó el pago de EPS, o de dónde salió)
            $table->unsignedInteger('razon_social_id')->nullable();

            // Tipo de movimiento: abono | pago_eps | pago_cliente
            $table->string('tipo', 20);

            $table->decimal('valor', 14, 2);
            $table->date('fecha');

            // Cuenta bancaria de origen/destino (nullable para abonos personales)
            $table->unsignedBigInteger('banco_cuenta_id')->nullable();

            // Referencia a la consignación creada cuando tipo='pago_eps'
            $table->unsignedBigInteger('consignacion_id')->nullable();

            // Usuario que registró el movimiento
            $table->unsignedBigInteger('usuario_id');

            $table->string('observacion', 500)->nullable();

            // Comprobante de pago (imagen/PDF)
            $table->string('imagen_path', 500)->nullable();

            $table->timestamps();

            // ── Índices ─────────────────────────────────────────────────────────
            $table->index(['incapacidad_id', 'tipo']);
            $table->index(['aliado_id', 'tipo']);

            // ── Claves foráneas ──────────────────────────────────────────────────
            $table->foreign('aliado_id')
                ->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('incapacidad_id')
                ->references('id')->on('incapacidades')->noActionOnDelete();
            $table->foreign('banco_cuenta_id')
                ->references('id')->on('banco_cuentas')->noActionOnDelete();
            $table->foreign('consignacion_id')
                ->references('id')->on('consignaciones')->noActionOnDelete();
            $table->foreign('usuario_id')
                ->references('id')->on('users')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_incapacidades');
    }
};
