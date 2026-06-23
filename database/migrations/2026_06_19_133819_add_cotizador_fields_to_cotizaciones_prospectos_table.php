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
            $table->string('nombre_completo', 200)->nullable()->after('cedula');
            $table->boolean('es_independiente')->default(1)->after('ocupacion');
            $table->date('fecha_ingreso')->nullable()->after('es_independiente');
            $table->tinyInteger('n_arl')->default(1)->after('salario_base');
            $table->decimal('costo_afiliacion', 10, 2)->nullable()->after('n_arl');
            $table->json('resultado_cotizacion')->nullable()->after('costo_afiliacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones_prospectos', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_completo',
                'es_independiente',
                'fecha_ingreso',
                'n_arl',
                'costo_afiliacion',
                'resultado_cotizacion'
            ]);
        });
    }
};
