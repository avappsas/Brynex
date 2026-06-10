<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Models\Factura;

$contrato = Contrato::find(10901);
if (!$contrato) {
    echo "Contrato no encontrado\n";
    exit;
}

echo "Contrato ID: 10901\n";
echo "Fecha Ingreso: " . $contrato->fecha_ingreso . "\n";
echo "Estado: " . $contrato->estado . "\n";

$facturas = Factura::where('contrato_id', 10901)->get();
echo "Facturas del contrato:\n";
foreach ($facturas as $f) {
    echo "Factura ID: {$f->id}, Numero: {$f->numero_factura}, Tipo: {$f->tipo}, Mes: {$f->mes}, Anio: {$f->anio}, Estado: {$f->estado}, Total SS: {$f->total_ss}, Mora: {$f->mora}, Total: {$f->total}\n";
}
