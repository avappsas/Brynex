<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza los motivos de afiliación según catálogo del sistema Access (Brygar_BD).
 * Nueva Afiliación, Cambio de Plan, Cambio Razon Social, Recuperado, Error, Omiso
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            DELETE FROM [motivos_afiliacion];
            SET IDENTITY_INSERT [motivos_afiliacion] ON;
            INSERT INTO [motivos_afiliacion] ([id],[nombre],[activo]) VALUES
                (1,'Nueva Afiliacion',1),
                (2,'Cambio de Plan',1),
                (3,'Cambio Razon Social',1),
                (4,'Recuperado',1),
                (5,'Error',1),
                (6,'Omiso',1);
            SET IDENTITY_INSERT [motivos_afiliacion] OFF;
        ");
    }

    public function down(): void
    {
        DB::table('motivos_afiliacion')->truncate();
        DB::table('motivos_afiliacion')->insert([
            ['id' => 1, 'nombre' => 'Nuevo',               'activo' => 1],
            ['id' => 2, 'nombre' => 'Traslado EPS',         'activo' => 1],
            ['id' => 3, 'nombre' => 'Traslado ARL',         'activo' => 1],
            ['id' => 4, 'nombre' => 'Reingreso',            'activo' => 1],
            ['id' => 5, 'nombre' => 'Recuperado',           'activo' => 1],
            ['id' => 6, 'nombre' => 'Cambio Razon Social',  'activo' => 1],
            ['id' => 7, 'nombre' => 'Otro',                 'activo' => 1],
        ]);
    }
};
