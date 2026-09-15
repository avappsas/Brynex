<?php

namespace App\Services\Sos;

use App\Services\ArlSura\ArlSuraSesionService;
use App\Services\EpsPortal\EpsClavePortal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * La sesión del portal de S.O.S. de una empresa, abierta en un Chrome del servidor.
 *
 * El login de S.O.S. pide reCAPTCHA de imágenes, que resuelve una persona desde
 * BryNex (ver `scripts/sos-sesion.mjs`). Por eso la sesión no se abre y cierra
 * en cada trámite como en Salud Total: queda viva en un proceso aparte, que
 * escucha en 127.0.0.1 con token y se cierra solo tras un rato sin uso. Aquí se
 * lanza, se localiza por aliado y NIT, y se le mandan las órdenes.
 */
class SosSesion
{
    public const ENTIDAD = 'sos';

    private const PUERTOS = [18600, 18699];

    private const INACTIVIDAD_MINUTOS = 30;

    public static function credencial(string $nit): array
    {
        return EpsClavePortal::para(self::ENTIDAD, '%SOS%', 'S.O.S.', $nit);
    }

    /** Arranca la sesión si no hay una viva. */
    public static function iniciar(int $aliadoId, string $nit): array
    {
        $nit = preg_replace('/\D/', '', $nit);

        if (($estado = self::estado($aliadoId, $nit)) && ! in_array($estado['etapa'], ['cerrada', 'rechazada', 'error', 'vencida'], true)) {
            return $estado;
        }
        self::cerrar($aliadoId, $nit);

        $cred = self::credencial($nit);
        if (isset($cred['error'])) {
            throw new RuntimeException($cred['error']);
        }

        $puerto = self::puertoLibre();
        $token  = Str::random(40);

        // La clave va en un archivo que el proceso borra al leerlo, no en la línea de comandos.
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $archivo = $dir.'/sos_'.Str::random(16).'.json';
        file_put_contents($archivo, json_encode([
            'usuario' => $cred['usuario'], 'contrasena' => $cred['contrasena'],
            'puerto' => $puerto, 'token' => $token, 'inactividadMinutos' => self::INACTIVIDAD_MINUTOS,
            // En el Mac de desarrollo el Chrome se abre como ventana: el captcha se resuelve ahí mismo.
            'visible' => app()->environment('local'),
        ]));
        chmod($archivo, 0600);

        // Antes de lanzar se cierran los descriptores heredados de PHP: sin eso el
        // proceso se quedaba con la conexión de la petición (el navegador esperaba
        // hasta que el proceso muriera) y con los puertos que escucha el servidor web.
        $lanzar = sprintf(
            'for fd in $(ls /dev/fd); do [ "$fd" -gt 2 ] 2>/dev/null && eval "exec $fd>&-"; done 2>/dev/null; '
            // El cd va aparte: con `cd && nohup … &` bash deja un subproceso que
            // conserva la salida de PHP y la petición no termina hasta que muere node.
            .'cd %s || exit 1; nohup %s scripts/sos-sesion.mjs %s < /dev/null >> %s 2>&1 & echo $!',
            escapeshellarg(base_path()),
            escapeshellarg(ArlSuraSesionService::binarioNode()),
            escapeshellarg($archivo),
            escapeshellarg(storage_path('logs/sos-sesion.log'))
        );
        exec('bash -c '.escapeshellarg($lanzar), $salida);

        Cache::put(self::clave($aliadoId, $nit), [
            'puerto' => $puerto, 'token' => $token, 'pid' => (int) ($salida[0] ?? 0), 'inicio' => now()->toIso8601String(),
        ], now()->addHours(12));

        for ($i = 0; $i < 20; $i++) {
            usleep(500000);
            if ($estado = self::estado($aliadoId, $nit)) {
                return $estado;
            }
        }

        return ['etapa' => 'abriendo', 'mensaje' => 'Abriendo el portal de S.O.S.…'];
    }

