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
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->tinyInteger('dia_ingreso_ir')->nullable()->default(26)->after('dist_retiro_pct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn('dia_ingreso_ir');
        });
    }
};
