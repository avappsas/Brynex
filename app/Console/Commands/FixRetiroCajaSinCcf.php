<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige facturas de retiro (numero_factura=0) donde v_caja quedó en 0
 * por el bug del cargo sin-CCF que no estaba en el bloque retirar().
 *
 * Aplica SOLO a:
 *   - facturas con tipo='planilla' y numero_factura=0  (retiros reales con días)
 *   - v_caja = 0 actualmente
 *   - contrato con tipo_modalidad_id IN (0, 12)        (Dependiente E / Ingreso-Retiro)
 *   - plan SIN caja (incluye_caja = 0/false)           (o sin plan → caja_id IS NULL)
 *
 * Ejecutar:
 *   php artisan brynex:fix-retiro-caja-sin-ccf           → modo DRY-RUN (solo lista)
 *   php artisan brynex:fix-retiro-caja-sin-ccf --commit  → aplica los cambios
 */
class FixRetiroCajaSinCcf extends Command
{
    protected $signature   = 'brynex:fix-retiro-caja-sin-ccf {--commit : Aplicar cambios reales (sin esta opción es DRY-RUN)}';
    protected $description = 'Corrige v_caja=0 en facturas de retiro sin-CCF (modalidades 0/12 sin plan de caja)';

    public function handle(): int
    {
        $commit = $this->option('commit');

        $this->info($commit
            ? '⚠️  Modo COMMIT — los cambios se guardarán en BD.'
            : '🔍 Modo DRY-RUN — solo listado, sin modificar nada. Usa --commit para aplicar.'
        );

        // ── Buscar facturas afectadas ────────────────────────────────────────
        $afectadas = DB::table('facturas AS f')
            ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
            ->leftJoin('planes_contrato AS pl', 'pl.id', '=', 'c.plan_id')
            ->whereNull('f.deleted_at')
            ->where('f.numero_factura', 0)          // retiro
            ->where('f.tipo', 'planilla')
            ->where('f.v_caja', 0)                  // bug: debería ser 100
            ->where('f.dias_cotizados', '>', 0)     // retiro real (no informativo)
            ->whereIn('c.tipo_modalidad_id', [0, 12]) // Dependiente E / Ingreso-Retiro
            ->where(function ($q) {
                // Plan sin caja  ─ O ─  sin plan y sin caja_id en el contrato
                $q->where('pl.incluye_caja', false)
                  ->orWhere(function ($q2) {
                      $q2->whereNull('c.plan_id')->whereNull('c.caja_id');
                  });
            })
            ->select([
                'f.id          AS factura_id',
                'f.cedula',
                'f.v_caja',
                'f.total_ss',
                'f.dias_cotizados',
                'c.id          AS contrato_id',
                'c.tipo_modalidad_id',
                'c.plan_id',
                'pl.incluye_caja',
            ])
            ->get();

        if ($afectadas->isEmpty()) {
            $this->info('✅ No se encontraron facturas afectadas. Nada que corregir.');
            return 0;
        }

        $this->table(
            ['factura_id', 'cedula', 'modalidad', 'plan_id', 'incluye_caja', 'v_caja_actual', 'total_ss_actual', 'dias'],
            $afectadas->map(fn($r) => [
                $r->factura_id,
                $r->cedula,
                $r->tipo_modalidad_id,
                $r->plan_id ?? '(sin plan)',
                $r->incluye_caja ?? 'null',
                $r->v_caja,
                $r->total_ss,
                $r->dias_cotizados,
            ])->toArray()
        );

        $this->line('');
        $this->line("📋 Total: {$afectadas->count()} factura(s) con v_caja=0 que deberían ser 100.");

        if (!$commit) {
            $this->warn('⚠️  DRY-RUN: ejecuta con --commit para aplicar los cambios.');
            return 0;
        }

        // ── Aplicar corrección ───────────────────────────────────────────────
        if (!$this->confirm("¿Aplicar la corrección a {$afectadas->count()} factura(s)?")) {
            $this->info('Operación cancelada.');
            return 0;
        }

        $ids       = $afectadas->pluck('factura_id')->toArray();
        $corregidas = 0;

        DB::transaction(function () use ($ids, &$corregidas) {
            foreach ($ids as $facturaId) {
                $updated = DB::table('facturas')
                    ->where('id', $facturaId)
                    ->where('v_caja', 0)            // doble check: solo si sigue en 0
                    ->whereNull('deleted_at')
                    ->update([
                        'v_caja'   => 100,
                        'total_ss' => DB::raw('total_ss + 100'),
                        // total no se toca: el retiro tiene total=0 (no cobra al cliente)
                    ]);

                if ($updated) {
                    $corregidas++;
                }
            }
        });

        $this->info("✅ Corrección aplicada: {$corregidas} factura(s) actualizadas.");
        $this->info('   v_caja: 0 → 100  |  total_ss: +100');
        $this->line('   (El campo "total" de la factura no se modificó: en retiro siempre es $0)');

        return 0;
    }
}
