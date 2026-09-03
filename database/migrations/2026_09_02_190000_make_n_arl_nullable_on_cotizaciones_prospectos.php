<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `n_arl` nació NOT NULL DEFAULT 1 porque el cotizador siempre manda un nivel.
 * Pero el asistente de IA cotiza también planes sin ARL —Solo EPS, EPS + AFP,
 * EPS + AFP + CCF, Solo AFP, Seguro— y ahí no hay nivel de riesgo que guardar:
 * escribía NULL y el INSERT se caía, así que esos interesados nunca llegaron a
 * /admin/cotizaciones. Se perdieron 3 conversaciones entre el 31-ago y el 2-sep.
 *
 * Se abre la columna a NULL en vez de inventarle riesgo 1 a un plan que no lleva
 * ARL. El DEFAULT 1 se queda: quien no manda el campo sigue guardando 1 como
 * siempre, y las lecturas ya venían defendidas con `?? 1`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cotizaciones_prospectos ALTER COLUMN n_arl TINYINT NULL');
    }

    public function down(): void
    {
        // Volver a NOT NULL exige que no queden nulos; el 1 es el default histórico.
        DB::statement('UPDATE cotizaciones_prospectos SET n_arl = 1 WHERE n_arl IS NULL');
        DB::statement('ALTER TABLE cotizaciones_prospectos ALTER COLUMN n_arl TINYINT NOT NULL');
    }
};
