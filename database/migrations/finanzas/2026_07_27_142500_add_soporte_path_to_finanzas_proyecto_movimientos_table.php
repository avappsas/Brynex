<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La columna se aplicó en su día pero el registro quedó en la tabla
     * `migrations` de la conexión por defecto en vez de la de finanzas, así que
     * aquí seguía figurando como pendiente. La guarda deja la migración
     * idempotente: no falla al re-ejecutarse ni al reconstruir la base.
     */
    public function up(): void
    {
        if (Schema::connection('finanzas')->hasColumn('finanzas_proyecto_movimientos', 'soporte_path')) {
            return;
        }

        Schema::connection('finanzas')->table('finanzas_proyecto_movimientos', function (Blueprint $table) {
            $table->string('soporte_path', 255)->nullable()->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('finanzas')->hasColumn('finanzas_proyecto_movimientos', 'soporte_path')) {
            return;
        }

        Schema::connection('finanzas')->table('finanzas_proyecto_movimientos', function (Blueprint $table) {
            $table->dropColumn('soporte_path');
        });
    }
};
