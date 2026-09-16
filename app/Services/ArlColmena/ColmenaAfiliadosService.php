<?php

namespace App\Services\ArlColmena;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lee el informe "Trabajadores vigentes" de Colmena, que es la lista contra la
 * que se cruzan los radicados de ARL.
 *
 * Colmena no devuelve el archivo por HTTP: responde `{bytes: "<base64 de un
 * .xls>"}`, así que se reconstruye en disco y se lee con PhpSpreadsheet. El
 * informe viene agrupado por centro de trabajo —con un bloque de encabezado por
 * cada uno— y las filas de personas son las que traen el documento en la
 * columna 3 con el prefijo del tipo ("CC 1130618824").
 *
 * Se verifica el NIT del encabezado antes de creerle nada al archivo: en EPS
 * SURA pasó que el portal entregó el informe de OTRA empresa del mismo usuario
 * y el cruce lo dio por bueno. Ver [[radicados-ok-confirmado-entidad]].
 */
class ColmenaAfiliadosService
{
    /** Columnas de la fila de cada trabajador. */
    private const COL_DOCUMENTO = 3;

    private const COL_NOMBRE = 6;

    private const COL_CARGO = 8;

    private const COL_INICIO = 10;

    private const COL_EPS = 15;

    private const COL_AFP = 16;

    private const COL_SALARIO = 18;

    /**
     * Los vigentes de esa empresa, por número de documento.
     *
     * @return Collection<string, array{tipo:string,numero:string,nombre:string,cargo:?string,inicio:?string,eps:?string,afp:?string,salario:?string,centro:?string}>
     */
    public function vigentes(string $nit): Collection
    {
        $nit = preg_replace('/\D/', '', $nit);
        $xls = (new ColmenaApiService($nit))->informeVigentes();

        // PhpSpreadsheet solo lee de disco; el temporal se borra siempre porque
        // el informe trae cédulas, salarios y fechas de nacimiento.
        $ruta = tempnam(sys_get_temp_dir(), 'colmena_').'.xls';

        try {
            file_put_contents($ruta, $xls);
            $filas = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
        } finally {
            @unlink($ruta);
        }

        $this->verificarEmpresa($filas, $nit);

        $vigentes = collect();
        $centro = null;

        foreach ($filas as $fila) {
            // El encabezado de cada bloque dice a qué centro pertenecen las
            // filas que vienen debajo.
            foreach ($fila as $i => $valor) {
                if (is_string($valor) && str_contains($valor, 'Nombre de Sede y Centro')) {
                    $centro = trim((string) ($fila[$i + 2] ?? $fila[$i + 1] ?? ''));
                }
            }

            $documento = trim((string) ($fila[self::COL_DOCUMENTO] ?? ''));

            if (! preg_match('/^([A-Z]{2,3})\s*0*(\d{3,})$/i', $documento, $m)) {
                continue;
            }

            $vigentes->put(self::documento($m[2]), [
                'tipo' => strtoupper($m[1]),
                'numero' => $m[2],
                'nombre' => trim((string) ($fila[self::COL_NOMBRE] ?? '')),
                'cargo' => trim((string) ($fila[self::COL_CARGO] ?? '')) ?: null,
                'inicio' => trim((string) ($fila[self::COL_INICIO] ?? '')) ?: null,
                'eps' => trim((string) ($fila[self::COL_EPS] ?? '')) ?: null,
                'afp' => trim((string) ($fila[self::COL_AFP] ?? '')) ?: null,
                'salario' => trim((string) ($fila[self::COL_SALARIO] ?? '')) ?: null,
                'centro' => $centro,
            ]);
        }

        if ($vigentes->isEmpty()) {
            throw new RuntimeException('El informe de vigentes de Colmena salió sin trabajadores.');
        }

        return $vigentes;
    }

    /**
     * El encabezado trae "IDENTIFICACIÓN: NI 901709476". Si no es la empresa que
     * se pidió, el informe no sirve: confirmar con él marcaría gente de otra
     * empresa.
     */
    private function verificarEmpresa(array $filas, string $nit): void
    {
        foreach ($filas as $fila) {
            foreach ($fila as $valor) {
                if (is_string($valor) && preg_match('/\bNI\s*0*(\d{6,})/i', $valor, $m)) {
                    if (preg_replace('/\D/', '', $m[1]) === $nit) {
                        return;
                    }

                    throw new RuntimeException(
                        "El informe de Colmena salió de otra empresa (NIT {$m[1]}, se pidió {$nit})."
                    );
                }
            }
        }

        throw new RuntimeException('El informe de Colmena no trae el NIT de la empresa en el encabezado.');
    }

    /** Sin puntos ni ceros a la izquierda, que es como se compara con BryNex. */
    public static function documento(string $numero): string
    {
        return ltrim(preg_replace('/\D/', '', $numero), '0');
    }
}
