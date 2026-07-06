<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../Brayan_Garcia_2026.xlsx';
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getSheetByName('BRAYAN');
$data = $sheet->toArray(null, false, false, true);

// Let's print row index 0 to 5 of column C to AN
for ($r = 0; $r <= 20; $r++) {
    if (!isset($data[$r])) continue;
    $row = $data[$r];
    $concept = $row['A'] ?? '';
    // check if it is interesting
    if ($r < 5 || stripos($concept, 'bry') !== false || stripos($concept, 'programa') !== false) {
        echo "Row $r [$concept]:\n";
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN'] as $col) {
            $val = $row[$col] ?? '';
            if ($val !== '') {
                echo "  $col: $val\n";
            }
        }
    }
}
