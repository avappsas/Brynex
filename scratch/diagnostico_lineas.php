<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Services\MoraClienteService;

echo "Paso 1: Buscando contrato...\n";
$contrato = Contrato::find(10901);
echo "Paso 2: Contrato encontrado: " . ($contrato ? $contrato->id : 'NO') . "\n";

echo "Paso 3: Cargando razon social...\n";
$rsRetiro = $contrato->razonSocial;
echo "Paso 4: Razon social cargada: " . ($rsRetiro ? $rsRetiro->id : 'NO') . "\n";

echo "Paso 5: Probando calcularCotizacion...\n";
$cotizacion = $contrato->calcularCotizacion(1);
echo "Paso 6: Cotizacion calculada: " . json_encode($cotizacion) . "\n";

echo "Paso 7: Calculando mora...\n";
$costoSs = $cotizacion['ss'];
$rsNitRet = (int)($rsRetiro ? ($rsRetiro->nit ?: $rsRetiro->id) : 0);
$rsDiaHRet = $rsRetiro ? ($rsRetiro->dia_habil ?? null) : null;
$moraInfo = MoraClienteService::calcular($contrato->aliado_id, $rsNitRet, $rsDiaHRet, $costoSs, 6, 2026);
echo "Paso 8: Mora calculada: " . json_encode($moraInfo) . "\n";
