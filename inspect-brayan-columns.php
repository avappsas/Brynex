<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__.'/Brayan_Garcia_2026.xlsx';
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFileName);
$sheet = $spreadsheet->getSheet(0); // BRAYAN

$rowForGastos = null;
for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
    $concepto = trim($sheet->getCell("A{$row}")->getValue());
    if (strtoupper($concepto) === 'GASTOS') {
        $rowForGastos = $row;
        break;
    }
}

$highestCol = $sheet->getHighestColumn();
$highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

echo "Headers and Gastos from Col 70 to {$highestColIndex} ({$highestCol}):\n";
for ($c = 70; $c <= $highestColIndex; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    
    // Check cell values in rows 1, 2, or other header rows to see what month/year this column is
    $r1 = $sheet->getCell($colLetter . '1')->getValue();
    $r2 = $sheet->getCell($colLetter . '2')->getValue();
    
    // Find the year header if any. Let's print rows 1-5 for these columns.
    $headerStr = "";
    for ($r = 1; $r <= 5; $r++) {
        $v = $sheet->getCell($colLetter . $r)->getValue();
        if ($v !== null && $v !== '') {
            $headerStr .= "R{$r}:{$v} | ";
        }
    }
    
    $gVal = $sheet->getCell($colLetter . $rowForGastos)->getCalculatedValue();
    echo "Col {$colLetter} ({$c}): Header=[{$headerStr}] Gastos=" . ($gVal !== null ? number_format((float)$gVal, 2) : 'null') . "\n";
}
