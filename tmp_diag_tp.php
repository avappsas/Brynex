<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contrato;
use App\Models\ConfiguracionBrynex;
use App\Models\ArlTarifa;

$c = Contrato::with(['eps','arl','pension','caja','tipoModalidad','razonSocial','cliente','plan'])->find(10054);
if (!$c) { echo "No encontrado\n"; exit; }

echo "══ Contrato #10054 ══\n";
echo "tipo_modalidad_id: " . $c->tipo_modalidad_id . "\n";
echo "plan_id:           " . $c->plan_id . "\n";
echo "salario:           " . $c->salario . "\n";
echo "ibc:               " . $c->ibc . "\n";
echo "n_arl:             " . ($c->n_arl ?? 'null') . "\n";
echo "porcentaje_caja:   " . ($c->porcentaje_caja ?? 'null') . "\n";
echo "\n";

$mod = $c->tipoModalidad;
echo "── Modalidad ──\n";
echo "nombre:           " . ($mod->nombre ?? 'null') . "\n";
echo "es_tiempo_parcial:" . ($mod && $mod->esTiempoParcial() ? 'SÍ' : 'NO') . "\n";
echo "esIndependiente:  " . ($c->esIndependiente() ? 'SÍ' : 'NO') . "\n";

if ($mod && $mod->esTiempoParcial()) {
    $diasP = $mod->diasPorEntidad();
    echo "diasPorEntidad:   " . json_encode($diasP) . "\n";
}
echo "\n";

$plan = $c->plan;
echo "── Plan ──\n";
echo "nombre:           " . ($plan->nombre ?? 'null') . "\n";
echo "incluye_eps:      " . ($plan && $plan->incluye_eps ? 'SÍ' : 'NO') . "\n";
echo "incluye_arl:      " . ($plan && $plan->incluye_arl ? 'SÍ' : 'NO') . "\n";
echo "incluye_pension:  " . ($plan && $plan->incluye_pension ? 'SÍ' : 'NO') . "\n";
echo "incluye_caja:     " . ($plan && $plan->incluye_caja ? 'SÍ' : 'NO') . "\n";
echo "\n";

// Mostrar porcentajes
$sm = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);
echo "── Salario mínimo: $$sm ──\n";

$pctArl = ArlTarifa::porcentajePara((int)($c->n_arl ?? 1), $c->aliado_id);
$pctPen = ConfiguracionBrynex::pctPensionIndependiente();
$esIndep = $c->esIndependiente();
$pctCaja = $esIndep 
    ? (float)($c->porcentaje_caja ?? ConfiguracionBrynex::pctCajaIndependienteAlto())
    : ConfiguracionBrynex::pctCajaDependiente();
echo "pctArl:  $pctArl%\n";
echo "pctPen:  $pctPen%\n";
echo "pctCaja: $pctCaja%\n";
echo "\n";

// Simular cálculo TP manual
if ($mod && $mod->esTiempoParcial()) {
    $diasP = $mod->diasPorEntidad();
    $factorMap = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
    $factorAfp  = $factorMap[$diasP['afp']]  ?? 1.0;
    $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

    echo "── Cálculo TP manual ──\n";
    echo "dias_arl:  " . $diasP['arl'] . "  (factor: 1.00)\n";
    echo "dias_afp:  " . $diasP['afp'] . "  (factor: $factorAfp)\n";
    echo "dias_caja: " . $diasP['caja'] . "  (factor: $factorCaja)\n";

    $ibcArl  = $sm;
    $ibcAfp  = round($sm * $factorAfp);
    $ibcCaja = round($sm * $factorCaja);
    echo "ibcArl:  $$ibcArl\n";
    echo "ibcAfp:  $$ibcAfp\n";
    echo "ibcCaja: $$ibcCaja\n";

    $r = fn($v) => (int)(ceil($v / 100) * 100);
    $arl  = ($plan && $plan->incluye_arl)    ? $r($ibcArl  * $pctArl  / 100) : 0;
    $pen  = ($plan && $plan->incluye_pension) ? $r($ibcAfp  * $pctPen  / 100) : 0;
    $caja = ($plan && $plan->incluye_caja)   ? $r($ibcCaja * $pctCaja / 100) : 0;
    echo "ARL:  $$arl\n";
    echo "AFP:  $$pen\n";
    echo "Caja: $$caja\n";
    echo "SS:   $" . ($arl + $pen + $caja) . "\n";
}

echo "\n── calcularCotizacion() ──\n";
$cot = $c->calcularCotizacion(30);
echo "EPS={$cot['eps']}  ARL={$cot['arl']}  AFP={$cot['pen']}  Caja={$cot['caja']}  SS={$cot['ss']}\n";

// Ver estructura de TipoModalidad
echo "\n── TipoModalidad raw ──\n";
$mod2 = \Illuminate\Support\Facades\DB::table('tipo_modalidades')->find($c->tipo_modalidad_id);
echo json_encode((array)$mod2, JSON_PRETTY_PRINT) . "\n";
