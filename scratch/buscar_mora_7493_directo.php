<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Services\MoraClienteService;

$contrato = Contrato::find(10901);
if (!$contrato) {
    echo "Contrato no encontrado\n";
    exit;
}

$rsRetiro = $contrato->razonSocial;
$esIndep = $contrato->esIndependiente() || ($rsRetiro && $rsRetiro->es_independiente);
$rsNitRet = $esIndep ? (int)$contrato->cedula : ($rsRetiro ? (int)($rsNitRet = $rsRetiro->nit ?: $rsRetiro->id) : 0);
$rsDiaHRet = $esIndep ? null : ($rsRetiro ? ($rsRetiro->dia_habil ?? null) : null);

echo "Buscando en PHP directo:\n";
for ($mes = 1; $mes <= 12; $mes++) {
    for ($dias = 1; $dias <= 30; $dias++) {
        // Calcular cotización
        $cotizacion = $contrato->calcularCotizacion($dias);
        $costoSs = (int)($cotizacion['eps'] ?? 0) + (int)($cotizacion['arl'] ?? 0) + (int)($cotizacion['pen'] ?? 0) + (int)($cotizacion['caja'] ?? 0);
        
        $mesVence = $mes + 1;
        $anioVence = 2026;
        if ($mesVence > 12) {
            $mesVence = 1;
            $anioVence++;
        }
        
        $periodoActualNum = now()->year * 100 + now()->month;
        $periodoVenceNum = $anioVence * 100 + $mesVence;
        
        $mora = 0;
        if ($rsNitRet && $costoSs > 0) {
            if ($periodoVenceNum > $periodoActualNum) {
                $mora = 0;
            } else {
                $moraInfo = MoraClienteService::calcular($contrato->aliado_id, $rsNitRet, $rsDiaHRet, $costoSs, $mesVence, $anioVence);
                $mora = (int) round($moraInfo['mora_real'] ?? 0);
            }
        }
        
        if ($mora >= 7400 && $mora <= 7600) {
            echo "Mes Plano: $mes, Dias: $dias -> Costo SS: $costoSs, Mora: $mora\n";
        }
    }
}
echo "Búsqueda finalizada.\n";
