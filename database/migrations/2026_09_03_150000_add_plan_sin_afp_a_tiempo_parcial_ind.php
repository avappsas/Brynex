<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tiempo Parcial IND (18) solo ofrecía planes con AFP —"ARL + AFP + CCF" (11)
     * y "ARL + AFP" (16)—, así que un cliente ya pensionado, que no puede cotizar
     * pensión, se quedaba sin ningún plan que elegir en esa modalidad.
     *
     * Se agrega "ARL + CCF" (13), que es el mismo espejo sin AFP que ya tienen
     * todas las demás modalidades de tiempo parcial (1-4 y -6 a -9).
     */
    public function up(): void
    {
        $existe = DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', 18)
            ->where('plan_id', 13)
            ->exists();

        if (! $existe) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => 18,
                'plan_id' => 13,
                'solo_ia' => false,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', 18)
            ->where('plan_id', 13)
            ->delete();
    }
};
