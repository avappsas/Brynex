<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Services\MoraClienteService;
use App\Models\ConfiguracionBrynex;

$contrato = Contrato::with(['razonSocial', 'tipoModalidad'])->find(10901);
$costoSs = 12300;

foreach ([5, 6] as $mesPlano) {
    $anioPlano = 2026;
    $mesVence = $mesPlano + 1;
    $anioVence = $anioPlano;
    if ($mesVence > 12) {
        $mesVence = 1;
        $anioVence++;
    }

    $rsRetiro = $contrato->razonSocial;
    $esIndep = $contrato->esIndependiente() || ($rsRetiro && $rsRetiro->es_independiente);
    $rsNitRet = $esIndep ? (int)$contrato->cedula : ($rsRetiro ? (int)($rsRetiro->nit ?: $rsRetiro->id) : 0);
    $rsDiaHRet = $esIndep ? null : ($rsRetiro ? ($rsRetiro->dia_habil ?? null) : null);

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

    echo "Mes Plano: $mesPlano, Vence: $mesVence | Mora calculada: $mora\n";
    if ($mora > 0) {
        echo "Detalles Mora para Mes Vence $mesVence: " . json_encode(MoraClienteService::calcular($contrato->aliado_id, $rsNitRet, $rsDiaHRet, $costoSs, $mesVence, $anioVence)) . "\n";
    }
}
