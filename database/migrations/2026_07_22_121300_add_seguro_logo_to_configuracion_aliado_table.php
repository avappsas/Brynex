<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            // Logo de la aseguradora, enlazado al seguro_valor por aliado
            $table->string('seguro_logo', 500)->nullable()->after('seguro_valor');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn('seguro_logo');
        });
    }
};
