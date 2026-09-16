<?php

namespace App\Services\Caja;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lee el "Listado de trabajadores" que exporta Comfandi.
 *
 * En el portal se pide desde Gestión de trabajadores y queda como un radicado
 * de tipo "Listado de trabajadores"; el Excel se baja desde ahí. Trae la
 * empresa completa —sin la paginación de a 5 que hacía imposible raspar la
 * tabla— y, de regalo, **los beneficiarios de cada trabajador**, hasta cuatro,
 * con parentesco y fecha de nacimiento.
 *
 * Formato (29 columnas): tipo y número de documento del trabajador, nombres,
 * fecha de afiliación, fecha de ingreso, y luego cuatro bloques de seis
 * columnas por beneficiario. Las fechas vienen pegadas como AAAAMMDD.
 */
class ComfandiListadoExcel
{
    /** Cada beneficiario ocupa seis columnas, empezando en la sexta. */
    private const BENEFICIARIO_INICIO = 5;

    private const BENEFICIARIO_ANCHO = 6;

    private const BENEFICIARIOS_MAX = 4;

    /** Parentesco de Comfandi → el que usa BryNex en `beneficiarios`. */
    private const PARENTESCOS = [
        'HIJO' => 'Hijo',
        'HIJA' => 'Hija',
        'HIJASTRO' => 'HIJASTRO',
        'HIJASTRA' => 'HIJASTRA',
        'CONYUGE COMPANERA' => 'Cónyuge',
        'CONYUGE COMPANERO' => 'Cónyuge',
        'CONYUGE' => 'Cónyuge',
        'MADRE' => 'Madre',
        'PADRE' => 'Padre',
    ];

    /**
     * @return array{filas: array<int, array{0:string,1:string,2:?string,3:?string}>, beneficiarios: array<string, array>, total: int}
     */
    public function leer(string $ruta): array
    {
        try {
            $hoja = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo: '.$e->getMessage());
        }

        $encabezado = array_shift($hoja) ?: [];
        if (! Str::contains(Str::lower(implode('|', array_map('strval', $encabezado))), 'documento trabajador')) {
            throw new RuntimeException('El archivo no parece el "Listado de trabajadores" de Comfandi: no trae sus columnas.');
        }

        $filas = [];
        $beneficiarios = [];

        foreach ($hoja as $r) {
            $documento = ltrim(preg_replace('/\D/', '', (string) ($r[1] ?? '')), '0');
            if ($documento === '') {
                continue;
            }

            // Mismo orden que la tabla del portal: documento, nombre, ingreso a
            // la empresa, ingreso a la caja.
            $filas[] = [
                $documento,
                trim(preg_replace('/\s+/', ' ', (string) ($r[2] ?? ''))),
                $this->fecha($r[4] ?? null),
                $this->fecha($r[3] ?? null),
            ];

            $suyos = [];
            for ($i = 0; $i < self::BENEFICIARIOS_MAX; $i++) {
                $b = self::BENEFICIARIO_INICIO + $i * self::BENEFICIARIO_ANCHO;
                $doc = ltrim(preg_replace('/\D/', '', (string) ($r[$b + 1] ?? '')), '0');
                if ($doc === '') {
                    continue;
                }
                $suyos[] = [
                    'tipo_doc' => strtoupper(trim((string) ($r[$b] ?? ''))) ?: null,
                    'documento' => $doc,
                    'nombre' => trim(preg_replace('/\s+/', ' ', (string) ($r[$b + 2] ?? ''))),
                    'genero' => trim((string) ($r[$b + 3] ?? '')) ?: null,
                    'nacimiento' => $this->fechaIso($r[$b + 4] ?? null),
                    'parentesco' => $this->parentesco($r[$b + 5] ?? null),
                ];
            }
            if ($suyos) {
                $beneficiarios[$documento] = $suyos;
            }
        }

        if (! $filas) {
            throw new RuntimeException('El archivo no trae ningún trabajador.');
        }

        return ['filas' => $filas, 'beneficiarios' => $beneficiarios, 'total' => count($filas)];
    }

    /** AAAAMMDD → DD/MM/AAAA, que es como se muestra en el detalle. */
    private function fecha(mixed $valor): ?string
    {
        $iso = $this->fechaIso($valor);

        return $iso ? implode('/', array_reverse(explode('-', $iso))) : null;
    }

    /** AAAAMMDD → AAAA-MM-DD, para guardar. */
    private function fechaIso(mixed $valor): ?string
    {
        $v = preg_replace('/\D/', '', (string) $valor);
        if (strlen($v) !== 8) {
            return null;
        }
        [$a, $m, $d] = [substr($v, 0, 4), substr($v, 4, 2), substr($v, 6, 2)];

        return checkdate((int) $m, (int) $d, (int) $a) ? "$a-$m-$d" : null;
    }

    /** "Cónyuge # Compañera" y demás, normalizados a lo que ya usa BryNex. */
    private function parentesco(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        $clave = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]/', ' ', Str::upper(Str::ascii($texto)))));

        return self::PARENTESCOS[$clave] ?? $texto;
    }
}
