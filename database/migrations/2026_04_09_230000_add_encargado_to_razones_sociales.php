<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            // Trabajador encargado de gestionar el cobro de esta empresa
            $table->unsignedBigInteger('encargado_id')->nullable()->after('aliado_id');
            $table->index('encargado_id');
        });
    }

    public function down(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropIndex(['encargado_id']);
            $table->dropColumn('encargado_id');
        });
    }
};
