<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__.'/Brayan_Garcia_2026.xlsx';
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFileName);

$brayanSheet = $spreadsheet->getSheet(0); // BRAYAN
$gastosSheet = $spreadsheet->getSheet(1); // GASTOS

// Find the GASTOS row in BRAYAN sheet
$rowForGastos = null;
for ($row = 1; $row <= $brayanSheet->getHighestRow(); $row++) {
    $concepto = trim($brayanSheet->getCell("A{$row}")->getValue());
    if (strtoupper($concepto) === 'GASTOS') {
        $rowForGastos = $row;
        break;
    }
}

if (!$rowForGastos) {
    echo "Row GASTOS not found in BRAYAN sheet\n";
    exit;
}

// Map columns of BRAYAN sheet to years
$columnsByYear = [
    2020 => range(3, 14),   // C to N
    2021 => range(15, 26),  // O to Z
    2022 => range(27, 38),  // AA to AL
    2023 => range(39, 50),  // AM to AX
    2024 => range(51, 62),  // AY to BJ
    2025 => range(63, 74),  // BK to BV
    2026 => range(75, 86),  // BW to CH (Wait, 2026 column range: BW to CB or CH? Let's check how many columns are in 2026!)
];

// Let's print out the column headers of BRAYAN sheet to verify the years!
echo "BRAYAN Column headers details:\n";
$highestCol = $brayanSheet->getHighestColumn();
$highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

$yearMap = [];
for ($c = 3; $c <= $highestColIndex; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    // Let's look up to 4 rows above or check how years are specified. Usually there's a year row or headers.
    // Let's inspect the headers or let's read the formula of the cells or values.
    // Let's assume a mapping based on columns.
}

// Let's do a precise grouping by looking at the formula in row 55 (or whatever row is GASTOS)
// Let's check what BRAYAN sheet row 55 has.
$brayanGastosByYear = [];
// Let's define the exact years based on standard month columns starting from C (Jan 2020)
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$currentYear = 2020;
$monthIndex = 0;
for ($c = 3; $c <= $highestColIndex; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $yearMap[$c] = $currentYear;
    
    $gVal = $brayanSheet->getCell($colLetter . $rowForGastos)->getCalculatedValue();
    if (!isset($brayanGastosByYear[$currentYear])) {
        $brayanGastosByYear[$currentYear] = 0.00;
    }
    $brayanGastosByYear[$currentYear] += (float)$gVal;
    
    $monthIndex++;
    if ($monthIndex == 12) {
        $monthIndex = 0;
        $currentYear++;
    }
}

echo "BRAYAN Sheet Gastos by Year (calculated from columns):\n";
foreach ($brayanGastosByYear as $yr => $sum) {
    echo "  Year {$yr}: " . number_format($sum, 2) . "\n";
}

// GASTOS sheet Column F Salidas by Year
$gastosSalidasByYear = [];
$highestRowGastos = $gastosSheet->getHighestRow();
for ($row = 2; $row < 5320; $row++) {
    $yearVal = $gastosSheet->getCell("B{$row}")->getCalculatedValue();
    $year = $yearVal ? (int)$yearVal : 0;
    
    $f = (float) $gastosSheet->getCell("F{$row}")->getCalculatedValue();
    
    if (!isset($gastosSalidasByYear[$year])) {
        $gastosSalidasByYear[$year] = 0.00;
    }
    $gastosSalidasByYear[$year] += $f;
}

echo "\nGASTOS Sheet Column F (Salidas) by Year:\n";
ksort($gastosSalidasByYear);
foreach ($gastosSalidasByYear as $yr => $sum) {
    echo "  Year {$yr}: " . number_format($sum, 2) . "\n";
}

echo "\n=== YEARLY DISCREPANCIES (BRAYAN Gastos - GASTOS Salidas) ===\n";
foreach ($brayanGastosByYear as $yr => $bSum) {
    $gSum = $gastosSalidasByYear[$yr] ?? 0.00;
    $diff = $bSum - $gSum;
    echo "  Year {$yr}: BRAYAN=" . number_format($bSum, 2) . " | GASTOS=" . number_format($gSum, 2) . " | Diff=" . number_format($diff, 2) . "\n";
}
