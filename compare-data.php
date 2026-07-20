<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;

$inputFileName = __DIR__.'/Brayan_Garcia_2026.xlsx';
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFileName);
$sheet = $spreadsheet->getSheet(1); // Movimientos

$highestRow = $sheet->getHighestRow();

$excelRows = [];
$excelTotalE = 0;
$excelTotalF = 0;
$excelTotalG = 0;
$excelTotalH = 0;

$minDate = null;
$maxDate = null;

for ($row = 2; $row < 5320; $row++) { // Exclude headers and total row 5320
    $fechaVal = $sheet->getCell("D{$row}")->getValue();
    $fechaStr = null;
    if ($fechaVal !== null && $fechaVal !== '') {
        if (is_numeric($fechaVal)) {
            $fechaStr = date('Y-m-d', Date::excelToTimestamp($fechaVal));
        } else {
            $fechaStr = $fechaVal;
        }
    }
    
    if ($fechaStr) {
        if ($minDate === null || $fechaStr < $minDate) $minDate = $fechaStr;
        if ($maxDate === null || $fechaStr > $maxDate) $maxDate = $fechaStr;
    }
    
    $e = (float) $sheet->getCell("E{$row}")->getCalculatedValue();
    $f = (float) $sheet->getCell("F{$row}")->getCalculatedValue();
    $g = (float) $sheet->getCell("G{$row}")->getCalculatedValue();
    
    $hCell = $sheet->getCell("H{$row}");
    $hVal = $hCell->getValue();
    $hCalc = $hCell->getCalculatedValue();
    $h = 0.00;
    if (is_numeric($hCalc)) {
        $h = (float) $hCalc;
    }
    
    $excelTotalE += $e;
    $excelTotalF += $f;
    $excelTotalG += $g;
    $excelTotalH += $h;
    
    $excelRows[] = [
        'row' => $row,
        'fecha' => $fechaStr,
        'e' => $e,
        'f' => $f,
        'g' => $g,
        'h' => $h,
    ];
}

echo "=== EXCEL STATS ===\n";
echo "Total rows analyzed: " . count($excelRows) . "\n";
echo "Date range in Excel: {$minDate} to {$maxDate}\n";
echo "Sum Column E (ENTRADAS): " . number_format($excelTotalE, 2) . "\n";
echo "Sum Column F (SALIDA):   " . number_format($excelTotalF, 2) . "\n";
echo "Sum Column G (PRESTAMOS): " . number_format($excelTotalG, 2) . "\n";
echo "Sum Column H (INVERSION): " . number_format($excelTotalH, 2) . "\n\n";

// DB stats for the same date range or all historical
$userId = 2;

// 1. Gastos from DB
$dbGastosTotal = (float) DB::connection('finanzas')->table('finanzas_gastos')
    ->where('user_id', $userId)
    ->where('tipo_movimiento', 'gasto')
    ->sum('monto');
$dbPrestamosTotal = (float) DB::connection('finanzas')->table('finanzas_gastos')
    ->where('user_id', $userId)
    ->where('tipo_movimiento', 'prestamo')
    ->sum('monto');
$dbInversionesTotal = (float) DB::connection('finanzas')->table('finanzas_gastos')
    ->where('user_id', $userId)
    ->where('tipo_movimiento', 'inversion')
    ->sum('monto');

echo "=== DATABASE STATS (user_id = 2) ===\n";
echo "Sum Gastos (tipo_movimiento = 'gasto'):     " . number_format($dbGastosTotal, 2) . "\n";
echo "Sum Préstamos (tipo_movimiento = 'prestamo'): " . number_format($dbPrestamosTotal, 2) . "\n";
echo "Sum Inversiones (tipo_movimiento = 'inversion'): " . number_format($dbInversionesTotal, 2) . "\n\n";

// Wait, let's look at the sheet 1 (index 0) "Cuentas" and see what it contains!
$sheet0 = $spreadsheet->getSheet(0);
echo "=== HOJA 1: Cuentas ===\n";
$highestRow0 = $sheet0->getHighestRow();
for ($r = 1; $r <= $highestRow0; $r++) {
    $rowVal = [];
    for ($c = 'A'; $c <= 'G'; $c++) {
        $rowVal[$c] = $sheet0->getCell($c . $r)->getCalculatedValue();
    }
    // check empty
    $isEmpty = true;
    foreach ($rowVal as $v) {
        if ($v !== null && $v !== '') {
            $isEmpty = false;
            break;
        }
    }
    if (!$isEmpty) {
        echo "Row {$r}: " . json_encode($rowVal, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
