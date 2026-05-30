<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('consignaciones', 'cuadre_id')) {
                $table->unsignedBigInteger('cuadre_id')->nullable()->after('usuario_id')->index();
            }
            if (!Schema::hasColumn('consignaciones', 'gasto_id')) {
                $table->unsignedBigInteger('gasto_id')->nullable()->after('cuadre_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('consignaciones', 'cuadre_id')) {
                $table->dropColumn('cuadre_id');
            }
            if (Schema::hasColumn('consignaciones', 'gasto_id')) {
                $table->dropColumn('gasto_id');
            }
        });
    }
};
