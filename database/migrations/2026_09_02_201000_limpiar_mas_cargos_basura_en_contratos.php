<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Segunda pasada sobre `contratos.cargo`: los rellenos que no son equis.
 *
 * Todos vienen del sistema viejo —en las 94 variantes cortas que quedaban,
 * `id_legacy` no era nulo ni una sola vez—, así que son lo que alguien tecleó
 * para pasar de campo, no un dato.
 *
 * Se limpian solo los seis que se revisaron uno por uno y no nombran ningún
 * oficio: G, NN, N, A, C y 0. Las abreviaturas que sí dicen algo se quedan
 * como están —AUX, ENF, ADM—, igual que en la pasada anterior.
 *
 * En NULL, el payload de la ARL cae al cargo por defecto de la razón social y,
 * mejor todavía, `ArlDatosFaltantesService` le pregunta a Sura el cargo real de
 * la persona la próxima vez que se abra su renovación.
 */
return new class extends Migration
{
    /** Sin oficio detrás: relleno para pasar de campo. */
    private const RELLENOS = ['G', 'NN', 'N', 'A', 'C', '0'];

    public function up(): void
    {
        // La colación de la base no distingue mayúsculas, así que "n" y "N"
        // caen con el mismo valor de la lista.
        DB::table('contratos')
            ->whereNotNull('cargo')
            ->whereIn(DB::raw('LTRIM(RTRIM(cargo))'), self::RELLENOS)
            ->update(['cargo' => null]);
    }

    public function down(): void
    {
        // No hay vuelta atrás, y no hace falta: lo que se borró era la ausencia
        // de un dato escrita de otra manera.
    }
};
