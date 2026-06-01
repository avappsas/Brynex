<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$mes = 5;
$anio = 2026;

echo "=== BUSQUEDA RÁPIDA EN MAYO 2026 ===\n";

// Listar todos los aliados
$aliados = DB::table('aliados')->select('id', 'razon_social')->get();
echo "Aliados en el sistema:\n";
foreach ($aliados as $a) {
    echo "- ID: {$a->id}, Nombre: {$a->razon_social}\n";
}

foreach ($aliados as $a) {
    $aid = $a->id;
    echo "\n----------------------------------------\n";
    echo "ALIADO ID: $aid ({$a->razon_social})\n";
    
    // Facturas informativas de retiro (numero_factura = 0)
    $costoRetiros = DB::table('facturas')
        ->where('aliado_id', $aid)->whereNull('deleted_at')
        ->whereNotNull('fecha_pago')
        ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
        ->where('numero_factura', 0)
        ->sum('total_ss');
        
    // Mora recogida
    $moraRecogida = DB::table('facturas')
        ->where('aliado_id', $aid)->whereNull('deleted_at')
        ->whereNotNull('fecha_pago')
        ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
        ->sum('mora');
        
    // Gastos tipo pago_planilla
    $pagadoSSRaw = DB::table('gastos')
        ->where('aliado_id', $aid)
        ->where('tipo', 'pago_planilla')
        ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
        ->sum('valor');
        
    // Todos los demás egresos operativos
    $gastosOp = DB::table('gastos')
        ->where('aliado_id', $aid)
        ->where('tipo', '!=', 'pago_planilla')
        ->where('tipo', '!=', 'efectivo_banco')
        ->where('forma_pago', '!=', 'banco_banco')
        ->whereMonth('fecha', $mes)->whereYear('fecha', $anio)
        ->sum('valor');

    // SS mes anterior cobrado
    $ssMesAnteriorParaActual = DB::table('facturas')
        ->where('aliado_id', $aid)->whereNull('deleted_at')
        ->whereIn('estado', ['pagada', 'abono', 'prestamo'])
        ->where('es_prestamo', 0)
        ->whereNotNull('fecha_pago')
        ->whereMonth('fecha_pago', $mes - 1 == 0 ? 12 : $mes - 1)
        ->whereYear('fecha_pago', $mes - 1 == 0 ? $anio - 1 : $anio)
        ->where('numero_factura', '>', 0)
        ->where('mes', $mes)->where('anio', $anio)
        ->sum('total_ss');

    // SS cobrada mes actual para mes actual
    $ssActuales = DB::table('facturas')
        ->where('aliado_id', $aid)->whereNull('deleted_at')
        ->whereNotNull('fecha_pago')
        ->whereMonth('fecha_pago', $mes)->whereYear('fecha_pago', $anio)
        ->where('numero_factura', '>', 0)
        ->where('tipo', 'planilla')
        ->where('anio', $anio)->where('mes', $mes)
        ->sum('total_ss');

    echo "Costo Retiros (SS Retiro Cobrado): " . number_format($costoRetiros) . "\n";
    echo "Mora Recogida: " . number_format($moraRecogida) . "\n";
    echo "Pagado SS Planillas (gastos): " . number_format($pagadoSSRaw) . "\n";
    echo "Gastos Operativos (otros): " . number_format($gastosOp) . "\n";
    echo "SS Mes Anterior arrastrado: " . number_format($ssMesAnteriorParaActual) . "\n";
    echo "SS Recaudado Mes Actual: " . number_format($ssActuales) . "\n";
}
