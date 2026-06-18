<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte de anulación (soft delete) a la tabla anticipos.
 *
 * Patrón idéntico al de facturas (2026_04_06_185000_add_soft_delete_to_facturas.php):
 *   - deleted_at       → SoftDeletes de Laravel (timestamp nullable)
 *   - motivo_anulacion → razón legible ingresada por el usuario (obligatoria al anular)
 *   - anulado_por      → ID del usuario que realizó la anulación
 *
 * REGLA DE NEGOCIO:
 *   Solo se pueden anular anticipos en estado 'disponible' con valor_aplicado = 0.
 *   Si la factura a la que estaba aplicado se anula, el anticipo ya queda en
 *   estado 'disponible' automáticamente (ver FacturacionController::anular()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anticipos', function (Blueprint $table) {
            $table->softDeletes();                                                              // columna deleted_at
            $table->text('motivo_anulacion')->nullable()->after('observacion');                // motivo legible
            $table->unsignedSmallInteger('anulado_por')->nullable()->after('motivo_anulacion'); // usuario que anuló
        });
    }

    public function down(): void
    {
        Schema::table('anticipos', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['motivo_anulacion', 'anulado_por']);
        });
    }
};
