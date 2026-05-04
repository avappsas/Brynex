<?php

namespace App\Services;

use App\Models\Contrato;
use setasign\Fpdi\Fpdi;

class FormularioEpsService
{
    public function generar(Contrato $contrato, bool $incluirBeneficiarios = false): string
    {
        $eps = $contrato->eps;
        if (!$eps || !$eps->formulario_pdf) abort(404, 'Sin formulario configurado para esta EPS.');

        $ruta = storage_path('app/formularios/eps/' . $eps->formulario_pdf);
        if (!file_exists($ruta)) abort(404, 'PDF no encontrado en el servidor.');

        $campos = $eps->formulario_campos ?? [];
        if (empty($campos)) return file_get_contents($ruta);

        $datos = $this->ensamblarDatos($contrato, $incluirBeneficiarios);
        return $this->rellenarPdf($ruta, $campos, $datos);
    }

    protected function ensamblarDatos(Contrato $contrato, bool $incluirBeneficiarios): array
    {
        $c  = $contrato->cliente;
        $rs = $contrato->razonSocial;

        // Género separado para los cuadros M / F
        $genero  = strtoupper(trim($c?->genero ?? ''));
        $esM     = in_array($genero, ['M', 'MASCULINO', 'HOMBRE']);
        $esF     = in_array($genero, ['F', 'FEMENINO', 'MUJER']);

        $datos = [
            // ── Cotizante ──────────────────────────────────────────
            'cliente.primer_apellido'  => strtoupper($c?->primer_apellido  ?? ''),
            'cliente.segundo_apellido' => strtoupper($c?->segundo_apellido ?? ''),
            'cliente.primer_nombre'    => strtoupper($c?->primer_nombre    ?? ''),
            'cliente.segundo_nombre'   => strtoupper($c?->segundo_nombre   ?? ''),
            'cliente.nombres'          => strtoupper(trim(($c?->primer_nombre ?? '') . ' ' . ($c?->segundo_nombre ?? ''))),
            'cliente.apellidos'        => strtoupper(trim(($c?->primer_apellido ?? '') . ' ' . ($c?->segundo_apellido ?? ''))),
            'cliente.nombre_completo'  => strtoupper($c?->nombre_completo  ?? ''),
            'cliente.tipo_doc'         => $c?->tipo_doc ?? '',
            'cliente.cedula'           => $c?->cedula   ?? '',
            'cliente.genero'           => $genero,
            'cliente.genero_m'         => $esM ? 'X' : '',  // Cuadro Masculino
            'cliente.genero_f'         => $esF ? 'X' : '',  // Cuadro Femenino
            'cliente.fecha_nacimiento'         => $c?->fecha_nacimiento?->format('d/m/Y') ?? '',
            'cliente.fecha_nacimiento_d'        => $c?->fecha_nacimiento?->format('d')     ?? '',
            'cliente.fecha_nacimiento_m'        => $c?->fecha_nacimiento?->format('m')     ?? '',
            'cliente.fecha_nacimiento_a'        => $c?->fecha_nacimiento?->format('Y')     ?? '',
            'cliente.fecha_nacimiento_d_esp'    => $this->digs($c?->fecha_nacimiento?->format('d')),
            'cliente.fecha_nacimiento_m_esp'    => $this->digs($c?->fecha_nacimiento?->format('m')),
            'cliente.fecha_nacimiento_a_esp'    => $this->digs($c?->fecha_nacimiento?->format('Y')),
            // Dígitos individuales — nacimiento
            'cliente.fecha_nacimiento_d1'       => $this->dig($c?->fecha_nacimiento?->format('d'), 0),
            'cliente.fecha_nacimiento_d2'       => $this->dig($c?->fecha_nacimiento?->format('d'), 1),
            'cliente.fecha_nacimiento_m1'       => $this->dig($c?->fecha_nacimiento?->format('m'), 0),
            'cliente.fecha_nacimiento_m2'       => $this->dig($c?->fecha_nacimiento?->format('m'), 1),
            'cliente.fecha_nacimiento_a1'       => $this->dig($c?->fecha_nacimiento?->format('Y'), 0),
            'cliente.fecha_nacimiento_a2'       => $this->dig($c?->fecha_nacimiento?->format('Y'), 1),
            'cliente.fecha_nacimiento_a3'       => $this->dig($c?->fecha_nacimiento?->format('Y'), 2),
            'cliente.fecha_nacimiento_a4'       => $this->dig($c?->fecha_nacimiento?->format('Y'), 3),
            'cliente.rh'               => $c?->rh       ?? '',
            'cliente.telefono'         => $c?->telefono  ?? '',
            'cliente.celular'          => $c?->celular   ?? '',
            'cliente.correo'           => $c?->correo    ?? '',
            'cliente.direccion'        => strtoupper($c?->direccion_vivienda ?? ''),
            'cliente.barrio'           => strtoupper($c?->barrio ?? ''),
            'cliente.municipio'        => strtoupper($c?->municipio?->nombre ?? ''),
            'cliente.departamento'     => strtoupper($c?->departamento?->nombre ?? ''),
            'cliente.sisben'           => $c?->sisben    ?? '',
            'cliente.ips'              => $c?->ips       ?? '',
            'cliente.ocupacion'        => strtoupper($c?->ocupacion ?? ''),
            // Estáticos
            'static.COLOMBIANA'        => 'COLOMBIANA',
            // ── ARL y Pensión ──────────────────────────────────────
            'arl.nombre'               => strtoupper($contrato->arl?->nombre_arl ?? $contrato->arl?->razon_social ?? ''),
            'pension.nombre'           => strtoupper($contrato->pension?->razon_social ?? ''),
            // ── Contrato ───────────────────────────────────────────
            'contrato.fecha_ingreso'           => $contrato->fecha_ingreso?->format('d/m/Y') ?? '',
            'contrato.fecha_ingreso_d'         => $contrato->fecha_ingreso?->format('d')     ?? '',
            'contrato.fecha_ingreso_m'         => $contrato->fecha_ingreso?->format('m')     ?? '',
            'contrato.fecha_ingreso_a'         => $contrato->fecha_ingreso?->format('Y')     ?? '',
            'contrato.fecha_ingreso_d_esp'     => $this->digs($contrato->fecha_ingreso?->format('d')),
            'contrato.fecha_ingreso_m_esp'     => $this->digs($contrato->fecha_ingreso?->format('m')),
            'contrato.fecha_ingreso_a_esp'     => $this->digs($contrato->fecha_ingreso?->format('Y')),
            // Dígitos individuales — ingreso
            'contrato.fecha_ingreso_d1'        => $this->dig($contrato->fecha_ingreso?->format('d'), 0),
            'contrato.fecha_ingreso_d2'        => $this->dig($contrato->fecha_ingreso?->format('d'), 1),
            'contrato.fecha_ingreso_m1'        => $this->dig($contrato->fecha_ingreso?->format('m'), 0),
            'contrato.fecha_ingreso_m2'        => $this->dig($contrato->fecha_ingreso?->format('m'), 1),
            'contrato.fecha_ingreso_a1'        => $this->dig($contrato->fecha_ingreso?->format('Y'), 0),
            'contrato.fecha_ingreso_a2'        => $this->dig($contrato->fecha_ingreso?->format('Y'), 1),
            'contrato.fecha_ingreso_a3'        => $this->dig($contrato->fecha_ingreso?->format('Y'), 2),
            'contrato.fecha_ingreso_a4'        => $this->dig($contrato->fecha_ingreso?->format('Y'), 3),
            'contrato.salario'         => $contrato->salario
                ? number_format((float)$contrato->salario, 0, ',', '.') : '',
            'contrato.ibc'             => $contrato->ibc
                ? number_format((float)$contrato->ibc, 0, ',', '.') : '',
            'contrato.cargo'           => strtoupper($contrato->cargo ?? ''),
            'contrato.tipo_cotizante'  => ($contrato->tipoModalidad?->modalidad === 'dependiente')
                ? 'Dependiente' : 'Independiente',
            // ── Empresa / Razón Social ──────────────────────────────
            // Columnas reales en razones_sociales: direccion, telefonos, correos
            'empresa.nit'              => $rs?->id            ?? '',
            'empresa.dv'               => $rs?->dv            ?? '',
            'empresa.nit_dv'           => ($rs?->id ?? '') . ($rs?->dv ? '-' . $rs->dv : ''),
            'empresa.tipo_doc'         => 'NIT',
            'empresa.razon_social'     => strtoupper($rs?->razon_social ?? ''),
            'empresa.direccion'        => strtoupper($rs?->direccion   ?? ''),
            'empresa.telefono'         => $rs?->telefonos ?? '',
            'empresa.correo'           => $rs?->correos   ?? '',
            // Departamento y municipio de la empresa: QUEMADOS (Valle del Cauca / Cali)
            'empresa.departamento'     => 'VALLE DEL CAUCA',
            'empresa.municipio'        => 'CALI',
            // Sello / firma de la razón social (PNG por ID)
            'empresa.sello'            => $rs?->id
                ? storage_path('app/sellos/' . $rs->id . '.png')
                : '',
            // Firma del cliente (PNG guardado en firma modal — clave: cedula)
            'cliente.firma'            => $c?->cedula
                ? storage_path('app/firmas/' . $c->cedula . '.png')
                : '',
        ];

        // ── Beneficiarios ───────────────────────────────────────────
        if ($incluirBeneficiarios && $c) {
            foreach ($c->beneficiarios()->get() as $i => $b) {
                $n = $i + 1;
                $datos["ben{$n}.nombres"]          = strtoupper($b->nombres       ?? '');
                $datos["ben{$n}.tipo_doc"]         = $b->tipo_doc      ?? '';
                $datos["ben{$n}.documento"]        = $b->n_documento   ?? '';
                $datos["ben{$n}.parentesco"]       = strtoupper($b->parentesco    ?? '');
                $datos["ben{$n}.fecha_nacimiento"] = $b->fecha_nacimiento?->format('d/m/Y') ?? '';
                $datos["ben{$n}.fecha_expedicion"] = $b->fecha_expedicion?->format('d/m/Y') ?? '';
            }
        }

        return $datos;
    }

