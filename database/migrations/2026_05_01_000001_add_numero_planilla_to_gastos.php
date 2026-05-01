<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // Campo dedicado para el número de planilla SS
            // Solo aplica cuando tipo = 'pago_planilla'
            $table->string('numero_planilla', 100)->nullable()->after('tipo');
        });

        // ── Backfill: extraer el numero_planilla de la descripcion existente ──
        // Formato nuevo: "... | Planilla: {numero_planilla}"
        // Usamos CHARINDEX + SUBSTRING en SQL Server para extraerlo eficientemente
        DB::statement("
            UPDATE gastos
            SET numero_planilla = LTRIM(RTRIM(
                SUBSTRING(
                    descripcion,
                    CHARINDEX('Planilla: ', descripcion) + 10,
                    LEN(descripcion)
                )
            ))
            WHERE tipo = 'pago_planilla'
              AND CHARINDEX('Planilla: ', descripcion) > 0
              AND numero_planilla IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn('numero_planilla');
        });
    }
};
