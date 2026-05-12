<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Fecha de afiliación ARL vigente (se actualiza mes a mes en Gestión ARL)
            // Null = aún no se ha registrado la primera afiliación en el portal ARL
            $table->date('fecha_arl')->nullable()->after('fecha_retiro');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('fecha_arl');
        });
    }
};
