<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Caja y pensión de Tipo E - Extras también cobran afiliación: $100.000.
 *
 * Nacieron en cero (ver la migración del tarifario, del mismo día) porque en las
 * modalidades viejas nunca se cobró. El dueño fijó el precio el 17-sep-2026:
 * son tres planes que se venden igual que los de salud —dos planillas, dos
 * pagos y el sobrecosto fijo de $12.200 del paso 1—, así que no hay razón para
 * que la afiliación vaya gratis.
 */
return new class extends Migration
{
    private const ALIADO = 2;          // BRYGAR

    private const MODALIDAD = -12;     // Tipo E - Extras

    private const PLANES = ['SOLO_CCF_14', 'SOLO_CCF_30', 'SOLO_AFP'];

    private const AFILIACION = 100000;

    public function up(): void
    {
        $this->fijar(self::AFILIACION);
    }

    public function down(): void
    {
        $this->fijar(0);
    }

    private function fijar(int $valor): void
    {
        $planes = DB::table('planes_contrato')->whereIn('codigo', self::PLANES)->pluck('id');

        DB::table('afiliacion_arl_modalidad')
            ->where('aliado_id', self::ALIADO)
            ->where('tipo_modalidad_id', self::MODALIDAD)
            ->whereIn('plan_id', $planes)
            ->update(['costo_afiliacion' => $valor, 'updated_at' => now()]);
    }
};
