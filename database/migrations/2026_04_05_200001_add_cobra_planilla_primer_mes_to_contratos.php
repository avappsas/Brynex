<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Solo aplica a independientes: permite cobrar planilla + afiliacion en el mismo mes
            $table->boolean('cobra_planilla_primer_mes')->default(false)->after('observacion_afiliacion')
                  ->comment('Independiente que paga afiliacion + planilla en el mes de ingreso');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('cobra_planilla_primer_mes');
        });
    }
};
