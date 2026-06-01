<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aid = 2; // BRYGAR
$mes = 5;
$anio = 2026;

// Buscar en la tabla consignaciones de mayo de 2026 para el aliado 2 (BRYGAR)
echo "=== CONSIGNACIONES MAYO 2026 PARA BRYGAR (Aliado 2) ===\n";
$consig = DB::table('consignaciones')
    ->where('aliado_id', $aid)
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->get();

foreach ($consig as $c) {
    if ($c->valor > 100000) {
        echo "ID: {$c->id}, Valor: " . number_format($c->valor) . ", Ref: {$c->referencia}, Obs: {$c->observacion}, Fecha: {$c->fecha}\n";
    }
}

// Buscar en la tabla gastos de mayo de 2026 para BRYGAR con valor > 100.000 y tipo != pago_planilla
echo "\n=== GASTOS EXTRAORDINARIOS/OPERATIVOS MAYO 2026 PARA BRYGAR ===\n";
$gastos = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->where('tipo', '!=', 'pago_planilla')
    ->get();

foreach ($gastos as $g) {
    echo "ID: {$g->id}, Tipo: {$g->tipo}, Valor: " . number_format($g->valor) . ", Desc: {$g->descripcion}, Fecha: {$g->fecha}\n";
}

// Buscar en facturas de mayo 2026 para BRYGAR con tipo planilla pero que tengan algo particular
echo "\n=== FACTURAS PLANILLA MAYO 2026 CON VALOR RETIRO U OTRO CAMPO ALTO ===\n";
$facturas = DB::table('facturas')
    ->where('aliado_id', $aid)
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where(function($q) {
        $q->where('retiro', '>', 0)
          ->orWhere('mora', '>', 50000);
    })
    ->get();

foreach ($facturas as $f) {
    echo "ID: {$f->id}, Cedula: {$f->cedula}, NumFac: {$f->numero_factura}, Retiro: " . number_format($f->retiro) . ", Mora: " . number_format($f->mora) . ", TotalSS: " . number_format($f->total_ss) . ", Pago: {$f->fecha_pago}\n";
}
