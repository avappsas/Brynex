<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Buscar y eliminar cualquier check constraint existente en la columna 'resultado' de la tabla 'bitacora_cobros'
        DB::statement("
            DECLARE @ConstraintName NVARCHAR(200)
            SELECT @ConstraintName = name
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('bitacora_cobros')
              AND definition LIKE '%resultado%'
            
            IF @ConstraintName IS NOT NULL
            BEGIN
                EXEC('ALTER TABLE bitacora_cobros DROP CONSTRAINT ' + @ConstraintName)
            END
        ");

        // 2. Agregar el nuevo check constraint con 'whatsapp'
        DB::statement("
            ALTER TABLE bitacora_cobros 
            ADD CONSTRAINT CK_bitacora_cobros_resultado 
            CHECK (resultado IN ('no_contesta', 'promesa_pago', 'pagado', 'numero_errado', 'whatsapp', 'otro'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Eliminar nuestro constraint
        DB::statement("
            DECLARE @ConstraintName NVARCHAR(200)
            SELECT @ConstraintName = name
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('bitacora_cobros')
              AND definition LIKE '%resultado%'
            
            IF @ConstraintName IS NOT NULL
            BEGIN
                EXEC('ALTER TABLE bitacora_cobros DROP CONSTRAINT ' + @ConstraintName)
            END
        ");

        // 2. Volver a agregar el check constraint original
        DB::statement("
            ALTER TABLE bitacora_cobros 
            ADD CONSTRAINT CK_bitacora_cobros_resultado 
            CHECK (resultado IN ('no_contesta', 'promesa_pago', 'pagado', 'numero_errado', 'otro'))
        ");
    }
};
