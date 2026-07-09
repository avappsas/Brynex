<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamo_movimientos', function (Blueprint $table) {
            $table->string('soporte_path', 255)->nullable()->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamo_movimientos', function (Blueprint $table) {
            $table->dropColumn('soporte_path');
        });
    }
};
