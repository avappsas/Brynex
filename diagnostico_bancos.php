<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Ver últimas consignaciones
$rows = DB::table('consignaciones')
    ->select('id','aliado_id','banco_cuenta_id','fecha','valor','confirmado','tipo')
    ->orderByDesc('fecha')
    ->limit(5)
    ->get();

echo "=== Últimas consignaciones ===\n";
foreach ($rows as $r) {
    echo json_encode($r) . "\n";
}

// Total confirmadas
$total = DB::table('consignaciones')->where('confirmado', 1)->count();
$sinConf = DB::table('consignaciones')->where('confirmado', 0)->count();
$nulos = DB::table('consignaciones')->whereNull('confirmado')->count();
echo "\nConfirmadas: $total | Sin confirmar: $sinConf | NULL: $nulos\n";

// Ver banco_cuentas
echo "\n=== Cuentas bancarias ===\n";
$bancos = DB::table('banco_cuentas')->get();
foreach ($bancos as $b) {
    echo "id={$b->id} banco={$b->banco} activo={$b->activo}\n";
}
