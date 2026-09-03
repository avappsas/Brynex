<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera y última pasada sobre `contratos.cargo`: el resto de los rellenos.
 *
 * Se define por lista blanca y no por lista negra: de los 88 valores cortos que
 * quedaban, solo seis nombran un oficio, así que es más corto —y menos frágil—
 * decir qué se conserva.
 *
 * Cae todo lo demás:
 *
 *  - Teclado aporreado: h, d, f, v, z, {, }, asd, zzz, ggg, hhh, kkk, <, >, **…
 *  - Palabras de modalidad escritas en el campo equivocado: pen, dep, ind, ped.
 *    Serán "pensionado", "dependiente", "independiente", pero ninguna es un
 *    cargo, y como cargo es lo que se le manda a la ARL.
 *  - Marcadores de vacío: N/A, ok, ID.
 *  - UPC, que es una modalidad de afiliación, no un oficio.
 *
 * Se conserva DJ aunque venga rodeado de basura: es un oficio de verdad, y una
 * fila ambigua de menos no vale perder un dato bueno.
 */
return new class extends Migration
{
    /** Los únicos valores cortos que nombran un oficio. */
    private const OFICIOS = ['AUX', 'ENF', 'ADM', 'CON', 'OF', 'DJ'];

    public function up(): void
    {
        DB::table('contratos')
            ->whereNotNull('cargo')
            ->whereRaw('LEN(LTRIM(RTRIM(cargo))) <= 3')
            ->whereNotIn(DB::raw('LTRIM(RTRIM(cargo))'), self::OFICIOS)
            ->update(['cargo' => null]);
    }

    public function down(): void
    {
        // No hay vuelta atrás, y no hace falta: lo que se borró era la ausencia
        // de un dato escrita de otra manera.
    }
};
