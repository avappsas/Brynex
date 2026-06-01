<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$mes = 5;
$anio = 2026;

// Buscar facturas de retiro (numero_factura = 0) o gastos o ingresos de TODOS los aliados en mayo de 2026
// para localizar el valor de $ 704.193

echo "--- BUSCANDO VALOR 704.193 EN FACTURAS REGULARES (campo retiro o total) o DE RETIRO (total_ss) ---\n";
$facturas = DB::table('facturas')
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->get();

foreach ($facturas as $f) {
    if (abs($f->total_ss - 704193) < 1000 || abs($f->retiro - 704193) < 1000 || abs($f->admon - 704193) < 1000 || abs(($f->total_ss + $f->mora) - 704193) < 1000) {
        echo "[FACTURA] Aliado: {$f->aliado_id}, ID: {$f->id}, Cedula: {$f->cedula}, NumFac: {$f->numero_factura}, TotalSS: {$f->total_ss}, Retiro: {$f->retiro}, Mora: {$f->mora}, Pago: {$f->fecha_pago}\n";
    }
}

echo "\n--- BUSCANDO VALOR 704.193 EN GASTOS DE TODOS LOS ALIADOS ---\n";
$gastos = DB::table('gastos')
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->get();

foreach ($gastos as $g) {
    if (abs($g->valor - 704193) < 1000) {
        echo "[GASTO] Aliado: {$g->aliado_id}, ID: {$g->id}, Tipo: {$g->tipo}, Valor: {$g->valor}, Desc: {$g->descripcion}, Forma Pago: {$g->forma_pago}\n";
    }
}

// También busquemos si hay sumas o grupos que den 704.193
// Por ejemplo, costo_retiros total por aliado
$costoRetirosPorAliado = DB::table('facturas')
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where('numero_factura', 0)
    ->groupBy('aliado_id')
    ->select('aliado_id', DB::raw('SUM(total_ss) as total'))
    ->get();

echo "\n--- COSTO RETIROS TOTAL POR ALIADO (numero_factura = 0) ---\n";
foreach ($costoRetirosPorAliado as $cra) {
    echo "Aliado ID: {$cra->aliado_id}, Total Costo Retiros: " . number_format($cra->total, 0, ',', '.') . "\n";
}

// También sum_retiro total de facturas regulares por aliado
$ingresoRetiroPorAliado = DB::table('facturas')
    ->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->groupBy('aliado_id')
    ->select('aliado_id', DB::raw('SUM(retiro) as total'))
    ->get();

echo "\n--- INGRESO RETIRO CAMPO TOTAL POR ALIADO ---\n";
foreach ($ingresoRetiroPorAliado as $ira) {
    echo "Aliado ID: {$ira->aliado_id}, Total Ingreso Retiro: " . number_format($ira->total, 0, ',', '.') . "\n";
}
