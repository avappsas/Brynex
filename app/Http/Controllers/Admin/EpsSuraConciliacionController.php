<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\EpsSuraConciliar;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Botón de Afiliaciones que concilia los radicados de EPS SURA con el portal.
 *
 * El trabajo real lo hace `eps:conciliar-sura` en un proceso aparte (ver el
 * comando para el porqué); aquí solo se lanza y se lee su progreso.
 */
class EpsSuraConciliacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function iniciar(Request $request): JsonResponse
    {
        $aliadoId = (int) session('aliado_id_activo');
        $simular  = $request->boolean('simular');

        if (! $aliadoId) {
            return response()->json(['ok' => false, 'mensaje' => 'No hay aliado activo.'], 422);
        }

        // Se prueba el candado solo para responder de inmediato; el comando lo
        // vuelve a tomar y es quien de verdad impide dos corridas a la vez.
        $candado = Cache::lock(EpsSuraConciliar::claveCandado($aliadoId), 5);
        if (! $candado->get()) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya hay una conciliación corriendo.'], 409);
        }
        $candado->release();

        Cache::put(EpsSuraConciliar::claveEstado($aliadoId), [
            'corriendo' => true,
            'inicio'    => now()->toIso8601String(),
            'mensaje'   => 'Iniciando…',
            'detalle'   => [],
            'simulado'  => $simular,
        ], now()->addDay());

        $comando = sprintf(
            'nohup %s %s eps:conciliar-sura --aliado=%d --usuario=%d%s > %s 2>&1 &',
            escapeshellarg(self::binarioPhp()),
            escapeshellarg(base_path('artisan')),
            $aliadoId,
            (int) Auth::id(),
            $simular ? ' --simular' : '',
            escapeshellarg(storage_path('logs/eps-sura-conciliacion.log'))
        );
        exec($comando);

        return response()->json(['ok' => true]);
    }

    public function estado(): JsonResponse
    {
        $aliadoId = (int) session('aliado_id_activo');

        return response()->json(Cache::get(EpsSuraConciliar::claveEstado($aliadoId)) ?? ['corriendo' => false, 'vacio' => true]);
    }

    /**
     * El php de línea de comandos. Bajo mod_php `PHP_BINARY` es el de Apache (o
     * vacío), no algo que pueda correr artisan; por eso se busca en las rutas
     * habituales, con PHP_CLI_BIN para fijarlo.
     */
    private static function binarioPhp(): string
    {
        if ($configurado = env('PHP_CLI_BIN')) {
            return $configurado;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY) {
            return PHP_BINARY;
        }

        foreach (['/usr/bin/php', '/usr/local/bin/php', '/opt/homebrew/bin/php'] as $ruta) {
            if (is_executable($ruta)) {
                return $ruta;
            }
        }

        return 'php';
    }
}
