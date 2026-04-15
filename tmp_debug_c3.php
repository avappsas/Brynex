<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = App\Models\Contrato::find(3);
echo "administracion: ".$c->administracion."\n";
echo "admon_asesor: ".$c->admon_asesor."\n";
echo "seguro: ".$c->seguro."\n";
$f = App\Models\Factura::where('contrato_id',3)->orderByDesc('id')->first();
echo "ultima factura id:".$f?->id." mes:".$f?->mes." anio:".$f?->anio."\n";
echo "saldo_proximo:".($f?->saldo_proximo ?? 'N/A')."\n";
echo "pendiente_siguiente:".($f?->pendiente_siguiente ?? 'N/A')."\n";
echo "valor_total:".($f?->valor_total ?? 'N/A')."\n";
echo "estado:".($f?->estado ?? 'N/A')."\n";
