<?php

namespace App\Console\Commands;

use App\Services\NuevaEps\NuevaEpsConciliacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pone al día los radicados de EPS de Nueva EPS con las novedades del portal.
 *
 * Proceso aparte y no job, por la misma razón que `eps:conciliar-sura`: la cola
 * es `database` con retry_after de 90 s y una empresa tarda más que eso. El
 * botón de Afiliaciones lo lanza en segundo plano y lee el progreso de la caché.
 */
class NuevaEpsConciliar extends Command
{
    protected $signature = 'eps:conciliar-nueva-eps
                            {--aliado=2 : Aliado cuyos radicados se revisan}
                            {--nit= : Solo esta empresa}
                            {--usuario= : Usuario de BryNex al que se atribuyen los cambios}
                            {--simular : Consulta el portal pero no toca ningún radicado}';

    protected $description = 'Actualiza los radicados de EPS de Nueva EPS (número, estado y certificado) con lo que dice el portal';

    /** Una sola a la vez por aliado: dos navegadores con el mismo usuario se tumban la sesión. */
    private const DURACION_CANDADO = 3600;

    public static function claveEstado(int $aliadoId): string
    {
        return "nueva_eps_conciliacion:estado:{$aliadoId}";
    }

    public static function claveCandado(int $aliadoId): string
    {
        return "nueva_eps_conciliacion:candado:{$aliadoId}";
    }

    public function handle(NuevaEpsConciliacionService $servicio): int
    {
        $aliadoId  = (int) $this->option('aliado');
        $usuarioId = $this->option('usuario') ? (int) $this->option('usuario') : null;
        $simular   = (bool) $this->option('simular');

        $candado = Cache::lock(self::claveCandado($aliadoId), self::DURACION_CANDADO);

        if (! $candado->get()) {
            $this->error("Ya hay una conciliación de Nueva EPS corriendo para el aliado {$aliadoId}.");

            return self::FAILURE;
        }

        $estado = ['corriendo' => true, 'inicio' => now()->toIso8601String(), 'mensaje' => 'Buscando pendientes…', 'detalle' => [], 'simulado' => $simular];
        Cache::put(self::claveEstado($aliadoId), $estado, now()->addDay());

        try {
            $resultado = $servicio->conciliar(
                $aliadoId,
                $this->option('nit'),
                $simular,
                $usuarioId,
                function (string $mensaje, array $parcial) use (&$estado, $aliadoId) {
                    $this->line('  '.$mensaje);
                    $estado['mensaje'] = $mensaje;
                    $estado['detalle'] = $parcial;
                    Cache::put(self::claveEstado($aliadoId), $estado, now()->addDay());
                },
            );

            Cache::put(self::claveEstado($aliadoId), $resultado + [
                'corriendo' => false,
                'inicio'    => $estado['inicio'],
                'fin'       => now()->toIso8601String(),
                'mensaje'   => 'Terminado.',
            ], now()->addDay());

            $this->newLine();
            $this->table(
                ['Radicado', 'Cédula', 'Nombre', 'Empresa', 'Acción', 'Detalle'],
                collect($resultado['detalle'])->map(fn ($f) => [
                    $f['radicado_id'], $f['cedula'], $f['nombre'], $f['empresa'], $f['accion'], $f['mensaje'],
                ])->all()
            );
            $this->info(sprintf(
                '%s%d a OK · %d en trámite · %d sin reingreso · %d errores (de %d).',
                $simular ? '[SIMULACIÓN] ' : '',
                $resultado['cerrados'], $resultado['tramite'], $resultado['faltan'], $resultado['errores'], $resultado['total']
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            Cache::put(self::claveEstado($aliadoId), $estado + [
                'corriendo' => false,
                'fin'       => now()->toIso8601String(),
                'error'     => $e->getMessage(),
            ], now()->addDay());
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $candado->release();
        }
    }
}
