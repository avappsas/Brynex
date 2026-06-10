<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Factura;

foreach ([10853, 144] as $cid) {
    $hasPlanilla = Factura::where('contrato_id', $cid)
        ->where('tipo', 'planilla')
        ->where('dias_cotizados', '>', 0)
        ->where('numero_factura', '>', 0)
        ->exists();
    echo "Contrato $cid tiene planilla dias > 0: " . ($hasPlanilla ? 'SI' : 'NO') . "\n";
}
