<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Agregar columna nit a razones_sociales si no existe ─────────────
        // nit = id_legacy (el NIT real de la empresa en el legacy)
        if (!Schema::hasColumn('razones_sociales', 'nit')) {
            Schema::table('razones_sociales', function (Blueprint $table) {
                $table->bigInteger('nit')->nullable()->index()->after('id')
                      ->comment('NIT de la razón social = id_legacy del sistema anterior');
            });
        }

        // Popular nit = id_legacy
        DB::statement('UPDATE razones_sociales SET nit = id_legacy WHERE nit IS NULL');

        // ── 2. Corregir planos.razon_social_id ────────────────────────────────
        // planos.razon_social guarda el NIT como string (heredado del legacy).
        // razones_sociales.id_legacy = ese NIT → razon_social_id = razones_sociales.id (BryNex)
        DB::statement("
            UPDATE p
            SET    p.razon_social_id = rs.id
            FROM   planos p
            JOIN   razones_sociales rs
                   ON rs.id_legacy = TRY_CAST(p.razon_social AS BIGINT)
            WHERE  p.razon_social_id IS NULL
              AND  p.razon_social IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropColumn('nit');
        });
    }
};
