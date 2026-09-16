<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\EpsSuraConciliar;
use App\Console\Commands\NuevaEpsConciliar;
use App\Console\Commands\PensionConciliar;
use App\Console\Commands\SaludTotalConciliar;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Botón de Afiliaciones que concilia los radicados con las fuentes oficiales:
 * los portales de EPS (SURA, Nueva EPS y Salud Total) y el RUAF para pensión.
 *
 * El trabajo real lo hace el comando de cada EPS en un proceso aparte (ver
 * `eps:conciliar-sura` para el porqué); aquí solo se lanza y se lee su progreso.
 */
class EpsSuraConciliacionController extends Controller
{
    /** entidad => [clase del comando (claves de caché), firma del comando] */
    private const ENTIDADES = [
        'sura'      => [EpsSuraConciliar::class, 'eps:conciliar-sura'],
        'nueva_eps' => [NuevaEpsConciliar::class, 'eps:conciliar-nueva-eps'],
        'salud_total' => [SaludTotalConciliar::class, 'eps:conciliar-salud-total'],
        'pension'     => [PensionConciliar::class, 'pension:conciliar'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function iniciar(Request $request): JsonResponse
    {
        $aliadoId = (int) session('aliado_id_activo');
        $simular  = $request->boolean('simular');
        [$clase, $comando] = self::ENTIDADES[$request->input('entidad', 'sura')] ?? self::ENTIDADES['sura'];

        if (! $aliadoId) {
            return response()->json(['ok' => false, 'mensaje' => 'No hay aliado activo.'], 422);
        }

        // Se prueba el candado solo para responder de inmediato; el comando lo
        // vuelve a tomar y es quien de verdad impide dos corridas a la vez.
        $candado = Cache::lock($clase::claveCandado($aliadoId), 5);
        if (! $candado->get()) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya hay una conciliación corriendo.'], 409);
        }
        $candado->release();

        Cache::put($clase::claveEstado($aliadoId), [
            'corriendo' => true,
            'inicio' => now()->toIso8601String(),
            'mensaje' => 'Iniciando…',
            'detalle' => [],
            'simulado' => $simular,
        ], now()->addDay());

        exec(sprintf(
            'nohup %s %s %s --aliado=%d --usuario=%d%s%s < /dev/null > %s 2>&1 &',
            escapeshellarg(self::binarioPhp()),
            escapeshellarg(base_path('artisan')),
            $comando,
            $aliadoId,
            (int) Auth::id(),
            $simular ? ' --simular' : '',
            // Solo lo entiende pension:conciliar: revisar también los OK es lo
            // que destapa los traslados de fondo que nadie registró.
            $comando === 'pension:conciliar' && $request->boolean('incluir_ok', true) ? ' --incluir-ok' : '',
            escapeshellarg(storage_path('logs/eps-conciliacion.log'))
        ));

        return response()->json(['ok' => true]);
    }

    public function estado(Request $request): JsonResponse
    {
        $aliadoId = (int) session('aliado_id_activo');
        [$clase] = self::ENTIDADES[$request->query('entidad', 'sura')] ?? self::ENTIDADES['sura'];

        return response()->json(Cache::get($clase::claveEstado($aliadoId)) ?? ['corriendo' => false, 'vacio' => true]);
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
