<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->date('brynex_fecha_inicio')->nullable()->after('afiliaciones_brynex');
            $table->date('brynex_fecha_fin')->nullable()->after('brynex_fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn(['brynex_fecha_inicio', 'brynex_fecha_fin']);
        });
    }
};
