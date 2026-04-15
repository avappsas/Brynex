<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            if (!Schema::hasColumn('planos', 'nombre_eps')) {
                $table->string('nombre_eps', 100)->nullable()->after('cod_eps');
            }
            if (!Schema::hasColumn('planos', 'nombre_afp')) {
                $table->string('nombre_afp', 100)->nullable()->after('cod_afp');
            }
            if (!Schema::hasColumn('planos', 'nombre_arl')) {
                $table->string('nombre_arl', 100)->nullable()->after('cod_arl');
            }
            if (!Schema::hasColumn('planos', 'nombre_caja')) {
                $table->string('nombre_caja', 100)->nullable()->after('cod_caja');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['nombre_eps', 'nombre_afp', 'nombre_arl', 'nombre_caja']);
        });
    }
};
