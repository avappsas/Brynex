<?php

/**
 * La base vieja (Brygar_BD) desde la que se migró.
 *
 * Sigue viva y en solo lectura: hay meses que BryNex heredó incompletos y la
 * única fuente confiable de esos datos es ella.
 *
 * `aliado_id` es la guarda que impide leer plata ajena. Cada aliado tenía su
 * propia base con la MISMA numeración de tablas — el `id_legacy = 8` de la
 * cuenta BRYGAR también existe como cuenta 8 en la base de otro aliado, y
 * apunta a otro banco de otra persona. La conexión `sqlsrv_legacy` apunta a
 * una sola de esas bases, así que cualquier consulta legacy tiene que
 * verificar primero que el registro que está mirando sea de ESE aliado.
 */
return [

    'conexion' => env('DB_LEGACY_CONNECTION', 'sqlsrv_legacy'),

    // De quién es la base a la que apunta `sqlsrv_legacy`. 2 = BRYGAR.
    'aliado_id' => (int) env('DB_LEGACY_ALIADO', 2),

    // Hasta cuándo la base vieja fue la que mandaba. Después de esta fecha
    // BryNex ya registraba todo y el legacy solo tiene ruido.
    'corte' => env('DB_LEGACY_CORTE', '2026-05-31'),
];
