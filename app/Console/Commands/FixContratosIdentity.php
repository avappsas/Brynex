<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixContratosIdentity extends Command
{
    protected $signature   = 'fix:contratos-identity';
    protected $description = 'Agrega IDENTITY a la columna id de la tabla contratos (SQL Server).';

    public function handle(): int
    {
        $this->info('Verificando si ya tiene IDENTITY...');
        $isId = DB::selectOne("SELECT COLUMNPROPERTY(object_id(N'dbo.contratos'),N'id',N'IsIdentity') AS v")->v;
        if ($isId) {
            $this->info('La columna ya tiene IDENTITY. Nada que hacer.');
            return 0;
        }

        $count = DB::selectOne("SELECT COUNT(*) AS c FROM [contratos]")->c;
        $this->info("Registros actuales: {$count}");

        // 1. Guardar datos
        $this->info('Leyendo datos actuales...');
        $rows = DB::select('SELECT * FROM [contratos]');
        $this->info("Filas leidas: ".count($rows));

        // 2. Quitar FKs que apuntan HACIA contratos (radicados si existen)
        $this->info('Quitando FKs externas...');
        $fksExternas = DB::select("
            SELECT fk.name AS fk_name, OBJECT_NAME(fk.parent_object_id) AS tabla
            FROM sys.foreign_keys fk
            WHERE OBJECT_NAME(fk.referenced_object_id) = 'contratos'
        ");
        foreach ($fksExternas as $fk) {
            DB::statement("ALTER TABLE [{$fk->tabla}] DROP CONSTRAINT [{$fk->fk_name}]");
            $this->line("  - Quitada FK: {$fk->fk_name} en {$fk->tabla}");
        }

        // 3. Quitar FKs propias de contratos
        $this->info('Quitando FKs propias de contratos...');
        $fksPropias = DB::select("
            SELECT name FROM sys.foreign_keys
            WHERE parent_object_id = object_id('contratos')
        ");
        foreach ($fksPropias as $fk) {
            DB::statement("ALTER TABLE [contratos] DROP CONSTRAINT [{$fk->name}]");
        }

        // 4. Eliminar la tabla y recrear con IDENTITY
        $this->info('Eliminando tabla actual...');
        Schema::dropIfExists('contratos');

        $this->info('Creando tabla nueva con IDENTITY...');
        DB::unprepared("
            CREATE TABLE [dbo].[contratos] (
                [id]                    INT             IDENTITY(1,1) NOT NULL,
                [aliado_id]             BIGINT              NULL,
                [cedula]                BIGINT              NOT NULL DEFAULT 0,
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
                CONSTRAINT [PK_contratos_new] PRIMARY KEY ([id])
            );
        ");

        // 5. Copiar datos con IDENTITY_INSERT ON
        if (count($rows) > 0) {
            $this->info('Copiando '.count($rows).' registros con IDENTITY_INSERT ON...');
            DB::statement('SET IDENTITY_INSERT [contratos] ON');
            foreach ($rows as $row) {
                DB::table('contratos')->insert([
                    'id'                     => $row->id,
                    'aliado_id'              => $row->aliado_id,
                    'cedula'                 => $row->cedula,
                    'estado'                 => $row->estado ?? 'vigente',
                    'razon_social_id'        => $row->razon_social_id,
                    'razon_social_bloqueada' => $row->razon_social_bloqueada ?? 0,
                    'plan_id'                => $row->plan_id,
                    'tipo_modalidad_id'      => $row->tipo_modalidad_id,
                    'eps_id'                 => $row->eps_id,
                    'pension_id'             => $row->pension_id,
                    'arl_id'                 => $row->arl_id,
                    'n_arl'                  => $row->n_arl,
                    'arl_modo'               => $row->arl_modo,
                    'caja_id'                => $row->caja_id,
                    'cargo'                  => $row->cargo,
                    'fecha_ingreso'          => $row->fecha_ingreso,
                    'fecha_retiro'           => $row->fecha_retiro,
                    'actividad_economica_id' => $row->actividad_economica_id,
                    'salario'                => $row->salario,
                    'ibc'                    => $row->ibc,
                    'porcentaje_caja'        => $row->porcentaje_caja,
                    'administracion'         => $row->administracion ?? 0,
                    'admon_asesor'           => $row->admon_asesor ?? 0,
                    'costo_afiliacion'       => $row->costo_afiliacion ?? 0,
                    'seguro'                 => $row->seguro ?? 0,
                    'asesor_id'              => $row->asesor_id,
                    'encargado_id'           => $row->encargado_id,
                    'motivo_afiliacion_id'   => $row->motivo_afiliacion_id,
                    'motivo_retiro_id'       => $row->motivo_retiro_id,
                    'fecha_arl'              => $row->fecha_arl,
                    'envio_planilla'         => $row->envio_planilla,
                    'fecha_probable_pago'    => $row->fecha_probable_pago,
                    'modo_probable_pago'     => $row->modo_probable_pago,
                    'observacion'            => $row->observacion,
                    'observacion_afiliacion' => $row->observacion_afiliacion,
                    'observacion_llamada'    => $row->observacion_llamada,
                    'np'                     => $row->np,
                    'fecha_created'          => $row->fecha_created,
                    'created_at'             => $row->created_at,
                    'updated_at'             => $row->updated_at,
                ]);
            }
            DB::statement('SET IDENTITY_INSERT [contratos] OFF');
            $this->info('Datos copiados correctamente.');
        }

        // 6. Recrear FKs propias
        $this->info('Recreando FKs...');
        DB::unprepared("
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_aliado_id_foreign]           FOREIGN KEY ([aliado_id])              REFERENCES [aliados]([id])              ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_razon_social_id_foreign]     FOREIGN KEY ([razon_social_id])        REFERENCES [razones_sociales]([id])     ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_plan_id_foreign]             FOREIGN KEY ([plan_id])                REFERENCES [planes_contrato]([id])      ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_tipo_modalidad_id_foreign]   FOREIGN KEY ([tipo_modalidad_id])      REFERENCES [tipo_modalidad]([id])       ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_eps_id_foreign]              FOREIGN KEY ([eps_id])                 REFERENCES [eps]([id])                  ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_pension_id_foreign]          FOREIGN KEY ([pension_id])             REFERENCES [pensiones]([id])            ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_arl_id_foreign]              FOREIGN KEY ([arl_id])                 REFERENCES [arls]([id])                 ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_caja_id_foreign]             FOREIGN KEY ([caja_id])                REFERENCES [cajas]([id])                ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_actividad_economica_id_foreign] FOREIGN KEY ([actividad_economica_id]) REFERENCES [actividades_economicas]([id]) ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_asesor_id_foreign]           FOREIGN KEY ([asesor_id])              REFERENCES [asesores]([id]);
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_encargado_id_foreign]        FOREIGN KEY ([encargado_id])           REFERENCES [users]([id]);
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_motivo_afiliacion_id_foreign] FOREIGN KEY ([motivo_afiliacion_id])  REFERENCES [motivos_afiliacion]([id])   ON DELETE SET NULL;
            ALTER TABLE [contratos] ADD CONSTRAINT [contratos_motivo_retiro_id_foreign]    FOREIGN KEY ([motivo_retiro_id])       REFERENCES [motivos_retiro]([id])       ON DELETE SET NULL;
        ");

        // 7. Restaurar FKs externas (radicados -> contratos)
        foreach ($fksExternas as $fk) {
            $tablaRef = $fk->tabla;
            // Obtener columna que apunta a contratos
            $col = DB::selectOne("
                SELECT c.name AS col_name
                FROM sys.foreign_key_columns fkc
                INNER JOIN sys.columns c ON c.object_id = fkc.parent_object_id AND c.column_id = fkc.parent_column_id
                WHERE fkc.constraint_object_id = object_id('{$tablaRef}.dbo.{$fk->fk_name}')
            ");
            // Intentar recrear de forma genérica si podemos
            $this->line("  - FK externa '{$fk->fk_name}' en '{$tablaRef}' debe recrearse manualmente si aplica.");
        }

        $this->info('');
        $this->info('✓ Columna id de contratos ahora tiene IDENTITY. Inserts se generarán automáticamente.');

        return 0;
    }
}
