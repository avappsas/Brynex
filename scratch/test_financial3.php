<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aid = 2; // BRYGAR
$mes = 5;
$anio = 2026;

echo "=== DETALLE COMPLETO DE FACTURAS PAGADAS EN MAYO 2026 PARA ALIADO 2 (BRYGAR) ===\n";
$facturas = DB::table('facturas')
    ->where('aliado_id', $aid)
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->get();

foreach ($facturas as $f) {
    echo "ID: {$f->id}, Cedula: {$f->cedula}, NumFac: {$f->numero_factura}, Tipo: {$f->tipo}, Estado: {$f->estado}, TotalSS: " . number_format($f->total_ss) . ", RetiroCampo: " . number_format($f->retiro) . ", Mora: " . number_format($f->mora) . ", Admon: " . number_format($f->admon) . ", Pago: {$f->fecha_pago}\n";
}

echo "\n=== DETALLE COMPLETO DE GASTOS DE MAYO 2026 PARA BRYGAR ===\n";
$gastos = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->get();
foreach ($gastos as $g) {
    echo "ID: {$g->id}, Tipo: {$g->tipo}, Valor: " . number_format($g->valor) . ", Desc: {$g->descripcion}, Forma Pago: {$g->forma_pago}\n";
}
