<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Agrega IDENTITY a la columna 'id' de la tabla 'contratos'.
 *
 * SQL Server no permite ALTER COLUMN para agregar IDENTITY directamente.
 * La estrategia es:
 * 1. Renombrar la tabla actual a contratos_backup
 * 2. Crear la tabla nueva con id IDENTITY
 * 3. Copiar todos los datos usando SET IDENTITY_INSERT ON
 * 4. Eliminar la backup
 * 5. Recrear las FKs
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar si ya existe contratos_backup y limpiar
        Schema::dropIfExists('contratos_backup');

        // 1. Renombrar la tabla actual
        DB::statement('EXEC sp_rename [contratos], [contratos_backup]');

        // 2. Crear nueva tabla con IDENTITY en id
        DB::unprepared("
            CREATE TABLE [dbo].[contratos] (
                [id]                    INT             IDENTITY(1,1) NOT NULL,
                [aliado_id]             BIGINT              NULL,
                [cedula]                BIGINT              NOT NULL,
                [estado]                NVARCHAR(20)    NOT NULL DEFAULT 'vigente',
                [razon_social_id]       INT                 NULL,
                [razon_social_bloqueada] BIT             NOT NULL DEFAULT 0,
                [plan_id]               TINYINT             NULL,
                [tipo_modalidad_id]     SMALLINT            NULL,
                [eps_id]                BIGINT              NULL,
                [pension_id]            BIGINT              NULL,
                [arl_id]                BIGINT              NULL,
                [n_arl]                 TINYINT             NULL,
                [arl_modo]              NVARCHAR(20)        NULL,
                [caja_id]               BIGINT              NULL,
                [cargo]                 NVARCHAR(255)       NULL,
                [fecha_ingreso]         DATE                NULL,
                [fecha_retiro]          DATE                NULL,
                [actividad_economica_id] BIGINT             NULL,
                [salario]               DECIMAL(18,2)       NULL,
                [ibc]                   DECIMAL(18,2)       NULL,
                [porcentaje_caja]       DECIMAL(5,2)        NULL,
                [administracion]        DECIMAL(10,2)   NOT NULL DEFAULT 0,
                [admon_asesor]          DECIMAL(10,2)   NOT NULL DEFAULT 0,
                [costo_afiliacion]      DECIMAL(10,2)   NOT NULL DEFAULT 0,
                [seguro]                DECIMAL(10,2)   NOT NULL DEFAULT 0,
                [asesor_id]             BIGINT              NULL,
                [encargado_id]          BIGINT              NULL,
                [motivo_afiliacion_id]  TINYINT             NULL,
                [motivo_retiro_id]      TINYINT             NULL,
                [fecha_arl]             DATE                NULL,
                [envio_planilla]        NVARCHAR(55)        NULL,
                [fecha_probable_pago]   NVARCHAR(255)       NULL,
                [modo_probable_pago]    NVARCHAR(255)       NULL,
                [observacion]           NVARCHAR(MAX)       NULL,
                [observacion_afiliacion] NVARCHAR(MAX)      NULL,
                [observacion_llamada]   NVARCHAR(MAX)       NULL,
                [np]                    NVARCHAR(255)       NULL,
                [fecha_created]         DATETIME            NULL,
                [created_at]            DATETIME            NULL,
                [updated_at]            DATETIME            NULL,
                CONSTRAINT [PK_contratos] PRIMARY KEY ([id])
            );
        ");

        // 3. Copiar datos preservando IDs originales
        DB::unprepared("
            SET IDENTITY_INSERT [contratos] ON;
            INSERT INTO [contratos] (
                [id],[aliado_id],[cedula],[estado],[razon_social_id],[razon_social_bloqueada],
                [plan_id],[tipo_modalidad_id],[eps_id],[pension_id],[arl_id],[n_arl],[arl_modo],[caja_id],
                [cargo],[fecha_ingreso],[fecha_retiro],[actividad_economica_id],
                [salario],[ibc],[porcentaje_caja],[administracion],[admon_asesor],[costo_afiliacion],[seguro],
                [asesor_id],[encargado_id],[motivo_afiliacion_id],[motivo_retiro_id],[fecha_arl],
                [envio_planilla],[fecha_probable_pago],[modo_probable_pago],
                [observacion],[observacion_afiliacion],[observacion_llamada],[np],
                [fecha_created],[created_at],[updated_at]
            )
            SELECT
                [id],[aliado_id],[cedula],[estado],[razon_social_id],[razon_social_bloqueada],
                [plan_id],[tipo_modalidad_id],[eps_id],[pension_id],[arl_id],[n_arl],[arl_modo],[caja_id],
                [cargo],[fecha_ingreso],[fecha_retiro],[actividad_economica_id],
                [salario],[ibc],[porcentaje_caja],[administracion],[admon_asesor],[costo_afiliacion],[seguro],
                [asesor_id],[encargado_id],[motivo_afiliacion_id],[motivo_retiro_id],[fecha_arl],
                [envio_planilla],[fecha_probable_pago],[modo_probable_pago],
                [observacion],[observacion_afiliacion],[observacion_llamada],[np],
                [fecha_created],[created_at],[updated_at]
            FROM [contratos_backup];
            SET IDENTITY_INSERT [contratos] OFF;
        ");

        // 4. Agregar FKs a la nueva tabla
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('planes_contrato')->nullOnDelete();
            $table->foreign('tipo_modalidad_id')->references('id')->on('tipo_modalidad')->nullOnDelete();
            $table->foreign('eps_id')->references('id')->on('eps')->nullOnDelete();
            $table->foreign('pension_id')->references('id')->on('pensiones')->nullOnDelete();
            $table->foreign('arl_id')->references('id')->on('arls')->nullOnDelete();
            $table->foreign('caja_id')->references('id')->on('cajas')->nullOnDelete();
            $table->foreign('actividad_economica_id')->references('id')->on('actividades_economicas')->nullOnDelete();
            $table->foreign('asesor_id')->references('id')->on('asesores')->noActionOnDelete();
            $table->foreign('encargado_id')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('motivo_afiliacion_id')->references('id')->on('motivos_afiliacion')->nullOnDelete();
            $table->foreign('motivo_retiro_id')->references('id')->on('motivos_retiro')->nullOnDelete();
        });

        // 5. Eliminar tabla backup
        Schema::dropIfExists('contratos_backup');
    }

    public function down(): void
    {
        // No reversible de forma simple - la columna id no puede perder IDENTITY sin datos
        // Se puede recrear manualmente si es necesario
    }
};
