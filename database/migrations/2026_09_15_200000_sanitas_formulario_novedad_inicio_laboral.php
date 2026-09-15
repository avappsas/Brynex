<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sanitas recibe el inicio laboral como novedad ("Cambio de empleador" en su
 * formulario web) y pide el formulario con "B. Reporte de novedades" y la
 * novedad 9 "Inicio de relación laboral" marcados. La X fija de "A. Afiliación"
 * pasa a depender del trámite y se agregan las dos marcas de la novedad.
 * Coordenadas medidas sobre la plantilla de producción (15-sep-2026).
 */
return new class extends Migration
{
    private const CODIGO = 'EPS005';

    public function up(): void
    {
        $eps = DB::table('eps')->where('codigo', self::CODIGO)->first(['id', 'formulario_campos']);
        $campos = json_decode((string) $eps?->formulario_campos, true);
        if (! is_array($campos) || ! $campos) {
            return;
        }

        $datos = array_column($campos, 'dato');
        foreach ($campos as &$c) {
            // La X de "A. Afiliación" (fila del tipo de trámite, a la izquierda).
            if (($c['dato'] ?? '') === 'static.X_1' && (int) ($c['pagina'] ?? 1) === 1 && abs((float) $c['y'] - 81) < 3 && abs((float) $c['x'] - 77.9) < 3) {
                $c['dato'] = 'tramite.afiliacion_x';
            }
        }
        unset($c);

        $marca = ['width' => 11.9, 'height' => 11.9, 'style' => '', 'align' => 'L', 'tipo' => 'texto'];
        if (! in_array('tramite.novedad_x', $datos, true)) {
            $campos[] = ['dato' => 'tramite.novedad_x', 'pagina' => 1, 'x' => 167.9, 'y' => 81, 'font_size' => 9] + $marca;
        }
        if (! in_array('novedad.inicio_laboral_x', $datos, true)) {
            $campos[] = ['dato' => 'novedad.inicio_laboral_x', 'pagina' => 2, 'x' => 43.7, 'y' => 258, 'font_size' => 8] + $marca;
        }

        DB::table('eps')->where('id', $eps->id)->update(['formulario_campos' => json_encode($campos, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        $eps = DB::table('eps')->where('codigo', self::CODIGO)->first(['id', 'formulario_campos']);
        $campos = json_decode((string) $eps?->formulario_campos, true);
        if (! is_array($campos)) {
            return;
        }

        $campos = array_values(array_filter($campos, fn ($c) => ! in_array($c['dato'] ?? '', ['tramite.novedad_x', 'novedad.inicio_laboral_x'], true)));
        foreach ($campos as &$c) {
            if (($c['dato'] ?? '') === 'tramite.afiliacion_x') {
                $c['dato'] = 'static.X_1';
            }
        }
        unset($c);

        DB::table('eps')->where('id', $eps->id)->update(['formulario_campos' => json_encode($campos, JSON_UNESCAPED_UNICODE)]);
    }
};
