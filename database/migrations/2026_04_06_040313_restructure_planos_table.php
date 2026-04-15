<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // ── Añadir campo unificado de días ────────────────────────
            $table->unsignedSmallInteger('num_dias')->default(30)->after('anio_plano')
                  ->comment('Días cotizados (todos los fondos usan el mismo valor)');

            // ── tipo_reg: cambia semántica a afiliacion|planilla ──────
            // (ya es varchar, solo se cambia el uso)

            // ── Cambiar razon_social de string→int (ID de la RS) ─────
            // SQL Server no permite DROP + ADD en el mismo batch,
            // lo hacemos en pasos: rename old → add new
            $table->unsignedBigInteger('razon_social_id')->nullable()->after('razon_social')
                  ->comment('ID de razones_sociales');

            // ── Cambiar tipo_p de string→int (ID del tipo_modalidad) ─
            $table->unsignedSmallInteger('tipo_modalidad_id')->nullable()->after('tipo_p')
                  ->comment('ID de tipo_modalidad');

            // ── Eliminar columnas redundantes ─────────────────────────
            $table->dropColumn([
                'tipo_cot', 'sub_tipo_cot',
                'ing', 'ret',
                'num_dias_pension', 'num_dias_salud', 'num_dias_riesgo', 'num_dias_caja',
                'ibc_pension', 'ibc_salud', 'ibc_riesgos', 'ibc_caja',
                'nombre_eps', 'nombre_afp', 'nombre_arl', 'nombre_caja',
                'tar_salud', 'tar_pension', 'tar_arl', 'tar_caja',
                'val_eps', 'val_afp', 'val_arl', 'val_caja',
                'total_cot',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // Re-añadir columnas eliminadas (valores nulos)
            $table->string('tipo_cot', 2)->nullable();
            $table->string('sub_tipo_cot', 2)->nullable();
            $table->boolean('ing')->default(false);
            $table->boolean('ret')->default(false);
            $table->unsignedSmallInteger('num_dias_pension')->default(30);
            $table->unsignedSmallInteger('num_dias_salud')->default(30);
            $table->unsignedSmallInteger('num_dias_riesgo')->default(30);
            $table->unsignedSmallInteger('num_dias_caja')->default(30);
            $table->bigInteger('ibc_pension')->default(0);
            $table->bigInteger('ibc_salud')->default(0);
            $table->bigInteger('ibc_riesgos')->default(0);
            $table->bigInteger('ibc_caja')->default(0);
            $table->string('nombre_eps')->nullable();
            $table->string('nombre_afp')->nullable();
            $table->string('nombre_arl')->nullable();
            $table->string('nombre_caja')->nullable();
            $table->decimal('tar_salud', 6, 4)->default(0);
            $table->decimal('tar_pension', 6, 4)->default(0);
            $table->decimal('tar_arl', 6, 4)->default(0);
            $table->decimal('tar_caja', 6, 4)->default(0);
            $table->bigInteger('val_eps')->default(0);
            $table->bigInteger('val_afp')->default(0);
            $table->bigInteger('val_arl')->default(0);
            $table->bigInteger('val_caja')->default(0);
            $table->bigInteger('total_cot')->default(0);
            // Eliminar los nuevos
            $table->dropColumn(['num_dias', 'razon_social_id', 'tipo_modalidad_id']);
        });
    }
};
