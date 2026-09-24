<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La columna `accion` de la bitácora aguantaba 20 caracteres.
 *
 * Se quedó corta hace rato: `aviso_tratamiento_aceptado` son 26 y
 * `permisos_actualizados` 21, así que esos eventos no se guardaban —el insert
 * fallaba entero y el error solo iba al log, que es justo lo que una bitácora
 * no puede hacer—. Se vio el 24-sep-2026 con un aviso de tratamiento de datos.
 *
 * No hay índices sobre la columna, así que el ALTER es directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE [bitacora] ALTER COLUMN [accion] NVARCHAR(60) NOT NULL');
    }

    public function down(): void
    {
        // Volver a 20 cortaría las acciones ya guardadas que no caben.
        DB::statement("UPDATE [bitacora] SET [accion] = LEFT([accion], 20) WHERE LEN([accion]) > 20");
        DB::statement('ALTER TABLE [bitacora] ALTER COLUMN [accion] NVARCHAR(20) NOT NULL');
    }
};
