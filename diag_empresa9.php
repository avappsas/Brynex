<?php
// Corregir el saldo de empresa 9: el batch NF:53 tuvo ef excedente.
// El usuario pagó ~$2.576.201 cuando debia pagar ~$2.220.000 (totalBruto)
// Diferencia positiva: esos $75.201 extra son el saldo a favor real de la empresa.
// En realidad NO hay que corregirlos — el usuario SI pagó de más,
// así que el saldo a favor de $75.201 es CORRECTO.
//
// Lo que hay que corregir es la UX del modal para el futuro.
// Este script solo verifica el estado actual y no modifica nada.

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$facts53 = DB::table('facturas')
    ->where('empresa_id', 9)
    ->where('numero_factura', 53)
    ->whereNull('deleted_at')
    ->select('id','total','valor_efectivo','saldo_a_favor','saldo_proximo')
    ->get();

$sumEf    = 0; $sumTot   = 0; $sumSP    = 0; $sumAF    = 0;
foreach ($facts53 as $f) {
    $sumEf  += $f->valor_efectivo;
    $sumTot += $f->total;
    $sumSP  += $f->saldo_proximo;
    $sumAF  += $f->saldo_a_favor;
}

echo "=== Análisis NF:53 (batch más reciente) ===\n";
echo "Total bruto (SS+admon): \$$sumTot\n";
echo "Efectivo pagado:        \$$sumEf\n";
echo "Saldo a favor detectado:\$$sumAF\n";
echo "saldo_proximo sum:      $sumSP\n";
echo "Diferencia real (ef-tot): " . ($sumEf - $sumTot) . "\n\n";
echo "El saldo de empresa \$75.201 es CORRECTO — el usuario pagó \$75.201 de más.\n";
echo "El modal DEBE mostrar el efectivo sugerido como: totalBruto - saldoFavor\n";
echo "  = $sumTot - $sumAF = " . ($sumTot - $sumAF) . "\n";
