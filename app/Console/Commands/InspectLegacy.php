<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectLegacy extends Command
{
    protected $signature = 'legacy:inspect {--table= : Inspeccionar solo una tabla} {--db=Brygar_BD : BD legacy a inspeccionar}';
    protected $description = 'Inspecciona estructura de tablas legacy para migración';

    private $tables = [
        'usuarios',
        'Razon_Social',
        'Empresas',
        'Asesores',
        'Bancos_cuentas',
        'Base_De_Datos',
        'Contratos',
        'beneficiarios',
        'facturacion',
        'Planos',
        'claves',
        'incapacidades',
        'Gestion_Incapacidades',
        'gastos',
        'movimientos_bancos',
        'tareas',
        'bitacora_afiliaciones',
    ];

    public function handle()
    {
        $db = $this->option('db');
        $singleTable = $this->option('table');

        // Configure connection dynamically
        config(["database.connections.sqlsrv_legacy.database" => $db]);
        DB::purge('sqlsrv_legacy');

        $this->info("\n" . str_repeat('=', 80));
        $this->info("  DATABASE: $db");
        $this->info(str_repeat('=', 80));

        $tablesToInspect = $singleTable ? [$singleTable] : $this->tables;

        foreach ($tablesToInspect as $table) {
            $this->inspectTable($table);
        }

        $this->info("\n✅ Inspección completa.");
    }

    private function inspectTable(string $table): void
    {
        $this->line("\n  ┌─ TABLE: $table");

        try {
            $exists = DB::connection('sqlsrv_legacy')->select(
                "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?",
                [$table]
            );

            if (!$exists[0]->cnt) {
                $this->warn("  │  ⚠️  NO EXISTE");
                $this->line("  └─");
                return;
            }

            // Count
            $count = DB::connection('sqlsrv_legacy')->selectOne("SELECT COUNT(*) as cnt FROM [$table]")->cnt;
            $this->line("  │  Registros: $count");
            $this->line("  │");

            // Columns
            $cols = DB::connection('sqlsrv_legacy')->select("
                SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE,
                       COLUMNPROPERTY(OBJECT_ID(?), COLUMN_NAME, 'IsIdentity') as IS_IDENTITY
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ", [$table, $table]);

            $this->line("  │  " . str_pad('COLUMNA', 35) . str_pad('TIPO', 20) . str_pad('NULL', 8) . "IDENT");
            $this->line("  │  " . str_repeat('─', 70));

            foreach ($cols as $c) {
                $type = $c->DATA_TYPE;
                if ($c->CHARACTER_MAXIMUM_LENGTH) {
                    $len = $c->CHARACTER_MAXIMUM_LENGTH == -1 ? 'MAX' : $c->CHARACTER_MAXIMUM_LENGTH;
                    $type .= "($len)";
                }
                $ident = $c->IS_IDENTITY ? '✓' : '';
                $this->line("  │  " . str_pad($c->COLUMN_NAME, 35) . str_pad($type, 20) . str_pad($c->IS_NULLABLE, 8) . $ident);
            }

            // Show 2 sample rows
            $this->line("  │");
            $this->line("  │  MUESTRA (2 filas):");
            $rows = DB::connection('sqlsrv_legacy')->select("SELECT TOP 2 * FROM [$table]");
            foreach ($rows as $i => $row) {
                $parts = [];
                foreach ((array)$row as $k => $v) {
                    $val = $v === null ? 'NULL' : (strlen((string)$v) > 25 ? substr((string)$v, 0, 25) . '...' : $v);
                    $parts[] = "$k=$val";
                }
                $this->line("  │  Row $i: " . implode(' | ', $parts));
            }

        } catch (\Throwable $e) {
            $this->error("  │  ❌ Error: " . $e->getMessage());
        }

        $this->line("  └─");
    }
}
