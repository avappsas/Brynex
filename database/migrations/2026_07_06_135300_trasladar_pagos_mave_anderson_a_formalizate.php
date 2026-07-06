<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Trasladar los abonos de Mave integral - ANDERSON (asociados a aliado_id = 4)
        // al aliado Formalizate (aliado_id = 7)
        DB::connection('finanzas')
            ->table('finanzas_brynex_pagos')
            ->where('aliado_id', 4)
            ->where('observacion', 'like', '%Mave integral - ANDERSON%')
            ->update([
                'aliado_id' => 7,
                'observacion' => DB::raw("CONCAT(observacion, ' - Trasladado a Formalizate por requerimiento de usuario')")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversar el traslado: regresar los pagos a aliado_id = 4 y restaurar la observación
        DB::connection('finanzas')
            ->table('finanzas_brynex_pagos')
            ->where('aliado_id', 7)
            ->where('observacion', 'like', '%Trasladado a Formalizate%')
            ->update([
                'aliado_id' => 4,
                'observacion' => DB::raw("REPLACE(observacion, ' - Trasladado a Formalizate por requerimiento de usuario', '')")
            ]);
    }
};
