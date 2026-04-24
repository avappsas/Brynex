<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

$cols = ['banco_cuenta_id','banco_cuenta2_id','valor_banco2','valor_prestamo','dist_admon','dist_asesor','dist_retiro','dist_utilidad'];
foreach ($cols as $col) {
    $exists = Schema::hasColumn('facturas', $col);
    echo ($exists ? '✓' : '✗') . " $col\n";
}
