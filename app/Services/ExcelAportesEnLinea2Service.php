<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ExcelAportesEnLinea2Service
 *
 * Segunda variante del Excel de Aportes en Línea: los MISMOS datos que
 * ExcelAportesEnLineaService, pero escritos sobre la plantilla que el propio
 * portal entrega al "Exportar plano" (resources/plantillas).
 *
 * Qué aporta la plantilla y no se puede reproducir a mano:
 *   - La hoja `DatosPruebaEmp` (oculta) con los catálogos del operador.
 *   - Las 54 listas de validación de `Liquidaciones`, que apuntan a esa hoja.
 *   - El logo y los anchos/estilos originales del portal.
 *
 * Por eso aquí NO se dibuja nada: se rellena. Los encabezados de las filas
 * 7-18 ya vienen en la plantilla; este servicio solo escribe el bloque del
 * aportante (K1:K3), los datos de la liquidación (fila 10) y los cotizantes
 * desde la fila 19, replicando estilos y validaciones hasta la última fila.
 */
class ExcelAportesEnLinea2Service extends ExcelAportesEnLineaService
{
    /** Primera fila de cotizantes en la plantilla. */
    private const FILA_INICIAL = 19;

    /** Última fila de datos que trae la plantilla (sus validaciones llegan hasta acá). */
    private const FILA_FINAL_PLANTILLA = 23;

    /** Columnas de la hoja Liquidaciones (A→CT). */
    private const TOTAL_COLUMNAS = 98;

    public function generar(array $params): Spreadsheet
    {
        [$rs, $planos, $periodoSS, $periodoSalud, $tipoPlanilla, $nombreArl] = $this->recolectar($params);

        $ruta = resource_path('plantillas/aportes_en_linea_2.xlsx');
        if (! is_file($ruta)) {
            throw new \RuntimeException('No se encontró la plantilla aportes_en_linea_2.xlsx.');
        }

        $spreadsheet = IOFactory::createReader('Xlsx')->load($ruta);
        app(TrazaArchivoService::class)->marcarExcel($spreadsheet);
        $spreadsheet->getProperties()
            ->setTitle('Aportes en Línea 2 — '.($rs->razon_social ?? ''))
            ->setCreator('BryNex');

        $sheet = $spreadsheet->getSheetByName('Liquidaciones');
        if (! $sheet) {
            throw new \RuntimeException('La plantilla no tiene la hoja Liquidaciones.');
        }

        $this->escribirAportante($sheet, $rs);
        $this->escribirLiquidacion($sheet, $rs, $periodoSS, $periodoSalud, $tipoPlanilla, $nombreArl);

        $total = $planos->count();
        if ($total > 0) {
            $ultima = self::FILA_INICIAL + $total - 1;
            // Los estilos van ANTES de los datos: writeEmployeeRow deja formato
            // de texto en la cédula y de fecha en ING/RET, y replicar después
            // los pisaría con el formato de la fila 19.
            $this->replicarEstilos($sheet, $ultima);
            foreach ($planos as $i => $p) {
                $this->writeEmployeeRow($sheet, self::FILA_INICIAL + $i, $p, $i + 1);
            }
            $this->ajustarValidaciones($sheet, $ultima);
        }

        $spreadsheet->setActiveSheetIndexByName('Liquidaciones');

        return $spreadsheet;
    }

    /** Bloque de identificación del aportante (esquina superior derecha). */
    private function escribirAportante(Worksheet $sheet, object $rs): void
    {
        $nit = (string) ($rs->nit ?? '');
        if ($nit !== '' && isset($rs->dv) && $rs->dv !== null && $rs->dv !== '') {
            $nit .= '-'.$rs->dv;
        }

        $sheet->setCellValue('K1', $rs->razon_social ?? '');
        $sheet->setCellValue('K2', $nit !== '' ? 'NIT '.$nit : '');
        // K3 solo si la razón social tiene sucursal; K4-K6 son datos del perfil
        // del portal (tipo de empleador, perfil, último acceso) y no se inventan.
        $sheet->setCellValue('K3', ! empty($rs->nombre_sucursal)
            ? 'SUCURSAL PRINCIPAL: '.$rs->nombre_sucursal
            : '');
    }

    /** Fila 10: los mismos valores que escribe el Excel de Aportes en Línea. */
    private function escribirLiquidacion(
        Worksheet $sheet,
        object $rs,
        string $periodoSS,
        string $periodoSalud,
        string $tipoPlanilla,
        ?string $nombreArl
    ): void {
        $sheet->setCellValue('A10', $periodoSS);
        $sheet->setCellValue('C10', $periodoSalud);
        $sheet->setCellValue('D10', $tipoPlanilla);
        $sheet->setCellValue('G10', $rs->codigo_sucursal ?? '');
        $sheet->setCellValue('H10', $rs->nombre_sucursal ?? '');
        $sheet->setCellValue('K10', $nombreArl ?? '');
    }

    /**
     * La plantilla trae estilo hasta la fila 23; de la 24 en adelante las filas
     * saldrían en blanco. Se copia el de la fila 19 columna por columna (un
     * duplicateStyle por columna, no por celda).
     */
    private function replicarEstilos(Worksheet $sheet, int $ultima): void
    {
        if ($ultima <= self::FILA_INICIAL) {
            return;
        }

        $desde = self::FILA_INICIAL + 1;
        for ($ci = 1; $ci <= self::TOTAL_COLUMNAS; $ci++) {
            $col = $this->col($ci);
            $sheet->duplicateStyle(
                $sheet->getStyle($col.self::FILA_INICIAL),
                "{$col}{$desde}:{$col}{$ultima}"
            );
        }
    }

    /**
     * Las validaciones de la plantilla cubren solo las filas 19-23 (las cinco
     * del archivo que exportó el portal). Se estiran —o se recortan— hasta la
     * última fila con datos para que las listas desplegables sirvan en todas.
     */
    private function ajustarValidaciones(Worksheet $sheet, int $ultima): void
    {
        $final = self::FILA_FINAL_PLANTILLA;

        foreach ($sheet->getDataValidationCollection() as $rango => $validacion) {
            $nuevo = preg_replace_callback(
                '/([A-Z]{1,3})'.self::FILA_INICIAL.':([A-Z]{1,3})'.$final.'\b/',
                fn ($m) => "{$m[1]}".self::FILA_INICIAL.":{$m[2]}{$ultima}",
                $rango
            );

            if ($nuevo !== $rango) {
                $sheet->setDataValidation($rango, null);
                $sheet->setDataValidation($nuevo, $validacion);
            }
        }
    }
}
