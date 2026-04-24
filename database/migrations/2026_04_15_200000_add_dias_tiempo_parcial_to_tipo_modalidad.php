<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega columnas de días por entidad para planes de Tiempo Parcial.
     *
     * Reglas fijas (no configurables en UI):
     *  - Tiempo Parcial (7)      → ARL=7,  AFP=7,  CAJA=7
     *  - Tiempo Parcial (14)     → ARL=14, AFP=14, CAJA=14
     *  - Tiempo Parcial (21)     → ARL=21, AFP=21, CAJA=21
     *  - Tiempo Parcial (30)     → ARL=30, AFP=30, CAJA=30
     *  - Tiempo Parcial (7-14)   → ARL=14, AFP=7,  CAJA=14
     *  - Tiempo Parcial (7-21)   → ARL=21, AFP=7,  CAJA=21
     *  - Tiempo Parcial (14-21)  → ARL=21, AFP=14, CAJA=21
     *
     * Todos incluyen ARL. EPS no aplica en tiempo parcial.
     */
    public function up(): void
    {
        Schema::table('tipo_modalidad', function (Blueprint $table) {
            $table->boolean('es_tiempo_parcial')->default(false)->after('activo');
            $table->unsignedTinyInteger('dias_arl')->nullable()->after('es_tiempo_parcial');
            $table->unsignedTinyInteger('dias_afp')->nullable()->after('dias_arl');
            $table->unsignedTinyInteger('dias_caja')->nullable()->after('dias_afp');
        });

        // Poblar datos según el nombre en 'observacion'
        $reglas = [
            'Tiempo Parcial (7)'     => ['dias_arl' => 7,  'dias_afp' => 7,  'dias_caja' => 7],
            'Tiempo Parcial (14)'    => ['dias_arl' => 14, 'dias_afp' => 14, 'dias_caja' => 14],
            'Tiempo Parcial (21)'    => ['dias_arl' => 21, 'dias_afp' => 21, 'dias_caja' => 21],
            'Tiempo Parcial (30)'    => ['dias_arl' => 30, 'dias_afp' => 30, 'dias_caja' => 30],
            'Tiempo Parcial (7-14)'  => ['dias_arl' => 14, 'dias_afp' => 7,  'dias_caja' => 14],
            'Tiempo Parcial (7-21)'  => ['dias_arl' => 21, 'dias_afp' => 7,  'dias_caja' => 21],
            'Tiempo Parcial (14-21)' => ['dias_arl' => 21, 'dias_afp' => 14, 'dias_caja' => 21],
        ];

        foreach ($reglas as $observacion => $dias) {
            DB::table('tipo_modalidad')
                ->where('observacion', $observacion)
                ->update(array_merge($dias, ['es_tiempo_parcial' => true]));
        }
    }

    public function down(): void
    {
        Schema::table('tipo_modalidad', function (Blueprint $table) {
            $table->dropColumn(['es_tiempo_parcial', 'dias_arl', 'dias_afp', 'dias_caja']);
        });
    }
};
