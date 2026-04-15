<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->string('ruta_descarga_planos', 500)->nullable()->after('distribucion_afiliacion')
                  ->comment('Ruta local donde se guardan los archivos de planos descargados');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn('ruta_descarga_planos');
        });
    }
};
