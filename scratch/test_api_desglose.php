<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Http\Controllers\Admin\ContratoController;

$contrato = Contrato::find(10901);
session(['aliado_id_activo' => $contrato->aliado_id]);

$controller = new ContratoController();
$req = new \Illuminate\Http\Request([
    'dias' => 1,
    'mes_plano' => 6,
    'anio_plano' => 2026,
    'tipo_retiro' => 'real'
]);

echo "Llamando apiCalcularRetiro para mes_plano = 6 (Junio):\n";
$resp = $controller->apiCalcularRetiro($req, 10901);
echo "Respuesta: " . json_encode(json_decode($resp->getContent(), true), JSON_PRETTY_PRINT) . "\n\n";

$reqMayo = new \Illuminate\Http\Request([
    'dias' => 1,
    'mes_plano' => 5,
    'anio_plano' => 2026,
    'tipo_retiro' => 'real'
]);
echo "Llamando apiCalcularRetiro para mes_plano = 5 (Mayo, vence Junio y genera mora):\n";
$respMayo = $controller->apiCalcularRetiro($reqMayo, 10901);
echo "Respuesta Mayo: " . json_encode(json_decode($respMayo->getContent(), true), JSON_PRETTY_PRINT) . "\n";
