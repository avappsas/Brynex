<?php

namespace App\Console\Commands;

use App\Models\Radicado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-sincroniza el estado de los radicados desde las BD legacy.
 *
 * La migración es un snapshot: los aliados que siguen operando en el sistema
 * viejo actualizan allá el estado del trámite (columnas Radicado_EPS/ARL/Caja/
 * Pension de Contratos, que guardan el ESTADO y no un número — ver
 * NormalizarRadicadosLegacy) y BryNex se queda con el dato del día que se migró.
 *
 * REGLA DE NEGOCIO (definida por el dueño, ago-2026): el estado solo avanza.
 * Si el legacy está en `ok`, BryNex pasa a `ok`; pero si BryNex ya está en `ok`
 * y el legacy va atrás, manda BryNex y no se retrocede — desde la migración
 * también se gestiona dentro de BryNex y ese trabajo no debe pisarse.
 *
 * `--forzar` rompe esa regla y deja al legacy como fuente única de verdad;
 * existe solo como escape hatch, no es el uso normal del comando.
 */
class SincronizarRadicadosLegacy extends Command
{
    protected $signature = 'radicados:sincronizar-legacy
                            {--aliado= : Limitar a un aliado_id (por defecto, todos los migrados)}
                            {--cedula= : Limitar a una cédula}
                            {--forzar : Aplicar también los retrocesos (legacy manda)}
                            {--dry-run : Solo muestra lo que haría, sin escribir}';

    protected $description = 'Actualiza el estado de los radicados con el dato actual de las BD legacy (solo avances)';

    /** BD legacy → aliado_id en BryNex. */
    private const MAPA_BD = [
        'Brygar_BD'       => 2,
        'GiMave_Integral' => 4,
        'Grupo_Fecop'     => 6,
        'Mave_Anderson'   => 7,
        'SS_Faga'         => 8,
        'LuisLopez'       => 9,
    ];

    /** Columna legacy por tipo de radicado. */
    private const COLUMNAS = [
        'eps'     => 'Radicado_EPS',
        'arl'     => 'Radicado_ARL',
        'caja'    => 'Radicado_Caja',
        'pension' => 'Radicado_Pension',
    ];

    /**
     * Progresión del trámite. Un estado solo avanza hacia un rango mayor.
     * `error` y `traslado` son incidencias, no etapas: se dejan fuera de la
     * comparación de avance y solo se tocan con --forzar.
     */
    private const RANGO = [
        Radicado::ESTADO_PENDIENTE => 1,
        Radicado::ESTADO_TRAMITE   => 2,
        Radicado::ESTADO_OK        => 3,
    ];

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $forzar   = (bool) $this->option('forzar');
        $aliadoOp = $this->option('aliado');
        $cedulaOp = $this->option('cedula');

        $cambios   = [];   // filas a actualizar
        $omitidos  = [];   // retrocesos no aplicados
        $sinFila   = 0;

