<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$valor = 704193;

echo "=== BUSQUEDA GLOBAL DEL VALOR 704.193 ===\n";

// 1. En facturas (en cualquier campo numérico)
$facturas = DB::table('facturas')->get();
foreach ($facturas as $f) {
    foreach (['total_ss', 'mora', 'retiro', 'admon', 'seguro', 'afiliacion', 'mensajeria', 'otros', 'iva'] as $col) {
        if (isset($f->$col) && abs((float)$f->$col - $valor) < 10) {
            echo "[FACTURA] ID: {$f->id}, Aliado: {$f->aliado_id}, Columna: $col, Valor: {$f->$col}, Mes/Anio: {$f->mes}/{$f->anio}, Estado: {$f->estado}, Pago: {$f->fecha_pago}\n";
        }
    }
}

// 2. En gastos (valor)
$gastos = DB::table('gastos')->get();
foreach ($gastos as $g) {
    if (abs((float)$g->valor - $valor) < 10) {
        echo "[GASTO] ID: {$g->id}, Aliado: {$g->aliado_id}, Tipo: {$g->tipo}, Valor: {$g->valor}, Desc: {$g->descripcion}, Fecha: {$g->fecha}\n";
    }
}

// 3. En consignaciones (valor)
$consignaciones = DB::table('consignaciones')->get();
foreach ($consignaciones as $c) {
    if (abs((float)$c->valor - $valor) < 10) {
        echo "[CONSIGNACION] ID: {$c->id}, Aliado: {$c->aliado_id}, Valor: {$c->valor}, Ref: {$c->referencia}, Obs: {$c->observacion}, Fecha: {$c->fecha}\n";
    }
}
echo "Búsqueda finalizada.\n";
