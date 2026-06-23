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
        Schema::table('cotizaciones_prospectos', function (Blueprint $table) {
            $table->decimal('administracion', 10, 2)->nullable()->after('costo_afiliacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones_prospectos', function (Blueprint $table) {
            $table->dropColumn('administracion');
        });
    }
};
