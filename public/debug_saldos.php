<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aid = 2; // ID del aliado (Brayan Garcia)

// 1. Ver ledgers de saldos_banco
$ledgers = DB::table('saldos_banco')
    ->where('aliado_id', $aid)
    ->get();

echo "=== LEDGERS IN SALDOS_BANCO ===\n";
foreach ($ledgers as $l) {
    echo "ID: {$l->id} | Banco ID: {$l->banco_cuenta_id} | Fecha: {$l->fecha} | Tipo: {$l->tipo} | Saldo Acum: {$l->saldo_acumulado}\n";
}

// 2. Ver sumatoria de consignaciones de mayo 2026 por banco en la tabla consignaciones
$consM = DB::table('consignaciones')
    ->where('aliado_id', $aid)
    ->where('fecha', '>=', '2026-05-01')
    ->where('fecha', '<=', '2026-05-31')
    ->groupBy('banco_cuenta_id')
    ->selectRaw('banco_cuenta_id, SUM(valor) as total, COUNT(*) as cant')
    ->get();

echo "\n=== CONSIGNACIONES MAYO 2026 (tabla consignaciones) ===\n";
foreach ($consM as $c) {
    echo "Banco ID: {$c->banco_cuenta_id} | Total: {$c->total} | Cantidad: {$c->cant}\n";
}

// 3. Ver sumatoria de consignaciones de mayo 2026 por banco usando static::where de Consignacion
$consMStatic = \App\Models\Consignacion::where('aliado_id', $aid)
    ->where('fecha', '>=', '2026-05-01')
    ->where('fecha', '<=', '2026-05-31')
    ->groupBy('banco_cuenta_id')
    ->selectRaw('banco_cuenta_id, SUM(valor) as total, COUNT(*) as cant')
    ->get();

echo "\n=== CONSIGNACIONES MAYO 2026 (modelo Consignacion) ===\n";
foreach ($consMStatic as $c) {
    echo "Banco ID: {$c->banco_cuenta_id} | Total: {$c->total} | Cantidad: {$c->cant}\n";
}

// 4. Ver qué bancos se consideran en saldosBancosOptimizados
$bancoIds = [137, 141, 142, 145, 199]; // IDs de bancos
$fechaFin = '2026-05-31';
$saldos = \App\Models\Consignacion::saldosBancosOptimizados($aid, $bancoIds, $fechaFin);

echo "\n=== SALDOS OPTIMIZADOS AL 31-MAY-2026 (Desde Modelo) ===\n";
print_r($saldos);



// Egresos (gastos) por banco en mayo 2026
$gastosM = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->where('fecha', '>=', '2026-05-01')
    ->where('fecha', '<=', '2026-05-31')
    ->groupBy('banco_origen_id')
    ->selectRaw('banco_origen_id, SUM(valor) as total, COUNT(*) as cant')
    ->get();

echo "\n=== GASTOS MAYO 2026 (tabla gastos) ===\n";
foreach ($gastosM as $g) {
    echo "Banco ID: {$g->banco_origen_id} | Total: {$g->total} | Cantidad: {$g->cant}\n";
}

// Detalle del banco 137 (Bancolombia Brayan)
echo "\n=== DETALLE BANCO 137 (LEDGER E IMPLICACIONES) ===\n";
$ledger137 = DB::table('saldos_banco')
    ->where('aliado_id', $aid)
    ->where('banco_cuenta_id', 137)
    ->first();
if ($ledger137) {
    echo "Ledger 137 -> Fecha: {$ledger137->fecha}, Tipo: {$ledger137->tipo}, Saldo: {$ledger137->saldo_acumulado}\n";
}

// Suma de entradas en 137 después de la fecha del ledger
$ent137 = DB::table('consignaciones')
    ->where('aliado_id', $aid)
    ->where('banco_cuenta_id', 137)
    ->where('fecha', '>=', '2026-05-01')
    ->where('fecha', '>', $ledger137 ? $ledger137->fecha : '2026-05-01')
    ->sum('valor');
$sal137 = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->where('banco_origen_id', 137)
    ->where('fecha', '>=', '2026-05-01')
    ->where('fecha', '>', $ledger137 ? $ledger137->fecha : '2026-05-01')
    ->sum('valor');
echo "Entradas después ledger: {$ent137} | Salidas después ledger: {$sal137}\n";

// Miremos qué consignaciones y gastos hay para el banco 137 en mayo en total
$totEnt137 = DB::table('consignaciones')->where('aliado_id', $aid)->where('banco_cuenta_id', 137)->whereBetween('fecha', ['2026-05-01', '2026-05-31'])->sum('valor');
$totSal137 = DB::table('gastos')->where('aliado_id', $aid)->where('banco_origen_id', 137)->whereBetween('fecha', ['2026-05-01', '2026-05-31'])->sum('valor');
echo "Total Entradas Mayo 137: {$totEnt137} | Total Salidas Mayo 137: {$totSal137}\n";

// Listar los gastos de mayo para banco 137 y 145 agrupados por tipo
$gastosDetalle137 = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->where('banco_origen_id', 137)
    ->whereBetween('fecha', ['2026-05-01', '2026-05-31'])
    ->selectRaw('tipo, SUM(valor) as total, COUNT(*) as cant')
    ->groupBy('tipo')
    ->get();

echo "\n=== GASTOS BANCO 137 POR TIPO ===\n";
foreach ($gastosDetalle137 as $g) {
    echo "Tipo: {$g->tipo} | Total: {$g->total} | Cantidad: {$g->cant}\n";
}

$gastosDetalle145 = DB::table('gastos')
    ->where('aliado_id', $aid)
    ->where('banco_origen_id', 145)
    ->whereBetween('fecha', ['2026-05-01', '2026-05-31'])
    ->selectRaw('tipo, SUM(valor) as total, COUNT(*) as cant')
    ->groupBy('tipo')
    ->get();

echo "\n=== GASTOS BANCO 145 POR TIPO ===\n";
foreach ($gastosDetalle145 as $g) {
    echo "Tipo: {$g->tipo} | Total: {$g->total} | Cantidad: {$g->cant}\n";
}


