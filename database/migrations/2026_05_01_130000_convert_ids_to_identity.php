<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convertir IDs manuales a IDENTITY auto-increment en:
 *  - clientes          (id manual → id IDENTITY, cedula bigInt indexada)
 *  - razones_sociales  (id manual → id IDENTITY, nit bigInt indexado)
 *  - empresas          (id manual → id IDENTITY, nit bigInt indexado)
 *
 * Seguro: todas las tablas están vacías tras el reset de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Dropear FKs externas que apuntan a razones_sociales y empresas ────────
        // (SQL Server no permite DROP TABLE si hay FK constraints referenciando la tabla)
        $fksToDrop = [
            ['tabla' => 'contratos',     'fk' => 'razon_social_id'],
            ['tabla' => 'incapacidades', 'fk' => 'razon_social_id'],
            ['tabla' => 'planos',        'fk' => 'razon_social_id'],
            ['tabla' => 'bitacora_cobros', 'fk' => 'empresa_id'],
        ];

        foreach ($fksToDrop as $item) {
            // Buscar el nombre exacto del constraint en sys.foreign_keys
            $constraintName = DB::selectOne("
                SELECT fk.name AS constraint_name
                FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
                JOIN sys.columns c ON fkc.parent_object_id = c.object_id
                                   AND fkc.parent_column_id = c.column_id
                WHERE OBJECT_NAME(fk.parent_object_id) = '{$item['tabla']}'
                  AND c.name = '{$item['fk']}'
            ")?->constraint_name;

            if ($constraintName) {
                DB::statement("ALTER TABLE [{$item['tabla']}] DROP CONSTRAINT [{$constraintName}]");
            }
        }

        // ════════════════════════════════════════════════════════
        // 1. CLIENTES  → id IDENTITY + cedula bigInteger indexada
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('clientes', 'U') IS NOT NULL DROP TABLE clientes");
        DB::statement("
            CREATE TABLE clientes (
                id          BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                cedula      BIGINT      NOT NULL,
                id_legacy   INT         NULL,
                cod_empresa INT         NULL,
                tipo_doc    NVARCHAR(10) NULL,
                primer_nombre       NVARCHAR(55) NULL,
                segundo_nombre      NVARCHAR(55) NULL,
                primer_apellido     NVARCHAR(55) NULL,
                segundo_apellido    NVARCHAR(55) NULL,
                genero              NVARCHAR(10) NULL,
                sisben              NVARCHAR(50) NULL,
                fecha_nacimiento    DATE NULL,
                fecha_expedicion    DATE NULL,
                rh                  NVARCHAR(10) NULL,
                telefono            NVARCHAR(20) NULL,
                celular             BIGINT NULL,
                correo              NVARCHAR(100) NULL,
                departamento_id     SMALLINT NULL,
                municipio_id        INT NULL,
                direccion_vivienda  NVARCHAR(150) NULL,
                direccion_cobro     NVARCHAR(150) NULL,
                barrio              NVARCHAR(80) NULL,
                eps_id              BIGINT NULL,
                pension_id          BIGINT NULL,
                ips                 NVARCHAR(100) NULL,
                urgencias           NVARCHAR(100) NULL,
                iva                 NVARCHAR(20) NULL,
                ocupacion           NVARCHAR(80) NULL,
                referido            NVARCHAR(80) NULL,
                observacion         NVARCHAR(MAX) NULL,
                observacion_llamada NVARCHAR(MAX) NULL,
                claves              NVARCHAR(255) NULL,
                datos               NVARCHAR(255) NULL,
                deuda               INT NULL,
                fecha_probable_pago NVARCHAR(50) NULL,
                modo_probable_pago  NVARCHAR(50) NULL,
                created_at          DATETIME2 NULL,
                updated_at          DATETIME2 NULL
            )
        ");
        DB::statement("CREATE INDEX clientes_cedula_index ON clientes (cedula)");
        DB::statement("CREATE INDEX clientes_id_legacy_index ON clientes (id_legacy)");

        // ════════════════════════════════════════════════════════
        // 2. RAZONES SOCIALES → id IDENTITY + nit bigInteger
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('razones_sociales', 'U') IS NOT NULL DROP TABLE razones_sociales");
        DB::statement("
            CREATE TABLE razones_sociales (
                id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                nit                 BIGINT NULL,
                dv                  INT NULL,
                id_legacy           INT NULL,
                aliado_id           BIGINT NULL,
                razon_social        NVARCHAR(255) NULL,
                estado              NVARCHAR(50) NULL,
                plan                NVARCHAR(255) NULL,
                direccion           NVARCHAR(255) NULL,
                telefonos           NVARCHAR(255) NULL,
                correos             NVARCHAR(255) NULL,
                actividad_economica NVARCHAR(255) NULL,
                objeto_social       NVARCHAR(255) NULL,
                observacion         NVARCHAR(255) NULL,
                salario_minimo      DECIMAL(18,2) NULL,
                arl_nit             BIGINT NULL,
                caja_nit            BIGINT NULL,
                mes_pagos           INT NULL,
                anio_pagos          INT NULL,
                n_plano             INT NULL,
                fecha_constitucion  DATETIME2 NULL,
                fecha_limite_pago   DATETIME2 NULL,
                dia_habil           INT NULL,
                forma_presentacion  NVARCHAR(50) NULL,
                codigo_sucursal     NVARCHAR(50) NULL,
                nombre_sucursal     NVARCHAR(100) NULL,
                notas_factura1      NVARCHAR(255) NULL,
                notas_factura2      NVARCHAR(255) NULL,
                dir_formulario      NVARCHAR(100) NULL,
                tel_formulario      NVARCHAR(20) NULL,
                correo_formulario   NVARCHAR(100) NULL,
                cedula_rep          BIGINT NULL,
                nombre_rep          NVARCHAR(100) NULL,
                es_independiente    BIT NOT NULL DEFAULT 0,
                encargado_id        BIGINT NULL,
                created_at          DATETIME2 NULL,
                updated_at          DATETIME2 NULL
            )
        ");
        DB::statement("CREATE INDEX razones_sociales_nit_index ON razones_sociales (nit)");
        DB::statement("CREATE INDEX razones_sociales_id_legacy_index ON razones_sociales (id_legacy)");
        DB::statement("CREATE INDEX razones_sociales_aliado_id_index ON razones_sociales (aliado_id)");

        // ════════════════════════════════════════════════════════
        // 3. EMPRESAS → id IDENTITY + nit bigInteger
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('empresas', 'U') IS NOT NULL DROP TABLE empresas");
        DB::statement("
            CREATE TABLE empresas (
                id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                id_legacy           INT NULL,
                nit                 BIGINT NULL,
                empresa             NVARCHAR(255) NULL,
                contacto            NVARCHAR(255) NULL,
                telefono            NVARCHAR(50) NULL,
                celular             NVARCHAR(50) NULL,
                direccion           NVARCHAR(255) NULL,
                observacion         NVARCHAR(500) NULL,
                cliente_de          NVARCHAR(255) NULL,
                tipo_facturacion    NVARCHAR(30) NULL,
                iva                 NVARCHAR(20) NULL,
                correo              NVARCHAR(150) NULL,
                actividad_economica NVARCHAR(1000) NULL,
                aliado_id           BIGINT NULL,
                asesor_id           BIGINT NULL,
                encargado_id        BIGINT NULL,
                created_at          DATETIME2 NULL,
                updated_at          DATETIME2 NULL
            )
        ");
        DB::statement("CREATE INDEX empresas_nit_index ON empresas (nit)");
        DB::statement("CREATE INDEX empresas_id_legacy_index ON empresas (id_legacy)");

        // ── Recrear las FKs que dropeamos (ahora apuntan a los nuevos ids IDENTITY) ──
        // Sólo si las tablas padre existen y tienen datos o son para integridad futura
        $fksTables = ['contratos', 'incapacidades', 'planos'];
        foreach ($fksTables as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'razon_social_id')) {
                try {
                    Schema::table($tabla, function (Blueprint $table) {
                        $table->foreign('razon_social_id')
                              ->references('id')->on('razones_sociales')
                              ->nullOnDelete();
                    });
                } catch (\Exception $e) {
                    // FK puede ya existir si la migración se reintenta
                }
            }
        }
        if (Schema::hasTable('bitacora_cobros') && Schema::hasColumn('bitacora_cobros', 'empresa_id')) {
            try {
                Schema::table('bitacora_cobros', function (Blueprint $table) {
                    $table->foreign('empresa_id')
                          ->references('id')->on('empresas')
                          ->nullOnDelete();
                });
            } catch (\Exception $e) { /* ya existe */ }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('razones_sociales');
        Schema::dropIfExists('empresas');
    }
};
