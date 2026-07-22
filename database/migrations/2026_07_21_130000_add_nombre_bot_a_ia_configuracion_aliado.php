<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_configuracion_aliado', function (Blueprint $table) {
            $table->string('nombre_bot', 100)->nullable()->after('modelo');
        });
    }

    public function down(): void
    {
        Schema::table('ia_configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn('nombre_bot');
        });
    }
};
