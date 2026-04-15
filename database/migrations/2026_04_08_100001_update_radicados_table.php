<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualiza la tabla radicados:
 *  - Agrega ruta_pdf para guardar el PDF del radicado confirmado
 *  - Los estados válidos pasan a ser: pendiente | tramite | traslado | error | ok
 *    (se validan en PHP, no hay CHECK constraint en SQL Server)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            // Ruta del PDF del radicado (storage/radicados/{aliado}/{contrato}/{cedula}/)
            $table->string('ruta_pdf', 500)->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            $table->dropColumn('ruta_pdf');
        });
    }
};
