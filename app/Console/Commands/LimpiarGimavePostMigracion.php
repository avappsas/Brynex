<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deja BryNex-GiMave tal como quedó al terminar la migración del legacy:
 * borra todo lo que nació en BryNex (id_legacy IS NULL) para que el
 * re-migrate pueda traer esos mismos registros desde GiMave_Integral sin
 * chocar con duplicados.
 *
 * Criterio de borrado: aliado_id = 4 AND id_legacy IS NULL.
 * NO se usa created_at: los inserts de la migración guardaron now() en
 * ~58.685 facturas, 16.881 contratos, 10.419 clientes y 111.069 planos,
 * así que filtrar por fecha borraría la migración entera.
 *
 * Excepción explícita: razones_sociales #11272 se conserva (tiene 35
 * contratos migrados colgando). Solo se borra la #11275, que está vacía.
 *
 * Por defecto corre en DRY-RUN. Para borrar de verdad: --ejecutar
 */
class LimpiarGimavePostMigracion extends Command
{
    protected $signature = 'legacy:limpiar-gimave
                            {--ejecutar : Ejecuta el borrado real (por defecto solo simula)}
                            {--sin-respaldo : Omite las tablas de respaldo (NO recomendado)}';

    protected $description = 'Borra de BryNex los registros de GiMave creados después de la migración (id_legacy IS NULL)';

    private const ALIADO_ID = 4;

    /** Razones sociales sin id_legacy que NO se borran (tienen contratos migrados colgando). */
    private const RS_CONSERVAR = [11272];

    private bool $dry = true;
    private string $sufijoBkp;

    /** Resumen final: tabla => cantidad. */
    private array $resumen = [];

