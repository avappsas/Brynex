<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de rendimiento para el módulo de incapacidades.
 *
 * 1. gestiones_incapacidad: índice compuesto (incapacidad_id, id DESC)
 *    → acelera la relación latestGestion (HasOne::latestOfMany) que busca
 *      el MAX(id) por incapacidad_id. Sin este índice hace full-scan.
 *
 * 2. incapacidades: índice compuesto (aliado_id, incapacidad_padre_id, estado)
 *    → cubre el filtro principal de la página index:
 *      WHERE aliado_id = ? AND incapacidad_padre_id IS NULL AND estado NOT IN (...)
 *      ORDER BY fecha_recibido DESC
 *
 * 3. incapacidades: índice para ordenamiento
 *    → (aliado_id, fecha_recibido DESC) ayuda al ORDER BY final
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── gestiones_incapacidad ────────────────────────────────────────────
        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            // Cubre: SELECT MAX(id) FROM gestiones_incapacidad WHERE incapacidad_id = ?
            $table->index(['incapacidad_id', 'id'], 'idx_gestiones_inc_id');
        });

        // ── incapacidades ────────────────────────────────────────────────────
        Schema::table('incapacidades', function (Blueprint $table) {
            // Cubre el filtro principal del index (aliado + padre_null + estado + fecha)
            $table->index(
                ['aliado_id', 'incapacidad_padre_id', 'estado', 'fecha_recibido'],
                'idx_inc_aliado_padre_estado_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            $table->dropIndex('idx_gestiones_inc_id');
        });

        Schema::table('incapacidades', function (Blueprint $table) {
            $table->dropIndex('idx_inc_aliado_padre_estado_fecha');
        });
    }
};
