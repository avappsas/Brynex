<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja en NULL los cargos que no dicen nada: cadenas vacías y equis.
 *
 * Un cargo de "X" es peor que ninguno. No está vacío, así que ninguna
 * validación lo ve, pero es lo que termina registrado como ocupación del
 * trabajador en la ARL —y el cargo es lo que sustenta el nivel de riesgo—.
 * En NULL, en cambio, el constructor del payload cae al cargo por defecto de
 * la razón social, y si tampoco lo hay, `problemas()` lo dice en pantalla
 * antes de afiliar a nadie.
 *
 * La cadena vacía se unifica a NULL de paso: son el mismo "no sé" escrito de
 * dos formas, y tenerlas separadas obliga a preguntar por las dos en cada
 * consulta.
 *
 * No se tocan cargos cortos que sí significan algo —AUX, ENF, ADM—: se limpia
 * solo lo que está compuesto únicamente de equis.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cadena vacía o solo espacios.
        DB::update("UPDATE contratos SET cargo = NULL
                    WHERE cargo IS NOT NULL AND LTRIM(RTRIM(cargo)) = ''");

        // Solo equis, en cualquier cantidad y combinación de mayúsculas:
        // X, xx, XXX, xxxx… El NOT LIKE '%[^xX]%' pide que no haya ningún
        // carácter distinto de x.
        DB::update("UPDATE contratos SET cargo = NULL
                    WHERE cargo IS NOT NULL
                      AND LTRIM(RTRIM(cargo)) <> ''
                      AND LTRIM(RTRIM(cargo)) NOT LIKE '%[^xX]%'");
    }

    public function down(): void
    {
        // No hay vuelta atrás, y no hace falta: lo que se borró era la ausencia
        // de un dato escrita de otra manera.
    }
};
