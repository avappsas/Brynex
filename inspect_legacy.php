<?php
/**
 * Inspecciona tablas legacy de Brygar_BD via Laravel DB facade.
 * Ejecutar: php artisan tinker --execute="require 'inspect_legacy.php';"
 */

use Illuminate\Support\Facades\DB;

$tables = [
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

$conn = 'sqlsrv_legacy';
echo "\n" . str_repeat('=', 80) . "\n";
echo "  DATABASE: Brygar_BD (via sqlsrv_legacy)\n";
echo str_repeat('=', 80) . "\n";

foreach ($tables as $table) {
    echo "\n  ┌─ TABLE: $table\n";

    // Check if table exists
    $exists = DB::connection($conn)->select("
        SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_NAME = ?
    ", [$table]);

    if (!$exists[0]->cnt) {
        echo "  │  ⚠️  NO EXISTE\n";
        echo "  └─\n";
        continue;
    }

    // Count
    $count = DB::connection($conn)->selectOne("SELECT COUNT(*) as cnt FROM [$table]")->cnt;
    echo "  │  Registros: $count\n";
    echo "  │\n";

    // Columns
    $cols = DB::connection($conn)->select("
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE,
               COLUMNPROPERTY(OBJECT_ID(?), COLUMN_NAME, 'IsIdentity') as IS_IDENTITY
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ", [$table, $table]);

    echo "  │  " . str_pad('COLUMNA', 35) . str_pad('TIPO', 20) . str_pad('NULL', 8) . "IDENT\n";
    echo "  │  " . str_repeat('─', 70) . "\n";

    foreach ($cols as $c) {
        $type = $c->DATA_TYPE;
        if ($c->CHARACTER_MAXIMUM_LENGTH) {
            $len = $c->CHARACTER_MAXIMUM_LENGTH == -1 ? 'MAX' : $c->CHARACTER_MAXIMUM_LENGTH;
            $type .= "($len)";
        }
        $ident = $c->IS_IDENTITY ? '✓' : '';
        echo "  │  " . str_pad($c->COLUMN_NAME, 35) . str_pad($type, 20) . str_pad($c->IS_NULLABLE, 8) . $ident . "\n";
    }

    // Show 2 sample rows
    echo "  │\n  │  MUESTRA (2 filas):\n";
    $rows = DB::connection($conn)->select("SELECT TOP 2 * FROM [$table]");
    foreach ($rows as $i => $row) {
        echo "  │  Row $i: ";
        $parts = [];
        foreach ((array)$row as $k => $v) {
            $val = $v === null ? 'NULL' : (strlen((string)$v) > 25 ? substr((string)$v, 0, 25) . '...' : $v);
            $parts[] = "$k=$val";
        }
        echo implode(' | ', $parts) . "\n";
    }

    echo "  └─\n";
}

echo "\n✅ Inspección completa.\n";
