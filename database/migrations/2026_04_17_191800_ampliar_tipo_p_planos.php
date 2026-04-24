<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía la columna tipo_p de planos de nvarchar(20) a nvarchar(50)
 * para soportar el nombre completo de las modalidades como
 * "Independientes Mes Actual" (24 chars).
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQL Server no permite cambiar tipo con ALTER TABLE + Blueprint directamente
        // cuando la columna forma parte de índices; usamos sentencia directa.
        DB::statement('ALTER TABLE [planos] ALTER COLUMN [tipo_p] NVARCHAR(50) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE [planos] ALTER COLUMN [tipo_p] NVARCHAR(20) NULL');
    }
};
