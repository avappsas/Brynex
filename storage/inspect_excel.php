<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Brayan_Garcia_2026.xlsx';
if (!file_exists($file)) {
    die("File not found\n");
}

$spreadsheet = IOFactory::load($file);
$sheetNames = $spreadsheet->getSheetNames();

foreach ($sheetNames as $name) {
    echo "\n=== Sheet: $name ===\n";
    $sheet = $spreadsheet->getSheetByName($name);
    $rows = $sheet->toArray();
    $found = false;
    foreach ($rows as $i => $row) {
        $rowValues = array_map(function($v) { return $v !== null ? trim($v) : ''; }, $row);
        $rowStr = implode(' | ', array_filter($rowValues));
        if (stripos($rowStr, 'arroyave') !== false || stripos($rowStr, 'programa') !== false || stripos($rowStr, 'lider') !== false) {
            echo "Row $i: $rowStr\n";
            $found = true;
        }
    }
    if (!$found) {
        // Just print first 5 rows to understand the structure
        echo "First 5 rows:\n";
        for ($i = 0; $i < min(5, count($rows)); $i++) {
            $rowValues = array_map(function($v) { return $v !== null ? trim($v) : ''; }, $rows[$i]);
            echo "Row $i: " . implode(' | ', array_filter($rowValues)) . "\n";
        }
    }
}
