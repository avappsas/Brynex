<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aid = 2; // BRYGAR
$mes = 5;
$anio = 2026;

echo "=== SUMARIOS DE FACTURAS PARA BRYGAR (MAYO 2026) ===\n";

// Suma de total_ss de facturas informativas de retiro (numero_factura = 0)
$ss_retiros_fact_0 = DB::table('facturas')
    ->where('aliado_id', $aid)->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where('numero_factura', 0)
    ->sum('total_ss');
echo "1. Suma total_ss en facturas de retiro (numero_factura = 0): " . number_format($ss_retiros_fact_0) . "\n";

// Suma del campo 'retiro' en facturas regulares (numero_factura > 0)
$sum_retiro_campo = DB::table('facturas')
    ->where('aliado_id', $aid)->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where('numero_factura', '>', 0)
    ->sum('retiro');
echo "2. Suma del campo 'retiro' en facturas regulares: " . number_format($sum_retiro_campo) . "\n";

// Suma del campo 'dist_retiro' en facturas de afiliación
$sum_dist_retiro = DB::table('facturas')
    ->where('aliado_id', $aid)->whereNull('deleted_at')
    ->whereNotNull('fecha_pago')
    ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
    ->where('tipo', 'afiliacion')
    ->sum('dist_retiro');
echo "3. Suma del campo 'dist_retiro' en afiliaciones: " . number_format($sum_dist_retiro) . "\n";

// Suma de total_ss de retiros cobrados (ss_retiro_facturas) en planillas de egreso
$planillasMes = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->where('tipo', 'pago_planilla')
    ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
    ->pluck('numero_planilla')
    ->filter()
    ->unique()
    ->toArray();

if (!empty($planillasMes)) {
    $ss_retiro_pagado_operador = DB::table('planos as p2')
        ->join('facturas as f', 'f.id', '=', 'p2.factura_id')
        ->where('p2.aliado_id', $aid)
        ->whereIn('p2.numero_planilla', $planillasMes)
        ->whereNull('p2.deleted_at')
        ->whereNull('f.deleted_at')
        ->where('f.numero_factura', 0)
        ->sum('f.total_ss');
    echo "4. Suma total_ss retiros pagados al operador en planillas del mes: " . number_format($ss_retiro_pagado_operador) . "\n";
} else {
    echo "4. Suma total_ss retiros pagados al operador: 0 (no hay planillas)\n";
}
