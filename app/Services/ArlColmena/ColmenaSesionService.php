<?php

namespace App\Services\ArlColmena;

use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Abre sesión en la Oficina Digital ARL de Colmena sin intervención humana.
 *
 * El login es Azure AD B2C: no se puede pedir por HTTP, así que lo hace un
 * Chrome headless (`scripts/colmena-login.mjs`) que devuelve el JWT de la
 * cookie `token` y el consecutivo del contrato elegido. De ahí en adelante todo
 * el trabajo va por HTTP desde PHP — ver [[ColmenaApiService]].
 *
 * Las claves salen del módulo de claves (`clave_accesos`, tipo ARL, entidad
 * COLMENA) buscando por el NIT de la empresa: es donde el equipo ya las carga,
 * y así cambiarlas allí cambia lo que se usa aquí, sin copias que se queden
 * viejas.
 */
class ColmenaSesionService
{
    /** Login B2C + elección de contrato: son varias redirecciones. */
    private const TIMEOUT_SEGUNDOS = 180;

    /**
     * Devuelve token y contrato para ese NIT, entrando al portal.
     *
     * @return array{token:string, contrato:string, empresa:?string}
     */
    public static function abrir(string $nit): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $clave = self::credencialPara($nit)
            ?? throw new RuntimeException("No hay clave de ARL Colmena para el NIT {$nit} en el módulo de claves.");

        $entrada = json_encode([
            'usuario' => $clave['usuario'],
            'contrasena' => $clave['contrasena'],
            'nitEmpresa' => $nit,
        ], JSON_UNESCAPED_UNICODE);

        $resultado = Process::path(base_path())
            ->timeout(self::TIMEOUT_SEGUNDOS)
            ->input($entrada)
            ->run(ArlSuraSesionService::binarioNode().' scripts/colmena-login.mjs');

        $salida = json_decode(trim($resultado->output()), true) ?: [];

        if (! ($salida['ok'] ?? false) || empty($salida['token'])) {
            $error = $salida['error'] ?? trim($resultado->errorOutput()) ?: 'El login no devolvió una sesión.';

            // El script nunca imprime la clave; lo que llega es la pantalla en
            // la que se quedó, que es justo lo que hace falta para arreglarlo.
            Log::warning('ARL Colmena: no se pudo abrir sesión', [
                'nit' => $nit,
                'error' => $error,
                'url' => $salida['url'] ?? null,
            ]);

            throw new RuntimeException("No se pudo abrir sesión en ARL Colmena: {$error}");
        }

        return [
            'token' => $salida['token'],
            'contrato' => (string) $salida['contrato'],
            'empresa' => $salida['empresa'] ?? null,
        ];
    }

    /**
     * La clave de Colmena de esa empresa, en cualquier aliado.
     *
     * Es la misma empresa ante la ARL, así que no se filtra por aliado: si
     * Brygar y Fecop tienen cargada la misma empresa, cualquiera de las dos
     * claves entra al mismo contrato.
     *
     * @return array{usuario:string, contrasena:string}|null
     */
    public static function credencialPara(string $nit): ?array
    {
        $nit = preg_replace('/\D/', '', $nit);

        if (! $nit) {
            return null;
        }

        $clave = DB::table('clave_accesos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('rs.nit', $nit)
            ->where('c.tipo', 'ARL')
            ->where('c.entidad', 'like', '%COLMENA%')
            ->where('c.activo', true)
            ->whereNotNull('c.usuario')
            ->whereNotNull('c.contrasena')
            ->orderByDesc('c.id')
            ->first(['c.usuario', 'c.contrasena']);

        if (! $clave) {
            return null;
        }

        return [
            'usuario' => trim($clave->usuario),
            'contrasena' => $clave->contrasena,
        ];
    }
}
