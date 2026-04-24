<?php
// ⚠️ ELIMINAR ESTE ARCHIVO DESPUÉS DE USARLO
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$results = [];

// Limpiar cachés
$calls = [
    'config:clear'      => 'Caché de configuración',
    'cache:clear'       => 'Caché de aplicación',
    'route:clear'       => 'Caché de rutas',
    'view:clear'        => 'Caché de vistas',
    'optimize:clear'    => 'Optimize clear',
];

foreach ($calls as $cmd => $label) {
    try {
        Artisan::call($cmd);
        $results[] = "✅ {$label} — OK";
    } catch (\Throwable $e) {
        $results[] = "❌ {$label} — " . $e->getMessage();
    }
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "<strong>BryNex — Limpieza de Caché</strong>\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n⚠️  Elimina este archivo del servidor: public/fix_config_cache.php";
echo '</pre>';
