<?php
require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class BrynexFpdi extends Fpdi {}

$pdf = new BrynexFpdi('L', 'pt');
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 9);

$x = 100;
$y = 100;
$w = 100;
$h = 15;
$fontSize = 9;
$valor = "CC 1058846712";

$pdf->Rect($x, $y, $w, $h, 'D');

$cellH = $fontSize + 1;
$textY = $y + $h - $cellH;

$pdf->SetXY($x, $textY);
$pdf->Cell($w, $cellH, $valor, 1, 0, 'L');
$pdf->Output('F', 'test_fpdf.pdf');
echo "test_fpdf.pdf generado\n";
