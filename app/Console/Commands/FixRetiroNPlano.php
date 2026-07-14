<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige facturas de retiro (numero_factura=0) que tienen n_plano NULL o 0
 * pero cuyo plano asociado en la tabla `planos` sí tiene n_plano > 0.
 *
 * Causa del bug: ContratoController::retirar() calculaba $nPlano después de
 * Factura::create(), por lo que la factura quedaba sin n_plano asignado mientras
 * el plano (planos.*) sí lo tenía correcto.
 *
 * Uso:
 *   php artisan brynex:fix-retiro-n-plano                → DRY-RUN (solo lista afectados)
 *   php artisan brynex:fix-retiro-n-plano --commit       → aplica los cambios
 *   php artisan brynex:fix-retiro-n-plano --aliado=5     → filtra por aliado específico
 */
class FixRetiroNPlano extends Command
{
    protected $signature = 'brynex:fix-retiro-n-plano
                            {--commit    : Aplicar los cambios reales (sin esta opción es DRY-RUN)}
                            {--aliado=   : Filtrar por aliado_id específico (opcional)}';

    protected $description = 'Corrige facturas de retiro (numero_factura=0) con n_plano NULL copiando el n_plano del plano asociado.';

    public function handle(): int
    {
        $commit   = $this->option('commit');
        $aliadoId = $this->option('aliado') ? (int) $this->option('aliado') : null;

        $this->line('');
        $this->info('══════════════════════════════════════════════════════');
        $this->info('  brynex:fix-retiro-n-plano' . ($commit ? ' [COMMIT]' : ' [DRY-RUN]'));
        $this->info('══════════════════════════════════════════════════════');

        if (!$commit) {
            $this->warn('  Modo DRY-RUN: no se realizarán cambios en la BD.');
            $this->warn('  Use --commit para aplicar los cambios.');
        }
        $this->line('');

        // ── Query de diagnóstico ─────────────────────────────────────────────
        // Facturas de retiro (numero_factura=0) con n_plano NULL o 0
        // que tienen un plano asociado con n_plano > 0
        $query = DB::table('facturas AS f')
            ->join('planos AS p', function ($join) {
                $join->on('p.factura_id', '=', 'f.id')
                     ->whereNull('p.deleted_at');
            })
            ->whereNull('f.deleted_at')
            ->where('f.numero_factura', 0)            // factura de retiro inicial
            ->where('p.num_dias', '>', 0)             // retiro real (con días cotizados)
            ->where('p.tipo_reg', 'retiro')           // confirmar que es plano de retiro
            ->where('p.n_plano', '>', 0)              // el plano SÍ tiene n_plano correcto
            ->where(function ($q) {
                // la factura NO tiene n_plano (o es 0 / null)
                $q->whereNull('f.n_plano')
                  ->orWhere('f.n_plano', 0);
            })
            ->when($aliadoId, fn ($q) => $q->where('f.aliado_id', $aliadoId))
            ->select([
                'f.id AS factura_id',
                'f.aliado_id',
                'f.cedula',
                'f.mes',
                'f.anio',
                'f.n_plano AS f_n_plano',
                'f.razon_social_id',
                'p.id AS plano_id',
                'p.n_plano AS p_n_plano',
                'p.primer_nombre',
                'p.primer_ape',
            ])
            ->orderBy('f.aliado_id')
            ->orderBy('f.anio')
            ->orderBy('f.mes')
            ->get();

        $total = $query->count();

        if ($total === 0) {
            $this->info('✅ No se encontraron facturas de retiro con n_plano sin asignar.');
            return 0;
        }

        // ── Mostrar tabla de afectados ───────────────────────────────────────
        $this->warn("⚠️  Se encontraron {$total} factura(s) de retiro con n_plano=NULL/0:");
        $this->line('');

        $headers = ['factura_id', 'aliado_id', 'cédula', 'nombre', 'mes', 'año', 'n_plano_factura', 'n_plano_plano', 'plano_id'];
        $rows = $query->map(fn ($r) => [
            $r->factura_id,
            $r->aliado_id,
            $r->cedula,
            trim($r->primer_nombre . ' ' . $r->primer_ape),
            $r->mes,
            $r->anio,
            $r->f_n_plano ?? 'NULL',
            $r->p_n_plano,
            $r->plano_id,
        ])->toArray();

        $this->table($headers, $rows);
        $this->line('');

        if (!$commit) {
            $this->info("ℹ️  Total afectados: {$total} factura(s).");
            $this->info("   Ejecute con --commit para corregirlos.");
            return 0;
        }

        // ── Aplicar corrección ───────────────────────────────────────────────
        $this->info("🔧 Aplicando corrección a {$total} factura(s)...");
        $this->line('');

        $corregidas = 0;
        $errores    = 0;

        foreach ($query as $row) {
            try {
                $actualizado = DB::table('facturas')
                    ->where('id', $row->factura_id)
                    ->whereNull('deleted_at')
                    ->where('numero_factura', 0)
                    ->where(function ($q) {
                        $q->whereNull('n_plano')->orWhere('n_plano', 0);
                    })
                    ->update(['n_plano' => $row->p_n_plano]);

                if ($actualizado) {
                    $corregidas++;
                    $this->line("  ✅ factura_id={$row->factura_id} | {$row->cedula} | {$row->mes}/{$row->anio} → n_plano={$row->p_n_plano}");
                } else {
                    $this->warn("  ⚠️  factura_id={$row->factura_id} | Sin cambios (ya corregida o condición no cumplida)");
                }
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  ❌ factura_id={$row->factura_id} | Error: " . $e->getMessage());
            }
        }

        $this->line('');
        $this->info("══════════════════════════════════════════════════════");
        $this->info("  Resultado: {$corregidas} corregida(s) | {$errores} error(es)");
        $this->info("══════════════════════════════════════════════════════");
        $this->line('');

        return $errores > 0 ? 1 : 0;
    }
}
