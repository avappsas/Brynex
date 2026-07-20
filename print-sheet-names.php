<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__.'/Brayan_Garcia_2026.xlsx';
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFileName);

$sheetCount = $spreadsheet->getSheetCount();
echo "Total Sheets: {$sheetCount}\n";
for ($i = 0; $i < $sheetCount; $i++) {
    echo "Sheet {$i}: " . $spreadsheet->getSheet($i)->getTitle() . "\n";
}