    /** Separa cada dígito de un string con un espacio: '2026' -> '2 0 2 6' */
    protected function digs(?string $valor): string
    {
        if (!$valor) return '';
        return implode(' ', str_split($valor));
    }

    /** Extrae un dígito individual en la posición $pos (0-based): dig('05', 0) → '0' */
    protected function dig(?string $valor, int $pos): string
    {
        if (!$valor) return '';
        return $valor[$pos] ?? '';
    }

    protected function rellenarPdf(string $rutaPdf, array $campos, array $datos): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);

        $totalPaginas = $pdf->setSourceFile($rutaPdf);
        $tpls = [];
        for ($p = 1; $p <= $totalPaginas; $p++) {
            $tpls[$p] = $pdf->importPage($p);
        }

        for ($p = 1; $p <= $totalPaginas; $p++) {
            $size = $pdf->getTemplateSize($tpls[$p]);
            $ori  = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($ori, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpls[$p]);

            foreach ($campos as $campo) {
                if ((int)($campo['pagina'] ?? 1) !== $p) continue;

                // Marcas X estáticas (static.X_1, static.X_2, …) → siempre 'X'
                if (str_starts_with($campo['dato'] ?? '', 'static.X_')) {
                    $valor = 'X';
                // Firmas adicionales (cliente.firma_2, …) → misma imagen que cliente.firma
                } elseif (str_starts_with($campo['dato'] ?? '', 'cliente.firma_')) {
                    $valor = $datos['cliente.firma'] ?? '';
                } else {
                    $valor = $datos[$campo['dato'] ?? ''] ?? ($campo['default'] ?? '');
                }
                if ($valor === '') continue;

                $fontSize = (float)($campo['font_size'] ?? 8);
                $style    = $campo['style']  ?? '';
                // Alineación por defecto: centrado (C) para cuadros, izquierda para texto libre
                $w        = (float)($campo['width']  ?? 0);
                $h        = (float)($campo['height'] ?? $fontSize + 2);
                $align    = $campo['align']  ?? ($w > 0 ? 'C' : 'L');
                $x        = (float)($campo['x'] ?? 0);
                $y        = (float)($campo['y'] ?? 0);

                $pdf->SetFont('Helvetica', $style, $fontSize);
                $pdf->SetTextColor(
                    (int)($campo['color_r'] ?? 0),
                    (int)($campo['color_g'] ?? 0),
                    (int)($campo['color_b'] ?? 0)
                );
                // ── Campo imagen (sello/firma) ───────────────────────
                if (($campo['tipo'] ?? '') === 'imagen'
                    || $campo['dato'] === 'empresa.sello'
                    || $campo['dato'] === 'cliente.firma'
                    || str_starts_with($campo['dato'] ?? '', 'cliente.firma_')) {
                    if ($valor && file_exists($valor) && $w > 0 && $h > 0) {
                        // Contener la imagen proporcionalmente (no estirar)
                        [$imgW, $imgH] = @getimagesize($valor) ?: [0, 0];
                        if ($imgW > 0 && $imgH > 0) {
                            $scale  = min($w / $imgW, $h / $imgH);
                            $newW   = $imgW * $scale;
                            $newH   = $imgH * $scale;
                            // Centrar dentro del cuadro mapeado
                            $drawX  = $x + ($w - $newW) / 2;
                            $drawY  = $y + ($h - $newH) / 2;
                            $pdf->Image($valor, $drawX, $drawY, $newW, $newH, 'PNG');
                        }
                    }
                    continue;
                }

                // ── Campo texto ────────────────────────────────────────
                // El usuario dibuja el rect con el borde INFERIOR pegado a la línea del formulario.
                // SetXY pone el cursor en la esquina SUPERIOR del cell, y la fuente ocupa
                // ~fontSize pt desde esa Y. Para que el texto quede en la línea visible
                // ajustamos Y al borde inferior menos la altura de la fuente + margen mínimo.
                $cellH  = $fontSize + 1;               // celda justa alrededor del texto
                $textY  = $y + $h - $cellH;            // anclar al fondo del rect

                $pdf->SetXY($x, $textY);

                if ($w > 0) {
                    $pdf->Cell($w, $cellH, $valor, 0, 0, $align);
                } else {
                    $pdf->Write($cellH, $valor);
                }
            }
        }

        return $pdf->Output('S');
    }
}
