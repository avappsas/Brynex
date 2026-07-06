<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Brayan_Garcia_2026.xlsx';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getSheetByName('BRAYAN');
$data = $sheet->toArray(null, false, false, true);

for ($r = 1; $r <= 5; $r++) {
    echo "Row $r:\n";
    foreach ($data[$r] as $col => $val) {
        if ($val !== null && $val !== '') {
            echo "  $col: $val\n";
        }
    }
}
