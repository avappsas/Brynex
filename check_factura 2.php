<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$f = App\Models\Factura::with('usuario')->find(2);
if (!$f) { echo "No encontrada\n"; exit; }

echo json_encode([
    'estado'          => $f->estado,
    'valor_prestamo'  => $f->valor_prestamo,
    'valor_efectivo'  => $f->valor_efectivo,
    'valor_consignado'=> $f->valor_consignado,
    'valor_banco2'    => $f->valor_banco2,
    'forma_pago'      => $f->forma_pago,
    'usuario_id'      => $f->usuario_id,
    'usuario_name'    => $f->usuario?->name,
    'total'           => $f->total,
    'observacion'     => $f->observacion,
    'columnas'        => array_keys($f->getAttributes()),
], JSON_PRETTY_PRINT);
