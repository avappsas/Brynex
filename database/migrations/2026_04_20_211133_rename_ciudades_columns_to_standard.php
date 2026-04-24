<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Normalizar la tabla 'ciudades' al esquema estándar BryNex.
 *
 * Estado actual de la tabla (importada desde BD legada):
 *   IdCiudad (float)    → PK real (referenciada por clientes.municipio_id int)
 *   Municipio_No (int)  → FK a departamentos.id
 *   Ciudad (nvarchar)   → nombre de la ciudad
 *   Ciudad_aportes      → ciudad_aportes
 *   Ciudad_Asopagos     → ciudad_asopagos
 *
 * Cambios:
 *   1. Dropear FK ficticia clientes_municipio_id_foreign (referencia a ciudades.id
 *      que no existe — quedó huérfana de la migración inicial).
 *   2. Renombrar columnas de ciudades para alinear con el esquema del código.
 *   3. Cambiar tipo de 'id' (ex-IdCiudad) de float a int para compatibilidad con
 *      clientes.municipio_id (int).
 *   4. Definir PK en ciudades.id
 *   5. Recrear FK clientes.municipio_id → ciudades.id (ahora int, compatible).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Dropear FK huérfana en clientes (apuntaba a ciudades.id que no existía)
        //    La FK clientes_municipio_id_foreign sobrevivió la migración fallida.
        //    Verificamos si existe antes de intentar dropearla.
        $fkExists = DB::select(
            "SELECT 1 FROM sys.foreign_keys WHERE name = 'clientes_municipio_id_foreign' AND OBJECT_NAME(parent_object_id) = 'clientes'"
        );
        if (!empty($fkExists)) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign('clientes_municipio_id_foreign');
            });
        }

        // 2. Renombrar columnas en 'ciudades' (sp_rename para SQL Server)
        DB::statement("EXEC sp_rename 'ciudades.IdCiudad',        'id',              'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.Municipio_No',    'departamento_id', 'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.Ciudad',          'nombre',          'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.Ciudad_aportes',  'ciudad_aportes',  'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.Ciudad_Asopagos', 'ciudad_asopagos', 'COLUMN'");

        // 3. Cambiar tipo de 'id' de float a int (para compatibilidad con clientes.municipio_id int)
        //    Los valores DANE de municipios son enteros (ej: 76001), así que el cast es seguro.
        DB::statement("ALTER TABLE ciudades ALTER COLUMN id INT NOT NULL");

        // 4. Definir PK en ciudades.id
        DB::statement("ALTER TABLE ciudades ADD CONSTRAINT pk_ciudades_id PRIMARY KEY (id)");

        // 5. Recrear FK clientes.municipio_id → ciudades.id (int ↔ int, compatible)
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreign('municipio_id')
                  ->references('id')
                  ->on('ciudades')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revertir en orden inverso
        $fkExists = DB::select(
            "SELECT 1 FROM sys.foreign_keys WHERE name = 'clientes_municipio_id_foreign' AND OBJECT_NAME(parent_object_id) = 'clientes'"
        );
        if (!empty($fkExists)) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign('clientes_municipio_id_foreign');
            });
        }

        // Dropear PK antes de cambiar tipo
        DB::statement("ALTER TABLE ciudades DROP CONSTRAINT pk_ciudades_id");

        // Restaurar tipo float en id (ex-IdCiudad)
        DB::statement("ALTER TABLE ciudades ALTER COLUMN id FLOAT NOT NULL");

        // Renombrar de vuelta
        DB::statement("EXEC sp_rename 'ciudades.id',              'IdCiudad',        'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.departamento_id', 'Municipio_No',    'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.nombre',          'Ciudad',          'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.ciudad_aportes',  'Ciudad_aportes',  'COLUMN'");
        DB::statement("EXEC sp_rename 'ciudades.ciudad_asopagos', 'Ciudad_Asopagos', 'COLUMN'");
    }
};
