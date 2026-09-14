<?php

namespace App\Console\Commands;

use App\Services\EpsSura\EpsSuraConciliacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Consulta en el portal de EPS SURA los radicados de EPS pendientes y cierra los
 * que ya estén hechos.
 *
 * Corre como proceso aparte y no como job: la cola es `database` con
 * retry_after de 90 s, y una sola empresa tarda más que eso (login más ~12 s por
 * cédula), así que otro worker tomaría el mismo trabajo a mitad de camino. El
 * botón de Afiliaciones lanza este comando en segundo plano y lee el progreso
 * de la caché.
 */
class EpsSuraConciliar extends Command
{
    protected $signature = 'eps:conciliar-sura
                            {--aliado=2 : Aliado cuyos radicados se revisan}
                            {--nit= : Solo esta empresa}
                            {--usuario= : Usuario de BryNex al que se atribuyen los cierres}
                            {--simular : Consulta el portal pero no cierra ningún radicado}';

    protected $description = 'Cierra los radicados de EPS SURA pendientes que ya están vigentes en el portal';

    /** Una sola conciliación por aliado a la vez: dos Chrome con el mismo usuario se tumban la sesión. */
    private const DURACION_CANDADO = 3600;

    public static function claveEstado(int $aliadoId): string
    {
        return "eps_sura_conciliacion:estado:{$aliadoId}";
    }

    public static function claveCandado(int $aliadoId): string
    {
        return "eps_sura_conciliacion:candado:{$aliadoId}";
    }

    public function handle(EpsSuraConciliacionService $servicio): int
    {
        $aliadoId  = (int) $this->option('aliado');
        $usuarioId = $this->option('usuario') ? (int) $this->option('usuario') : null;
        $simular   = (bool) $this->option('simular');

        $candado = Cache::lock(self::claveCandado($aliadoId), self::DURACION_CANDADO);

        if (! $candado->get()) {
            $this->error("Ya hay una conciliación de EPS SURA corriendo para el aliado {$aliadoId}.");

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
                '%s%d cerrados · %d faltan en Sura · %d por revisar · %d errores (de %d).',
                $simular ? '[SIMULACIÓN] ' : '',
                $resultado['cerrados'], $resultado['faltan'], $resultado['revisar'], $resultado['errores'], $resultado['total']
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
