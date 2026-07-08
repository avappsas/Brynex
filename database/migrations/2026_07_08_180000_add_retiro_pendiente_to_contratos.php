<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Fecha de retiro pendiente (registrada desde vista empresa, antes de facturar)
            // Al facturar, se mueve a fecha_retiro y el contrato pasa a 'retirado'
            $table->date('fecha_retiro_pendiente')->nullable()->after('fecha_retiro');

            // Decisión del aliado: ¿cobrar administración en este retiro?
            // 1 = sí cobrar admon, 0 = no cobrar admon, NULL = sin retiro pendiente
            $table->tinyInteger('retiro_pendiente_cobrar_admon')->nullable()->after('fecha_retiro_pendiente');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['fecha_retiro_pendiente', 'retiro_pendiente_cobrar_admon']);
        });
    }
};