    /** Etapa de la sesión (abriendo, captcha, entrando, lista, vencida, rechazada, error) o null si no hay proceso. */
    public static function estado(int $aliadoId, string $nit): ?array
    {
        $info = Cache::get(self::clave($aliadoId, $nit));
        if (! $info) {
            return null;
        }

        try {
            $r = Http::withHeaders(['X-Token' => $info['token']])->timeout(20)
                ->get("http://127.0.0.1:{$info['puerto']}/estado");
        } catch (Throwable) {
            return null;
        }

        $estado = $r->json() ?? [];

        if (($estado['etapa'] ?? null) === 'rechazada' && ! ($info['rechazo_registrado'] ?? false)) {
            $cred = self::credencial($nit);
            if (isset($cred['empresa'])) {
                EpsClavePortal::rechazada($cred['empresa'], $cred['usuario'], $cred['contrasena'], (string) ($estado['mensaje'] ?? 'Clave rechazada'));
            }
            Cache::put(self::clave($aliadoId, $nit), $info + ['rechazo_registrado' => true], now()->addHours(12));
        }
        if (($estado['etapa'] ?? null) === 'lista' && ! ($info['exito_registrado'] ?? false)) {
            $cred = self::credencial($nit);
            if (isset($cred['empresa'])) {
                EpsClavePortal::exito($cred['empresa']);
            }
            Cache::put(self::clave($aliadoId, $nit), $info + ['exito_registrado' => true], now()->addHours(12));
        }

        return $estado ?: null;
    }

    public static function clic(int $aliadoId, string $nit, float $x, float $y): array
    {
        return self::llamar($aliadoId, $nit, '/clic', ['x' => $x, 'y' => $y], 30);
    }

    public static function reiniciarCaptcha(int $aliadoId, string $nit): array
    {
        return self::llamar($aliadoId, $nit, '/captcha/reiniciar', [], 90);
    }

    /** Orden al proceso: consultar, certificado, registrar o adjuntar. */
    public static function llamar(int $aliadoId, string $nit, string $ruta, array $datos = [], int $timeout = 240): array
    {
        $info = Cache::get(self::clave($aliadoId, $nit));
        if (! $info) {
            throw new RuntimeException('No hay sesión de S.O.S. abierta para esta empresa: iníciala primero.');
        }

        try {
            $r = Http::withHeaders(['X-Token' => $info['token']])->timeout($timeout)
                ->post("http://127.0.0.1:{$info['puerto']}{$ruta}", $datos);
        } catch (Throwable $e) {
            throw new RuntimeException('La sesión de S.O.S. no respondió: '.$e->getMessage());
        }

        $json = $r->json();
        if (! is_array($json)) {
            throw new RuntimeException('La sesión de S.O.S. devolvió una respuesta inválida.');
        }
        if (! ($json['ok'] ?? false) && in_array($r->status(), [409, 500], true)) {
            throw new RuntimeException($json['error'] ?? 'S.O.S. devolvió un error.');
        }

        return $json;
    }

    public static function cerrar(int $aliadoId, string $nit): void
    {
        $info = Cache::pull(self::clave($aliadoId, $nit));
        if (! $info) {
            return;
        }
        try {
            Http::withHeaders(['X-Token' => $info['token']])->timeout(5)->post("http://127.0.0.1:{$info['puerto']}/cerrar");
        } catch (Throwable) {
            // Ya estaba cerrada.
        }
    }

    private static function clave(int $aliadoId, string $nit): string
    {
        return 'sos_sesion:'.$aliadoId.':'.preg_replace('/\D/', '', $nit);
    }

    private static function puertoLibre(): int
    {
        for ($i = 0; $i < 30; $i++) {
            $puerto = random_int(...self::PUERTOS);
            $s = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);
            if (! $s) {
                return $puerto;
            }
            fclose($s);
        }

        throw new RuntimeException('No hay puertos libres para la sesión de S.O.S.');
    }
}