    public function handle(): int
    {
        $this->dry       = ! $this->option('ejecutar');
        $this->sufijoBkp = 'bkp_gim_' . now()->format('Ymd_His');

        $aliado = DB::table('aliados')->where('id', self::ALIADO_ID)->first(['id', 'nombre']);
        if (! $aliado) {
            $this->error('No existe el aliado ' . self::ALIADO_ID . '.');
            return self::FAILURE;
        }

        $this->info(str_repeat('═', 68));
        $this->info("  LIMPIEZA POST-MIGRACIÓN — {$aliado->nombre} (aliado_id {$aliado->id})");
        $this->info(str_repeat('═', 68));

        if ($this->dry) {
            $this->warn('  MODO SIMULACIÓN — no se modifica nada. Usá --ejecutar para borrar.');
        } else {
            $this->error('  ⚠  EJECUCIÓN REAL SOBRE PRODUCCIÓN');
        }
        $this->newLine();

        // ── 1. Identificar los registros raíz ────────────────────────────────
        $facturaIds  = $this->idsSinLegacy('facturas');
        $contratoIds = $this->idsSinLegacy('contratos');
        $planoIds    = $this->idsSinLegacy('planos');
        $tareaIds    = $this->idsSinLegacy('tareas');
        $bancoIds    = $this->idsSinLegacy('banco_cuentas');
        $rsIds       = array_values(array_diff($this->idsSinLegacy('razones_sociales'), self::RS_CONSERVAR));

        // Tablas que deberían estar vacías; si aparece algo, hay que revisarlo a mano.
        foreach (['clientes', 'empresas', 'users', 'gastos', 'incapacidades', 'asesores'] as $t) {
            $n = count($this->idsSinLegacy($t));
            if ($n > 0) {
                $this->warn("  ⚠  $t tiene $n registros sin id_legacy que este comando NO borra.");
                $this->warn("     Revisalos a mano antes de re-migrar; podrían duplicarse.");
            }
        }

        $this->line('  Registros raíz a borrar:');
        $this->line(sprintf('    facturas: %d   contratos: %d   planos: %d   tareas: %d   banco_cuentas: %d   razones_sociales: %d',
            count($facturaIds), count($contratoIds), count($planoIds), count($tareaIds), count($bancoIds), count($rsIds)));
        $this->line('    razones_sociales conservadas: ' . implode(', ', self::RS_CONSERVAR));
        $this->newLine();

        // ── 2. Chequeo de seguridad: dependientes migrados que quedarían rotos ─
        if (! $this->verificarHuerfanos($facturaIds, $contratoIds, $planoIds, $bancoIds, $rsIds)) {
            $this->error('  Abortado: hay registros MIGRADOS que dependen de lo que se iba a borrar.');
            $this->error('  Resolvelo antes de continuar (arriba está el detalle).');
            return self::FAILURE;
        }

        // ── 3. Confirmación ──────────────────────────────────────────────────
        if (! $this->dry && ! $this->confirm('¿Confirmás el borrado real en producción?', false)) {
            $this->info('  Cancelado.');
            return self::SUCCESS;
        }

        // ── 4. Borrado, hijos antes que padres ───────────────────────────────
        DB::beginTransaction();
        try {
            // Cuelgan de facturas
            $this->borrar('consignaciones', fn($q) => $q->whereIn('factura_id', $facturaIds), $facturaIds);
            $this->borrar('abonos',         fn($q) => $q->whereIn('factura_id', $facturaIds), $facturaIds);
            $this->borrar('anticipos',      fn($q) => $q->whereIn('factura_id', $facturaIds), $facturaIds);

            // Cuelgan de planos
            $this->borrar('planilla_envios_whatsapp_detalle', fn($q) => $q->whereIn('plano_id', $planoIds), $planoIds);

            // Planos antes que facturas (planos.factura_id → facturas.id)
            $this->borrar('planos',   fn($q) => $q->whereIn('id', $planoIds), $planoIds);
            $this->borrar('facturas', fn($q) => $q->whereIn('id', $facturaIds), $facturaIds);

            // Cuelgan de contratos
            $this->borrar('radicado_movimientos', fn($q) => $q->whereIn('contrato_id', $contratoIds), $contratoIds);
            $this->borrar('radicados',            fn($q) => $q->whereIn('contrato_id', $contratoIds), $contratoIds);
            $this->borrar('anticipos',            fn($q) => $q->whereIn('contrato_id', $contratoIds), $contratoIds);
            $this->borrar('contratos',            fn($q) => $q->whereIn('id', $contratoIds), $contratoIds);

            // Cuelgan de tareas
            $this->borrar('tarea_gestiones', fn($q) => $q->whereIn('tarea_id', $tareaIds), $tareaIds);
            $this->borrar('tareas',          fn($q) => $q->whereIn('id', $tareaIds), $tareaIds);

            // Cuentas bancarias y razones sociales
            $this->borrar('consignaciones', fn($q) => $q->whereIn('banco_cuenta_id', $bancoIds), $bancoIds);
            $this->borrar('banco_cuentas',  fn($q) => $q->whereIn('id', $bancoIds), $bancoIds);
            $this->borrar('razones_sociales', fn($q) => $q->whereIn('id', $rsIds), $rsIds);

            if ($this->dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  ✖ Error, se revirtió todo: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── 5. Resumen ───────────────────────────────────────────────────────
        $this->newLine();
        $this->info(str_repeat('─', 68));
        $this->info($this->dry ? '  SIMULACIÓN — se habrían borrado:' : '  BORRADO COMPLETADO:');
        $total = 0;
        foreach ($this->resumen as $tabla => $n) {
            if ($n === 0) continue;
            $this->line(sprintf('    %-38s %6d', $tabla, $n));
            $total += $n;
        }
        $this->info(sprintf('    %-38s %6d', 'TOTAL', $total));
        $this->info(str_repeat('─', 68));

        if ($this->dry) {
            $this->newLine();
            $this->line('  Para ejecutarlo de verdad:');
            $this->line('    php artisan legacy:limpiar-gimave --ejecutar');
        } else {
            $this->newLine();
            $this->line("  Respaldos guardados con sufijo: {$this->sufijoBkp}");
            $this->line('  Siguiente paso: php artisan legacy:migrate-aliado gimave');
        }

        return self::SUCCESS;
    }

    /** IDs del aliado sin id_legacy en la tabla dada. */
    private function idsSinLegacy(string $tabla): array
    {
        return DB::table($tabla)
            ->where('aliado_id', self::ALIADO_ID)
            ->whereNull('id_legacy')
            ->pluck('id')
            ->all();
    }

    /**
     * Verifica que no quede ningún registro MIGRADO apuntando a algo que se borra.
     *
     * Solo se chequean las tablas cuyos registros SOBREVIVEN al borrado.
     * consignaciones, abonos, anticipos, radicados, radicado_movimientos y
     * tarea_gestiones no entran acá: se borran completos en cascada junto con
     * su padre, así que por definición no pueden quedar huérfanos.
     */
    private function verificarHuerfanos(array $facturaIds, array $contratoIds, array $planoIds, array $bancoIds, array $rsIds): bool
    {
        $ok = true;
        $this->line('  Verificando que nada migrado quede apuntando a lo que se borra...');

        $checks = [
            // [tabla hija, columna FK, ids padre a borrar, ids de la hija que también se borran]
            ['planos',        'factura_id',      $facturaIds,  $planoIds],
            ['planos',        'contrato_id',     $contratoIds, $planoIds],
            ['facturas',      'contrato_id',     $contratoIds, $facturaIds],
            ['contratos',     'razon_social_id', $rsIds,       $contratoIds],
            ['incapacidades', 'contrato_id',     $contratoIds, []],
        ];

        foreach ($checks as [$hija, $fk, $padreIds, $exentos]) {
            if (empty($padreIds)) continue;

            // Si la columna no existe en esta BD, saltar el chequeo.
            $tieneCol = DB::selectOne(
                "SELECT COUNT(*) AS c FROM sys.columns WHERE object_id = OBJECT_ID(?) AND name = ?",
                [$hija, $fk]
            )->c;
            if (! $tieneCol) continue;

            $q = DB::table($hija)->whereIn($fk, $padreIds);
            if (! empty($exentos)) {
                $q->whereNotIn('id', $exentos);
            }
            $n = $q->count();

            if ($n > 0) {
                $this->error("    ✖ $hija.$fk: $n registros sobrevivirían apuntando a un padre borrado.");
                $ok = false;
            }
        }

        if ($ok) $this->line('    ✔ Sin dependencias rotas.');
        $this->newLine();

        return $ok;
    }

    /**
     * Respalda y borra. En dry-run solo cuenta.
     * El respaldo es un SELECT INTO a una tabla nueva en la misma BD.
     */
    private function borrar(string $tabla, \Closure $filtro, array $ids): void
    {
        if (empty($ids)) {
            $this->resumen[$tabla] = $this->resumen[$tabla] ?? 0;
            return;
        }

        $n = $filtro(DB::table($tabla))->count();
        $this->resumen[$tabla] = ($this->resumen[$tabla] ?? 0) + $n;

        if ($n === 0) return;

        if ($this->dry) {
            $this->line(sprintf('    [simulado] %-38s %6d', $tabla, $n));
            return;
        }

        if (! $this->option('sin-respaldo')) {
            $destino = "{$tabla}_{$this->sufijoBkp}";
            $sub     = $filtro(DB::table($tabla))->select('id');

            // consignaciones y anticipos se borran en dos pasadas (por factura y
            // por contrato/banco), así que la tabla de respaldo puede existir ya.
            $existe = DB::selectOne("SELECT OBJECT_ID(?) AS oid", [$destino])->oid !== null;

            $sql = $existe
                ? "INSERT INTO [$destino] SELECT * FROM [$tabla] WHERE id IN ({$sub->toSql()})"
                : "SELECT * INTO [$destino] FROM [$tabla] WHERE id IN ({$sub->toSql()})";

            DB::statement($sql, $sub->getBindings());
            $this->line("    ↳ respaldo: $destino ($n filas)" . ($existe ? ' [append]' : ''));
        }

        $borrados = $filtro(DB::table($tabla))->delete();
        $this->line(sprintf('    [borrado]  %-38s %6d', $tabla, $borrados));
    }
}
