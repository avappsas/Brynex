<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Brayan_Garcia_2026.xlsx';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getSheetByName('BRAYAN');
$data = $sheet->toArray(null, false, false, true);

// Print headers (Row 1 and 2)
echo "Headers Row 1:\n";
print_r(array_slice($data[1] ?? [], 0, 40));
echo "Headers Row 2:\n";
print_r(array_slice($data[2] ?? [], 0, 40));

// Find and print row for "Programa Org" or "Otras App" or "App Lideres"
foreach ($data as $rowIndex => $row) {
    $rowStr = implode(' | ', array_filter($row));
    if (stripos($rowStr, 'programa') !== false || stripos($rowStr, 'lider') !== false) {
        echo "Row $rowIndex:\n";
        print_r(array_slice($row, 0, 40));
    }
}
