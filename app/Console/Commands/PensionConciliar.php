<?php

namespace App\Console\Commands;

use App\Services\Pension\PensionConciliacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cierra contra el RUAF los radicados de pensión.
 *
 * Proceso aparte y no job, por lo mismo que `eps:conciliar-sura`: la cola es
 * `database` con retry_after de 90 s y esto tarda unos 2 s por persona. El botón
 * de Afiliaciones lo lanza en segundo plano y lee el progreso de la caché.
 */
class PensionConciliar extends Command
{
    protected $signature = 'pension:conciliar
                            {--aliado=2 : Aliado cuyos radicados se revisan}
                            {--nit= : Solo esta empresa}
                            {--usuario= : Usuario de BryNex al que se atribuyen los cambios}
                            {--incluir-ok : También revisa los que ya están en OK, para detectar traslados de fondo}
                            {--pausa=250 : Milisegundos entre consultas al operador}
                            {--simular : Consulta el RUAF pero no toca ningún radicado}';

    protected $description = 'Cierra los radicados de pensión que el RUAF confirma y marca los traslados de fondo';

    private const DURACION_CANDADO = 7200;

    public static function claveEstado(int $aliadoId): string
    {
        return "pension_conciliacion:estado:{$aliadoId}";
    }

    public static function claveCandado(int $aliadoId): string
    {
        return "pension_conciliacion:candado:{$aliadoId}";
    }

    public function handle(PensionConciliacionService $servicio): int
    {
        $aliadoId = (int) $this->option('aliado');
        $usuarioId = $this->option('usuario') ? (int) $this->option('usuario') : null;
        $simular = (bool) $this->option('simular');
        $incluirOk = (bool) $this->option('incluir-ok');

        $candado = Cache::lock(self::claveCandado($aliadoId), self::DURACION_CANDADO);

        if (! $candado->get()) {
            $this->error("Ya hay una conciliación de pensión corriendo para el aliado {$aliadoId}.");

            return self::FAILURE;
        }

        $estado = ['corriendo' => true, 'inicio' => now()->toIso8601String(), 'mensaje' => 'Buscando radicados de pensión…', 'detalle' => [], 'simulado' => $simular];
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
                $incluirOk,
                (int) $this->option('pausa'),
            );

            Cache::put(self::claveEstado($aliadoId), $resultado + [
                'corriendo' => false,
                'inicio' => $estado['inicio'],
                'fin' => now()->toIso8601String(),
                'mensaje' => 'Terminado.',
            ], now()->addDay());

            $this->newLine();
            $this->table(
                ['Radicado', 'Cédula', 'Nombre', 'Empresa', 'Acción', 'Detalle'],
                collect($resultado['detalle'])->map(fn ($f) => [
                    $f['radicado_id'], $f['cedula'], $f['nombre'], $f['empresa'], $f['accion'], $f['mensaje'],
                ])->all()
            );
            $this->info(sprintf(
                '%s%d a OK · %d ya confirmados · %d sin fondo · %d por revisar · %d errores (de %d radicados, %d personas).',
                $simular ? '[SIMULACIÓN] ' : '',
                $resultado['cerrados'], $resultado['sin_cambio'], $resultado['faltan'],
                $resultado['revisar'], $resultado['errores'], $resultado['total'], $resultado['personas']
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            Cache::put(self::claveEstado($aliadoId), $estado + [
                'corriendo' => false,
                'fin' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], now()->addDay());
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $candado->release();
        }
    }
}
