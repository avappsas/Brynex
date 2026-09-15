<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Formulario de EPS Comfenalco Valle (delagente), según las devoluciones de la
 * asesora Lola Baena (sep-2026): el ítem 1 siempre como "B. Reporte de
 * novedades" (no Afiliación), la novedad 9 "Inicio de relación laboral" marcada,
 * y la firma del cotizante y la de la empresa en la primera página. Como la
 * asesora lo pide para todos los trámites, las marcas son fijas en la plantilla.
 * Coordenadas medidas sobre la plantilla de producción (15-sep-2026).
 */
return new class extends Migration
{
    private const CODIGO = 'EPS012';

    public function up(): void
    {
        $eps = DB::table('eps')->where('codigo', self::CODIGO)->first(['id', 'formulario_campos']);
        $campos = json_decode((string) $eps?->formulario_campos, true);
        if (! is_array($campos) || ! $campos) {
            return;
        }

        foreach ($campos as &$c) {
            $p1 = (int) ($c['pagina'] ?? 1) === 1;
            // X del tipo de trámite: de "A. Afiliación" a "B. Reporte de novedades" (misma columna, fila de abajo).
            if ($p1 && ($c['dato'] ?? '') === 'static.X_2' && abs((float) $c['y'] - 105.5) < 2) {
                $c['y'] = 115.5;
            }
            // Firma del cotizante dentro del recuadro 70 (estaba encima de la línea).
            if ($p1 && ($c['dato'] ?? '') === 'cliente.firma_2__1' && abs((float) $c['y'] - 816.2) < 2) {
                $c['y'] = 828;
                $c['height'] = 18;
            }
        }
        unset($c);

        $datos = array_column($campos, 'dato');
        if (! in_array('static.X_13', $datos, true)) {
            $campos[] = ['dato' => 'static.X_13', 'pagina' => 1, 'x' => 139, 'y' => 643, 'width' => 10.6, 'height' => 10.6, 'font_size' => 8, 'style' => '', 'align' => 'L', 'tipo' => 'texto'];
        }
        if (! in_array('empresa.sello__1', $datos, true)) {
            $campos[] = ['dato' => 'empresa.sello__1', 'pagina' => 1, 'x' => 360, 'y' => 828, 'width' => 120, 'height' => 18, 'font_size' => 9, 'style' => '', 'align' => 'L', 'tipo' => 'imagen'];
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

        $campos = array_values(array_filter($campos, fn ($c) => ! in_array($c['dato'] ?? '', ['static.X_13', 'empresa.sello__1'], true)));
        foreach ($campos as &$c) {
            if (($c['dato'] ?? '') === 'static.X_2' && abs((float) $c['y'] - 115.5) < 1) {
                $c['y'] = 105.5;
            }
            if (($c['dato'] ?? '') === 'cliente.firma_2__1' && abs((float) $c['y'] - 828) < 1) {
                $c['y'] = 816.2;
                $c['height'] = 28.5;
            }
        }
        unset($c);

        DB::table('eps')->where('id', $eps->id)->update(['formulario_campos' => json_encode($campos, JSON_UNESCAPED_UNICODE)]);
    }
};
