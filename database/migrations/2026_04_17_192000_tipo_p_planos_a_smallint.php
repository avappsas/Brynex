<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte tipo_p de nvarchar(50) a smallint.
 * tipo_p ahora guarda el tipo_modalidad_id (entero) en vez del nombre de texto.
 * tipo_modalidad_id ya existe como columna separada con el mismo dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Primero sincronizar datos existentes: copiar tipo_modalidad_id → tipo_p
        // para los registros que tenían el nombre de texto (incompatible con smallint)
        DB::statement('UPDATE [planos] SET [tipo_p] = CAST([tipo_modalidad_id] AS NVARCHAR(10)) WHERE [tipo_modalidad_id] IS NOT NULL');

        // Limpiar valores que no sean numéricos (nombres de texto legacy)
        DB::statement("UPDATE [planos] SET [tipo_p] = NULL WHERE TRY_CAST([tipo_p] AS INT) IS NULL");

        // Cambiar el tipo de columna: nvarchar → smallint (solo guarda IDs del 1-999)
        DB::statement('ALTER TABLE [planos] ALTER COLUMN [tipo_p] SMALLINT NULL');
    }

    public function down(): void
    {
        // Revertir a nvarchar(20) original
        DB::statement('ALTER TABLE [planos] ALTER COLUMN [tipo_p] NVARCHAR(20) NULL');
    }
};
