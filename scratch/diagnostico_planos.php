<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$planoIds = [41041, 41042];
echo "=== AUDITORÍA PLANOS PILA ===\n";

foreach ($planoIds as $id) {
    $p = DB::table('planos')->where('id', $id)->first();
    if ($p) {
        echo "PLANO ID: {$p->id}\n";
        echo "   Contrato ID: {$p->contrato_id}\n";
        echo "   Factura ID: {$p->factura_id}\n";
        echo "   Tipo Reg: {$p->tipo_reg}\n";
        echo "   Fecha Retiro: {$p->fecha_ret}\n";
        echo "   Mes/Anio Plano: {$p->mes_plano}/{$p->anio_plano}\n";
        echo "   Plano #: {$p->n_plano}\n";
        echo "   Usuario ID: {$p->usuario_id}\n";
        echo "   Creado At: {$p->created_at}\n";
        
        $f = DB::table('facturas')->where('id', $p->factura_id)->first();
        if ($f) {
            echo "   FACTURA ID: {$f->id}\n";
            echo "      Numero Factura: {$f->numero_factura}\n";
            echo "      Tipo Factura: {$f->tipo}\n";
            echo "      Estado: {$f->estado}\n";
            echo "      Observacion: {$f->observacion}\n";
            echo "      Creado At: {$f->created_at}\n";
        } else {
            echo "   Factura no encontrada.\n";
        }
        echo "\n";
    } else {
        echo "Plano ID $id no encontrado.\n\n";
    }
}
