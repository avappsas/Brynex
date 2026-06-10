<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Iniciando test DB...\n";
try {
    $res = DB::select('SELECT 1');
    echo "Resultado DB: " . json_encode($res) . "\n";
} catch (\Exception $e) {
    echo "Error DB: " . $e->getMessage() . "\n";
}
