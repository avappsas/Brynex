<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El chat de WhatsApp clasifica cada contacto (cliente / excliente / nuevo)
     * cruzando el celular contra `clientes`. Sin índice por celular, esa consulta
     * escanea las 31k filas de la tabla en cada carga del inbox.
     *
     * Se incluye `cedula` para que el join contra `contratos` (que sí tiene
     * idx_contratos_aliado_cedula) se resuelva sin volver a la tabla base.
     */
    public function up(): void
    {
        DB::statement("
            IF NOT EXISTS (SELECT 1 FROM sys.indexes
                           WHERE object_id = OBJECT_ID('clientes')
                             AND name = 'idx_clientes_aliado_celular')
            CREATE NONCLUSTERED INDEX idx_clientes_aliado_celular
                ON clientes (aliado_id, celular) INCLUDE (cedula)
        ");
    }

    public function down(): void
    {
        DB::statement("
            IF EXISTS (SELECT 1 FROM sys.indexes
                       WHERE object_id = OBJECT_ID('clientes')
                         AND name = 'idx_clientes_aliado_celular')
            DROP INDEX idx_clientes_aliado_celular ON clientes
        ");
    }
};
