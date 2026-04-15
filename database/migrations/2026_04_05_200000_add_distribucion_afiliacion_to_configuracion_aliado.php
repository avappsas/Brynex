<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            // Distribución del cobro de afiliación (% sobre costo_afiliacion)
            $table->decimal('dist_admon_pct',  5, 2)->default(0)->after('costo_afiliacion')
                  ->comment('Porcentaje del costo de afiliacion para Admon Empresa');
            $table->decimal('dist_retiro_pct', 5, 2)->default(0)->after('dist_admon_pct')
                  ->comment('Porcentaje del costo de afiliacion reservado para Retiro/Novedad');
            // dist_asesor_pct se calcula desde asesores.comision_afil_valor/tipo
            // dist_utilidad = lo restante (calculado, no se guarda)
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn(['dist_admon_pct', 'dist_retiro_pct']);
        });
    }
};
