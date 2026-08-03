<?php

namespace App\Console\Commands;

use App\Models\Radicado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige los radicados migrados desde legacy.
 *
 * En las BD legacy, las columnas Radicado_EPS / Radicado_ARL / Radicado_Caja /
 * Radicado_Pension de la tabla Contratos NO guardaban un número de radicado:
 * guardaban el estado del trámite ('OK', 'TRAMITE', 'PENT', 'ERROR', '-'...).
 *
 * La migración copió ese texto a `numero_radicado` y puso `estado = 'confirmado'`
 * en todos los registros — un valor que no existe en Radicado::todosEstados(),
 * por lo que la UI de afiliaciones los pinta como "❓C".
 *
 * Este comando traduce ese texto al estado real, limpia `numero_radicado`
 * (ninguno de los valores legacy contiene dígitos) y borra los radicados que
 * en legacy significaban "no aplica" ('-', '--', 'N/A', 'NO').
 */
class NormalizarRadicadosLegacy extends Command
{
    protected $signature = 'radicados:normalizar-legacy
                            {--aliado= : Limitar a un aliado_id (por defecto, todos)}
                            {--dry-run : Solo muestra lo que haría, sin escribir}';

    protected $description = 'Traduce el estado legacy guardado en numero_radicado al estado real del radicado';

    /**
     * Estado legacy (normalizado) → estado BryNex. `null` = no aplica esa entidad
     * (aquí se borra el radicado; en la migración, no se inserta).
     *
     * Lo usan también MigrateLegacy y MigrateLegacyAliado vía estadoDesdeLegacy().
     */
    public const MAPA = [
        // Afiliación confirmada
        'OK'        => Radicado::ESTADO_OK,
        'OKE'       => Radicado::ESTADO_OK,
        'OKO'       => Radicado::ESTADO_OK,
        'UK'        => Radicado::ESTADO_OK,
        '-OK-'      => Radicado::ESTADO_OK,
        'NUEVO_OK'  => Radicado::ESTADO_OK,
        'NUEVO'     => Radicado::ESTADO_OK,
        'NUEVA'     => Radicado::ESTADO_OK,
        'SI'        => Radicado::ESTADO_OK,
        'ENVIADO'   => Radicado::ESTADO_OK,

        // En trámite (incluye los typos encontrados en producción)
        'TRAMITE'   => Radicado::ESTADO_TRAMITE,
        'TRAMII'    => Radicado::ESTADO_TRAMITE,
        'TRAMI'     => Radicado::ESTADO_TRAMITE,
        'TRAMTE'    => Radicado::ESTADO_TRAMITE,
        'TRAMITT'   => Radicado::ESTADO_TRAMITE,
        'TYRAMITE'  => Radicado::ESTADO_TRAMITE,
        'TREMITE'   => Radicado::ESTADO_TRAMITE,
        '--T'       => Radicado::ESTADO_TRAMITE,
        'ENVIAR'    => Radicado::ESTADO_TRAMITE,

        // Pendiente
        'PENT'      => Radicado::ESTADO_PENDIENTE,
        'PENDT'     => Radicado::ESTADO_PENDIENTE,
        'PENTE'     => Radicado::ESTADO_PENDIENTE,
        'PEND'      => Radicado::ESTADO_PENDIENTE,
        'PTE'       => Radicado::ESTADO_PENDIENTE,

        'ERROR'     => Radicado::ESTADO_ERROR,
        'TRASLADO'  => Radicado::ESTADO_TRASLADO,

        // No aplicaba esa entidad → se borra el radicado.
        // La vista ya resuelve el caso: muestra "–" si el plan no incluye la
        // entidad, o "⏳P" si sí la incluye pero no hay radicado.
        '-'         => null,
        '--'        => null,
        '---'       => null,
        '----'      => null,
        '*'         => null,
        'N/A'       => null,
        'NO'        => null,
    ];

    /** Estado inválido que dejó la migración y que este comando corrige. */
    private const ESTADO_LEGACY = 'confirmado';

    /**
     * Traduce el texto legacy de Radicado_EPS/ARL/Caja/Pension al estado BryNex.
     *
     * Devuelve null cuando el valor significaba "no aplica" ('-', 'N/A', 'NO')
     * o cuando es un valor desconocido: en ambos casos no debe crearse radicado.
     */
    public static function estadoDesdeLegacy(?string $texto): ?string
    {
        $clave = strtoupper(trim((string) $texto));

        return $clave === '' ? null : (self::MAPA[$clave] ?? null);
    }

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $aliadoId = $this->option('aliado');