        foreach (self::MAPA_BD as $db => $aliadoId) {
            if ($aliadoOp && (int) $aliadoOp !== $aliadoId) continue;

            try {
                DB::connection('sqlsrv_legacy')->selectOne("SELECT TOP 1 1 x FROM [$db].dbo.Contratos");
            } catch (\Throwable $e) {
                $this->warn("  ⚠ $db no accesible, se omite");
                continue;
            }

            // Contratos vigentes migrados de este aliado: [id_legacy => id_brynex]
            $contratos = DB::table('contratos')
                ->where('aliado_id', $aliadoId)
                ->where('estado', 'vigente')
                ->whereNotNull('id_legacy')
                ->when($cedulaOp, fn ($q) => $q->where('cedula', $cedulaOp))
                ->pluck('id', 'id_legacy');

            if ($contratos->isEmpty()) continue;

            // Radicados de afiliación actuales: [contrato_id][tipo] => [id, estado]
            $rads = [];
            DB::table('radicados')
                ->where('aliado_id', $aliadoId)
                ->whereNull('incapacidad_id')
                ->whereIn('contrato_id', $contratos->values())
                ->select('id', 'contrato_id', 'tipo', 'estado')
                ->orderBy('id')
                ->chunk(5000, function ($filas) use (&$rads) {
                    foreach ($filas as $f) $rads[$f->contrato_id][$f->tipo] = $f;
                });

            foreach ($contratos->chunk(2000) as $lote) {
                $ids  = implode(',', $lote->keys()->all());
                $cols = implode(', ', self::COLUMNAS);
                $rows = DB::connection('sqlsrv_legacy')
                    ->select("SELECT Id, Cedula, $cols FROM [$db].dbo.Contratos WHERE Id IN ($ids)");

                foreach ($rows as $r) {
                    $contratoId = $contratos[$r->Id] ?? null;
                    if (!$contratoId) continue;

                    foreach (self::COLUMNAS as $tipo => $col) {
                        $esperado = NormalizarRadicadosLegacy::estadoDesdeLegacy($r->$col ?? '');
                        if ($esperado === null) continue;      // legacy dice "no aplica"

                        $fila = $rads[$contratoId][$tipo] ?? null;
                        if (!$fila) { $sinFila++; continue; }  // no se crean radicados aquí
                        if ($fila->estado === $esperado) continue;

                        $rangoActual   = self::RANGO[$fila->estado] ?? null;
                        $rangoEsperado = self::RANGO[$esperado]     ?? null;
                        $esAvance      = $rangoActual !== null
                                      && $rangoEsperado !== null
                                      && $rangoEsperado > $rangoActual;

                        $registro = [
                            'id'      => $fila->id,
                            'cedula'  => $r->Cedula,
                            'tipo'    => $tipo,
                            'de'      => $fila->estado,
                            'a'       => $esperado,
                            'aliado'  => $aliadoId,
                        ];

                        if ($esAvance || $forzar) {
                            $cambios[] = $registro;
                        } else {
                            $omitidos[] = $registro;
                        }
                    }
                }
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Cambios a aplicar: ' . count($cambios)
            . ' | omitidos por retroceso: ' . count($omitidos)
            . ' | sin radicado en BryNex: ' . $sinFila);

        if ($cambios) {
            $this->newLine();
            $this->line('  TRANSICIONES:');
            $resumen = [];
            foreach ($cambios as $c) {
                $k = "{$c['de']} → {$c['a']}";
                $resumen[$k] = ($resumen[$k] ?? 0) + 1;
            }
            arsort($resumen);
            foreach ($resumen as $k => $n) $this->line(sprintf('    %-24s %5d', $k, $n));

            $this->newLine();
            $this->line('  DETALLE:');
            foreach ($cambios as $c) {
                $this->line(sprintf('    aliado %-3d ced=%-12s %-8s %s → %s',
                    $c['aliado'], $c['cedula'], $c['tipo'], $c['de'], $c['a']));
            }
        }

        if ($omitidos) {
            $this->newLine();
            $this->line('  CONSERVADOS (BryNex va más adelante — manda BryNex, no se retrocede):');
            foreach ($omitidos as $c) {
                $this->line(sprintf('    aliado %-3d ced=%-12s %-8s brynex=%s legacy=%s',
                    $c['aliado'], $c['cedula'], $c['tipo'], $c['de'], $c['a']));
            }
        }

        if (!$cambios) {
            $this->newLine();
            $this->info('Nada que sincronizar.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->newLine();
            $this->comment('Dry-run: no se escribió nada. Quita --dry-run para aplicar.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Aplicar estos cambios sobre la BD?', false)) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        $aplicados = 0;
        DB::transaction(function () use ($cambios, &$aplicados) {
            foreach (collect($cambios)->groupBy('a') as $estado => $grupo) {
                foreach ($grupo->pluck('id')->chunk(1000) as $ids) {
                    $aplicados += DB::table('radicados')
                        ->whereIn('id', $ids)
                        ->update(['estado' => $estado, 'updated_at' => now()]);
                }
            }
        });

        $this->newLine();
        $this->info("✅ Radicados sincronizados: {$aplicados}");

        return self::SUCCESS;
    }
}
