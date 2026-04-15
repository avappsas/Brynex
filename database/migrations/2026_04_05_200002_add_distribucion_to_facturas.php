<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Distribución calculada del cobro de afiliación
            $table->integer('dist_admon')->default(0)->after('valor_prestamo')
                  ->comment('Valor Admon Empresa en cobro de afiliacion');
            $table->integer('dist_asesor')->default(0)->after('dist_admon')
                  ->comment('Valor comision asesor en cobro de afiliacion');
            $table->integer('dist_retiro')->default(0)->after('dist_asesor')
                  ->comment('Valor reservado para retiro/novedad en afiliacion');
            $table->integer('dist_utilidad')->default(0)->after('dist_retiro')
                  ->comment('Utilidad neta en cobro de afiliacion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['dist_admon', 'dist_asesor', 'dist_retiro', 'dist_utilidad']);
        });
    }
};