        $base = fn () => DB::table('radicados')
            ->where('estado', self::ESTADO_LEGACY)
            ->when($aliadoId, fn ($q) => $q->where('aliado_id', $aliadoId));

        $total = $base()->count();
        if ($total === 0) {
            $this->info('No hay radicados con estado "' . self::ESTADO_LEGACY . '" para corregir.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Radicados a normalizar: {$total}"
            . ($aliadoId ? " (aliado_id={$aliadoId})" : ' (todos los aliados)'));

        // Agrupar por el texto legacy para hacer un UPDATE por valor, no por fila.
        $valores = $base()
            ->select(DB::raw('UPPER(LTRIM(RTRIM(numero_radicado))) AS v'), DB::raw('COUNT(*) AS n'))
            ->groupBy(DB::raw('UPPER(LTRIM(RTRIM(numero_radicado)))'))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $resumen      = [];
        $aBorrar      = 0;
        $sinMapear    = [];

        $this->newLine();
        $this->line(sprintf('  %-14s %-12s %8s', 'LEGACY', '→ ESTADO', 'FILAS'));
        $this->line('  ' . str_repeat('─', 36));

        foreach ($valores as $fila) {
            $legacy = $fila->v;

            if (!array_key_exists($legacy, self::MAPA)) {
                $sinMapear[$legacy] = $fila->n;
                $this->line(sprintf('  %-14s %-12s %8d', $legacy, '(sin mapear)', $fila->n));
                continue;
            }

            $destino = self::MAPA[$legacy];
            $this->line(sprintf('  %-14s %-12s %8d', $legacy, $destino ?? 'BORRAR', $fila->n));

            if ($destino === null) {
                $aBorrar += $fila->n;
            } else {
                $resumen[$destino] = ($resumen[$destino] ?? 0) + $fila->n;
            }
        }

        $this->newLine();
        foreach ($resumen as $estado => $n) {
            $this->line("  → {$estado}: {$n}");
        }
        $this->line("  → borrados: {$aBorrar}");
        if ($sinMapear) {
            $this->warn('  ⚠ Sin mapear (se dejan intactos): ' . count($sinMapear) . ' valores distintos, '
                . array_sum($sinMapear) . ' filas');
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

        $this->newLine();
        $actualizados = 0;
        $borrados     = 0;

        DB::transaction(function () use ($base, &$actualizados, &$borrados) {
            foreach (self::MAPA as $legacy => $destino) {
                $filtro = fn () => $base()
                    ->whereRaw('UPPER(LTRIM(RTRIM(numero_radicado))) = ?', [$legacy]);

                if ($destino === null) {
                    // Sin movimientos asociados (verificado), pero se limpian por si acaso.
                    $ids = $filtro()->pluck('id');
                    if ($ids->isEmpty()) continue;

                    foreach ($ids->chunk(1000) as $lote) {
                        DB::table('radicado_movimientos')->whereIn('radicado_id', $lote)->delete();
                        $borrados += DB::table('radicados')->whereIn('id', $lote)->delete();
                    }
                    continue;
                }

                // numero_radicado se limpia: ninguno de los valores legacy es un
                // radicado real (0 valores con dígitos en la corrida de ago-2026).
                $actualizados += $filtro()->update([
                    'estado'          => $destino,
                    'numero_radicado' => null,
                    'updated_at'      => now(),
                ]);
            }
        });

        $this->info("✅ Actualizados: {$actualizados}   Borrados: {$borrados}");

        $restantes = DB::table('radicados')
            ->where('estado', self::ESTADO_LEGACY)
            ->when($aliadoId, fn ($q) => $q->where('aliado_id', $aliadoId))
            ->count();
        if ($restantes > 0) {
            $this->warn("⚠ Quedan {$restantes} radicados en '" . self::ESTADO_LEGACY . "' (valores sin mapear).");
        }

        $this->newLine();
        $this->line('Distribución final de estados:');
        DB::table('radicados')
            ->select('estado', DB::raw('COUNT(*) AS n'))
            ->when($aliadoId, fn ($q) => $q->where('aliado_id', $aliadoId))
            ->groupBy('estado')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->each(fn ($r) => $this->line(sprintf('  %-12s %8d', $r->estado, $r->n)));

        return self::SUCCESS;
    }
}
