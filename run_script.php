<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::unprepared(file_get_contents(__DIR__.'/sql_migration/01-empresas-BG.sql'));
    echo "¡Migración ejecutada con éxito!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
