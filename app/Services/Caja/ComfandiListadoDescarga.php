<?php

namespace App\Services\Caja;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Trae el "Listado de trabajadores" de Comfandi desde la URL firmada que
 * entrega su API.
 *
 * El portal no sirve el Excel: `ria.sucursalcomfandi.com/sus/download` devuelve
 * una URL de S3 firmada y de vida corta (50 minutos). Esa URL S3 no se puede
 * leer desde el navegador —S3 no manda cabeceras CORS—, así que la extensión
 * consigue la firma con el token de la sesión y es BryNex quien baja el
 * archivo. De paso, el token de Comfandi nunca sale del navegador: aquí solo
 * llega una URL temporal.
 */
class ComfandiListadoDescarga
{
    /** De dónde se acepta descargar. Sin esta lista, el endpoint sería un SSRF. */
    private const HOSTS = ['s3.us-east-1.amazonaws.com', 's3.amazonaws.com', 'ria.sucursalcomfandi.com'];

    /** El listado más grande ronda unos cientos de KB; 20 MB es de sobra. */
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private ComfandiListadoExcel $excel) {}

    /**
     * @return array{filas: array, beneficiarios: array, total: int}
     */
    public function bajar(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $esquema = parse_url($url, PHP_URL_SCHEME);

        if ($esquema !== 'https' || ! $host) {
            throw new RuntimeException('La dirección del archivo no es válida.');
        }
        // Coincidencia exacta de host: "s3.amazonaws.com.otro.io" no pasa.
        if (! in_array($host, self::HOSTS, true) && ! str_ends_with($host, '.s3.us-east-1.amazonaws.com')) {
            throw new RuntimeException("El archivo no viene de Comfandi ni de su almacenamiento ({$host}).");
        }

        $r = Http::timeout(90)->withOptions(['stream' => false])->get($url);

        if (! $r->successful()) {
            throw new RuntimeException('Comfandi no entregó el archivo (HTTP '.$r->status().'). La firma dura 50 minutos: vuelve a pedirlo.');
        }

        $cuerpo = $r->body();
        if (strlen($cuerpo) > self::MAX_BYTES) {
            throw new RuntimeException('El archivo es demasiado grande.');
        }
        // Un .xlsx es un zip: empieza por PK\x03\x04. Si llega HTML, es que la
        // firma caducó o el endpoint devolvió un error disfrazado de 200.
        if (! str_starts_with($cuerpo, "PK\x03\x04")) {
            throw new RuntimeException('Lo que llegó no es un Excel: seguramente la firma caducó. Vuelve a generar el listado.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'comfandi_').'.xlsx';
        file_put_contents($tmp, $cuerpo);

        try {
            return $this->excel->leer($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
