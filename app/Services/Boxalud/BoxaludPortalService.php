<?php

namespace App\Services\Boxalud;

use App\Services\ArlSura\ArlSuraSesionService;
use App\Services\EpsPortal\EpsClavePortal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Entra a un portal Boxalud con la clave de la empresa y lee lo que publica.
 *
 * Solo lectura: de aquí sale el listado de afiliaciones que la EPS tiene de esa
 * razón social, que es con lo que se cruzan los radicados y los retiros. Lo que
 * se afilia va por otro camino (BoxaludService, con la persona delante).
 *
 * La clave sale del módulo de claves por el NIT, igual que en las demás EPS:
 * cambiarla allí basta. Si el portal la rechaza se guarda su huella y no se
 * vuelve a probar hasta que cambie, porque estos portales bloquean al usuario
 * tras varios intentos fallidos.
 */
class BoxaludPortalService
{
    /** Login (~15 s) más el grid entero, que va de página en página por callback. */
    private const TIMEOUT_SEGUNDOS = 420;

    public static function conf(string $eps): array
    {
        $conf = config("boxalud.{$eps}");

        if (! $conf) {
            throw new RuntimeException("La EPS «{$eps}» no está configurada para el portal Boxalud.");
        }

        return $conf;
    }

    /**
     * El usuario y la clave de esa empresa, o por qué no se puede entrar.
     *
     * @return array{usuario:string, contrasena:string, empresa:\App\Models\EpsPortalEmpresa}|array{error:string}
     */
    public static function credencial(string $eps, string $nit): array
    {
        $conf = self::conf($eps);
        $nit = preg_replace('/\D/', '', $nit);

        return EpsClavePortal::para(
            $eps, $conf['clave_entidad'], $conf['nombre'], $nit, 'EPS', null,
            fn (string $usuario) => self::usuarioDelPortal($usuario),
        );
    }

    /**
     * En Boxalud el usuario del empleador es el NIT con una P detrás.
     *
     * El portal distingue los dos casos y lo dice: con el NIT pelado responde
     * "El usuario no se encuentra registrado", y con la P responde por la clave.
     * Varias filas del llavero guardan solo el NIT, así que se completa aquí en
     * vez de gastar un intento fallido contra la cuenta.
     */
    public static function usuarioDelPortal(string $usuario): string
    {
        $usuario = trim($usuario);

        return preg_match('/^\d+$/', $usuario) ? $usuario.'P' : $usuario;
    }

    /**
     * El listado de afiliaciones que la EPS tiene de esa empresa.
     *
     * @return array{ok:bool, error?:string, columnas?:array, filas?:array, afiliados?:array}
     */
    public static function afiliados(string $eps, string $nit): array
    {
        $salida = self::ejecutar($eps, $nit, ['modo' => 'afiliados']);

        if (! ($salida['ok'] ?? false)) {
            return $salida;
        }

        $salida['afiliados'] = self::normalizar($salida['columnas'] ?? [], $salida['filas'] ?? []);

        return $salida;
    }

    /**
     * Corre el Chrome del servidor contra el portal.
     *
     * @return array la salida del script; con `error` si no se pudo ni empezar
     */
    public static function ejecutar(string $eps, string $nit, array $datos, ?int $segundos = null): array
    {
        $conf = self::conf($eps);
        $cred = self::credencial($eps, $nit);

        if (isset($cred['error'])) {
            return ['ok' => false, 'paso' => 'credencial', 'error' => $cred['error']];
        }

        $resultado = Process::path(base_path())
            ->timeout($segundos ?? self::TIMEOUT_SEGUNDOS)
            // Por stdin para que ni la clave ni el proxy queden en `ps`.
            ->input(json_encode($datos + [
                'host' => $conf['host'],
                'usuario' => $cred['usuario'],
                'contrasena' => $cred['contrasena'],
                'proxy' => config('services.proxy_colombia.url'),
            ], JSON_UNESCAPED_UNICODE))
            ->run(ArlSuraSesionService::binarioNode().' scripts/boxalud-portal.mjs');

        $crudo = trim($resultado->output());
        $salida = json_decode($crudo, true);

        if (! is_array($salida)) {
            // "No respondió" a secas obliga a repetir la corrida para saber qué pasó.
            return ['ok' => false, 'paso' => 'proceso', 'error' => mb_substr(trim($resultado->errorOutput())
                ?: 'El portal no respondió (código '.$resultado->exitCode().': '.mb_substr($crudo, 0, 150).')', 0, 400)];
        }

        $empresa = $cred['empresa'];

        if ($salida['ok'] ?? false) {
            EpsClavePortal::exito($empresa);
        } else {
            // El mensaje nunca trae la clave: el script no la imprime.
            Log::warning("Boxalud {$eps}: el portal falló", ['nit' => $nit, 'paso' => $salida['paso'] ?? null, 'error' => $salida['error'] ?? null]);

            if (($salida['paso'] ?? null) === 'login') {
                EpsClavePortal::rechazada($empresa, $cred['usuario'], $cred['contrasena'], (string) ($salida['error'] ?? ''));
            }
        }

        return $salida;
    }

    /**
     * El grid, con cada columna en su nombre.
     *
     * Se guía por los encabezados y no por la posición: el portal las mueve
     * según el usuario, y leer la cédula de la columna equivocada sería cruzar
     * contratos de otra persona.
     *
     * @return array<int, array{documento:string, tipo_documento:?string, nombre:?string, estado:?string, afiliacion:?string, relacion:?string, fecha_inicio:?string, fecha_radicacion:?string, crudo:array}>
     */
    public static function normalizar(array $columnas, array $filas): array
    {
        $donde = function (string $patron) use ($columnas): ?int {
            foreach ($columnas as $i => $titulo) {
                if (preg_match($patron, self::sinTildes((string) $titulo))) {
                    return $i;
                }
            }

            return null;
        };

        $indices = [
            'documento' => $donde('/(numero|nro|n°).*(identificacion|documento)|^identificacion|^documento|^cedula/i'),
            'tipo_documento' => $donde('/tipo.*(identificacion|documento)/i'),
            'nombre' => $donde('/nombre|afiliado/i'),
            'estado' => $donde('/estado/i'),
            'afiliacion' => $donde('/(numero|nro|n°).*afiliacion|^afiliacion/i'),
            'relacion' => $donde('/relacion laboral/i'),
            'fecha_inicio' => $donde('/fecha.*inicio/i'),
            'fecha_radicacion' => $donde('/fecha.*radicacion/i'),
        ];

        $afiliados = [];

        foreach ($filas as $fila) {
            $valor = fn (?int $i) => $i !== null && isset($fila[$i]) ? trim((string) $fila[$i]) : null;

            // Sin cédula la fila no sirve para cruzar nada: suele ser el
            // subtotal o una columna de botones del grid.
            $documento = preg_replace('/\D/', '', (string) $valor($indices['documento']));

            if ($documento === '') {
                continue;
            }

            $afiliados[] = [
                'documento' => ltrim($documento, '0'),
                'tipo_documento' => $valor($indices['tipo_documento']),
                'nombre' => $valor($indices['nombre']),
                'estado' => $valor($indices['estado']),
                'afiliacion' => $valor($indices['afiliacion']),
                'relacion' => $valor($indices['relacion']),
                'fecha_inicio' => $valor($indices['fecha_inicio']),
                'fecha_radicacion' => $valor($indices['fecha_radicacion']),
                'crudo' => $fila,
            ];
        }

        return $afiliados;
    }

    /** "Número de afiliación" → "Numero de afiliacion", para comparar encabezados. */
    private static function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N']);
    }
}
