<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende tablas relacionadas con incapacidades:
 *
 * 1. consignaciones → agrega incapacidad_id (nullable) para ligar pagos de EPS
 * 2. banco_cuentas  → agrega razon_social_id (nullable) para identificar
 *                     cuentas receptoras de pagos de EPS por razón social
 * 3. gestiones_incapacidad → agrega campos para controlar cuándo una gestión
 *                            cambia el estado de la incapacidad
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. consignaciones: ligar a incapacidades ─────────────────────────────
        Schema::table('consignaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('consignaciones', 'incapacidad_id')) {
                $table->unsignedBigInteger('incapacidad_id')
                    ->nullable()
                    ->after('factura_id')
                    ->index();
                $table->foreign('incapacidad_id')
                    ->references('id')->on('incapacidades')->noActionOnDelete();
            }
        });

        // ── 2. banco_cuentas: ligar a razón social ───────────────────────────────
        Schema::table('banco_cuentas', function (Blueprint $table) {
            if (!Schema::hasColumn('banco_cuentas', 'razon_social_id')) {
                $table->unsignedInteger('razon_social_id')
                    ->nullable()
                    ->after('aliado_id');
                // Sin FK formal para evitar ciclos; la validación va en el modelo
            }
        });

        // ── 3. gestiones_incapacidad: campo para gestiones que cambian estado ─────
        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            if (!Schema::hasColumn('gestiones_incapacidad', 'cambia_estado')) {
                $table->boolean('cambia_estado')->default(false)->after('estado_resultado');
            }
            if (!Schema::hasColumn('gestiones_incapacidad', 'estado_nuevo')) {
                $table->string('estado_nuevo', 30)->nullable()->after('cambia_estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('consignaciones', 'incapacidad_id')) {
                $table->dropForeign(['incapacidad_id']);
                $table->dropColumn('incapacidad_id');
            }
        });

        Schema::table('banco_cuentas', function (Blueprint $table) {
            if (Schema::hasColumn('banco_cuentas', 'razon_social_id')) {
                $table->dropColumn('razon_social_id');
            }
        });

        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            if (Schema::hasColumn('gestiones_incapacidad', 'cambia_estado')) {
                $table->dropColumn('cambia_estado');
            }
            if (Schema::hasColumn('gestiones_incapacidad', 'estado_nuevo')) {
                $table->dropColumn('estado_nuevo');
            }
        });
    }
};
