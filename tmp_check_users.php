<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO TABLA users ===\n\n";

// Columnas reales via INFORMATION_SCHEMA
$cols = DB::select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' ORDER BY ORDINAL_POSITION");
echo "Columnas en la BD:\n";
foreach ($cols as $c) {
    echo "  - {$c->COLUMN_NAME} ({$c->DATA_TYPE})\n";
}

echo "\nSchema::hasColumn('users', 'cedula') = " . (Schema::hasColumn('users', 'cedula') ? 'TRUE' : 'FALSE') . "\n";
echo "Schema::hasColumn('users', 'telefono') = " . (Schema::hasColumn('users', 'telefono') ? 'TRUE' : 'FALSE') . "\n";

// Verificar migraciones ejecutadas
echo "\nMigraciones relacionadas con 'users' en tabla migrations:\n";
$migs = DB::table('migrations')->where('migration', 'like', '%user%')->get();
foreach ($migs as $m) {
    echo "  [{$m->batch}] {$m->migration}\n";
}

// Verificar si la migración de cedula está registrada
$cedulaMig = DB::table('migrations')->where('migration', '2026_04_21_163030_add_cedula_to_users_table')->first();
echo "\nMigración add_cedula_to_users_table: " . ($cedulaMig ? "REGISTRADA (batch {$cedulaMig->batch})" : "NO REGISTRADA") . "\n";
