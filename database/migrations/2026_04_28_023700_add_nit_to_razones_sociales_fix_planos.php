<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Agregar columna nit a razones_sociales ──────────────────────────
        // razones_sociales.id YA ES el NIT legacy (primary key = NIT del legacy).
        // La columna nit es un alias explícito para búsquedas y futuros usos.
        if (!Schema::hasColumn('razones_sociales', 'nit')) {
            Schema::table('razones_sociales', function (Blueprint $table) {
                $table->bigInteger('nit')->nullable()->index()->after('id')
                      ->comment('NIT legacy = mismo valor que id (heredado del sistema anterior)');
            });
        }

        // Popular nit = id para todos los registros existentes
        DB::statement('UPDATE razones_sociales SET nit = id WHERE nit IS NULL');

        // ── 2. Corregir planos.razon_social_id ────────────────────────────────
        // planos.razon_social tiene el NIT como string (ej: "900123456").
        // razones_sociales.id == ese NIT (PK = NIT legacy).
        // → razon_social_id = CAST(razon_social) donde exista en razones_sociales.
        DB::statement("
            UPDATE p
            SET    p.razon_social_id = TRY_CAST(p.razon_social AS BIGINT)
            FROM   planos p
            WHERE  p.razon_social_id IS NULL
              AND  p.razon_social IS NOT NULL
              AND  TRY_CAST(p.razon_social AS BIGINT) IS NOT NULL
              AND  EXISTS (
                       SELECT 1
                       FROM   razones_sociales rs
                       WHERE  rs.id = TRY_CAST(p.razon_social AS BIGINT)
                   )
        ");

        // ── 3. Normalizar planos.tipo_reg ─────────────────────────────────────
        // La migración legacy guardó códigos PILA crudos ('01', '02', '03', etc.).
        // BryNex necesita: 'planilla' | 'afiliacion' | 'retiro'.
        // Todo lo que no sea uno de esos valores válidos → 'planilla' (caso más común).
        DB::statement("
            UPDATE planos SET tipo_reg = 'planilla'
            WHERE tipo_reg NOT IN ('planilla', 'afiliacion', 'retiro')
               OR tipo_reg IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropColumn('nit');
        });
        // No se revierte razon_social_id ni tipo_reg
    }
};
