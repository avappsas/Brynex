<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aid = 2; // BRYGAR
$mes = 5;
$anio = 2026;

echo "=== BUSCANDO EL VALOR EXACTO 704193 O PROPORCIONAL A 704.193 EN TODA LA BASE DE DATOS ===\n";

// 1. Facturas
$fac = DB::table('facturas')
    ->where(function($q) {
        $q->where('total_ss', 704193)
          ->orWhere('mora', 704193)
          ->orWhere('retiro', 704193)
          ->orWhere('admon', 704193);
    })->get();
echo "Encontradas en facturas: " . count($fac) . "\n";
foreach ($fac as $f) {
    echo "Factura ID: {$f->id}, Cedula: {$f->cedula}, NumFac: {$f->numero_factura}, Tipo: {$f->tipo}, Pago: {$f->fecha_pago}\n";
}

// 2. Gastos
$gas = DB::table('gastos')
    ->where('valor', 704193)
    ->get();
echo "Encontradas en gastos: " . count($gas) . "\n";
foreach ($gas as $g) {
    echo "Gasto ID: {$g->id}, Aliado: {$g->aliado_id}, Tipo: {$g->tipo}, Valor: {$g->valor}, Desc: {$g->descripcion}, Fecha: {$g->fecha}\n";
}

// 3. Consignaciones
$con = DB::table('consignaciones')
    ->where('valor', 704193)
    ->get();
echo "Encontradas en consignaciones: " . count($con) . "\n";
foreach ($con as $c) {
    echo "Consignacion ID: {$c->id}, Aliado: {$c->aliado_id}, Valor: {$c->valor}, Ref: {$c->referencia}, Obs: {$c->observacion}, Fecha: {$c->fecha}\n";
}

// 4. Si no se encuentra el valor exacto, busquemos valores entre 704000 y 705000 en mayo 2026 para BRYGAR
echo "\n=== BUSCANDO VALORES CERCANOS EN MAYO 2026 (700.000 a 710.000) ===\n";
$facCercanas = DB::table('facturas')
    ->where('aliado_id', $aid)
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->get();

foreach ($facCercanas as $f) {
    $sum = $f->admon + $f->seguro + $f->afiliacion + $f->mensajeria + $f->otros + $f->iva + $f->retiro;
    if (($sum >= 700000 && $sum <= 710000) || ($f->total_ss >= 700000 && $f->total_ss <= 710000) || ($f->retiro >= 700000 && $f->retiro <= 710000)) {
        echo "Factura ID: {$f->id}, Cedula: {$f->cedula}, NumFac: {$f->numero_factura}, Total SS: " . number_format($f->total_ss) . ", Suma Admon+Seg: " . number_format($sum) . ", Retiro: " . number_format($f->retiro) . ", Pago: {$f->fecha_pago}\n";
    }
}

$gasCercanos = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->whereBetween('valor', [700000, 710000])
    ->get();
foreach ($gasCercanos as $g) {
    echo "Gasto ID: {$g->id}, Tipo: {$g->tipo}, Valor: " . number_format($g->valor) . ", Desc: {$g->descripcion}, Fecha: {$g->fecha}\n";
}

// 5. Miremos qué facturas de retiro hay específicas (con numero_factura = 0) de BRYGAR en mayo 2026
echo "\n=== FACTURAS DE RETIRO (numero_factura = 0) DE BRYGAR EN MAYO 2026 ===\n";
$facRetiro = DB::table('facturas')
    ->where('aliado_id', $aid)
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where('numero_factura', 0)
    ->get();
foreach ($facRetiro as $f) {
    echo "Factura Retiro ID: {$f->id}, Cedula: {$f->cedula}, Total SS: " . number_format($f->total_ss) . ", Pago: {$f->fecha_pago}\n";
}

// Suma de total_ss de retiros
echo "Suma Total SS de Facturas Retiro: " . number_format($facRetiro->sum('total_ss')) . "\n";
