<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Verificar duplicados antes de crear el índice ──────────────
        // Si existen duplicados en (cedula, aliado_id), el índice único
        // fallaría. Detectamos primero para dar un error descriptivo.
        $duplicados = DB::select("
            SELECT cedula, aliado_id, COUNT(*) AS total
            FROM clientes
            GROUP BY cedula, aliado_id
            HAVING COUNT(*) > 1
        ");

        if (!empty($duplicados)) {
            $detalle = collect($duplicados)->map(function ($row) {
                return "  - Cédula {$row->cedula} en aliado_id {$row->aliado_id} → {$row->total} registros";
            })->implode("\n");

            throw new \RuntimeException(
                "⚠️  No se puede crear el índice único en clientes(cedula, aliado_id) porque existen duplicados:\n{$detalle}\n\n" .
                "Resuelve los duplicados manualmente en la BD antes de correr esta migración.\n" .
                "Consulta de diagnóstico:\n" .
                "  SELECT cedula, aliado_id, COUNT(*) FROM clientes GROUP BY cedula, aliado_id HAVING COUNT(*) > 1\n"
            );
        }

        // ── Eliminar el índice simple previo en 'cedula' si existe ──────
        // (fue creado en la migración original: $table->bigInteger('cedula')->index())
        // Nombre convencional de Laravel: clientes_cedula_index
        $indexExiste = DB::select("
            SELECT 1 FROM sys.indexes
            WHERE object_id = OBJECT_ID('clientes')
              AND name = 'clientes_cedula_index'
        ");

        if (!empty($indexExiste)) {
            DB::statement("DROP INDEX clientes_cedula_index ON clientes");
        }

        // ── Crear índice único compuesto (cedula, aliado_id) ────────────
        Schema::table('clientes', function (Blueprint $table) {
            $table->unique(['cedula', 'aliado_id'], 'clientes_cedula_aliado_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_cedula_aliado_unique');
            // Restaurar el índice simple original
            $table->index('cedula');
        });
    }
};
