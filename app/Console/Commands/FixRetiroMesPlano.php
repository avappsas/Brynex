<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige planos de retiro (tipo_reg='retiro') de dependientes (tipo_modalidad_id != 11)
 * cuyo mes_plano quedó en (f.mes - 2) en lugar de (f.mes - 1).
 *
 * Causa: ContratoController::retirar() restaba 1 extra al mes_plano,
 * cuando validated['mes_plano'] ya era el mes de cotización (vencido).
 *
 * Regla correcta: plano.mes_plano = factura.mes - 1
 *
 * Uso:
 *   php artisan brynex:fix-retiro-mes-plano               → DRY-RUN
 *   php artisan brynex:fix-retiro-mes-plano --commit      → aplica cambios
 */
class FixRetiroMesPlano extends Command
{
    protected $signature = 'brynex:fix-retiro-mes-plano
                            {--commit : Aplicar cambios reales (sin esta opción es DRY-RUN)}';

    protected $description = 'Corrige mes_plano en planos de retiro de dependientes que quedaron desplazados 1 mes.';

    public function handle(): int
    {
        $commit = $this->option('commit');

        $this->line('');
        $this->info('══════════════════════════════════════════════════════');
        $this->info('  brynex:fix-retiro-mes-plano' . ($commit ? ' [COMMIT]' : ' [DRY-RUN]'));
        $this->info('══════════════════════════════════════════════════════');

        if (!$commit) {
            $this->warn('  Modo DRY-RUN: no se realizarán cambios.');
        }

        // Planos de retiro de dependientes cuyo mes_plano != f.mes - 1
        // Solo facturas de retiro inicial (numero_factura=0, no cobradas en lote)
        $afectados = DB::table('planos as p')
            ->join('facturas as f', 'f.id', '=', 'p.factura_id')
            ->where('p.tipo_reg', 'retiro')
            ->where('p.num_dias', '>', 0)
            ->where('p.tipo_modalidad_id', '<>', 11)   // no-independientes
            ->whereNull('p.deleted_at')
            ->whereNull('f.deleted_at')
            ->where('f.numero_factura', 0)              // factura inicial (no lote)
            ->whereRaw('p.mes_plano <> (CASE WHEN f.mes = 1 THEN 12 ELSE f.mes - 1 END)')
            ->select(
                'p.id',
                'p.no_identifi',
                'p.mes_plano',
                'p.anio_plano',
                'p.n_plano',
                'p.razon_social_id',
                'f.mes as f_mes',
                'f.anio as f_anio'
            )
            ->get();

        $total = $afectados->count();

        if ($total === 0) {
            $this->info('✅ No se encontraron planos de retiro con mes_plano incorrecto.');
            return 0;
        }

        $this->warn("⚠️  Se encontraron {$total} plano(s) de retiro con mes_plano desplazado:");
        $this->line('');

        $headers = ['plano_id', 'RS_id', 'cédula', 'mes_plano_actual', 'mes_plano_correcto', 'f_mes/anio', 'n_plano'];
        $rows = $afectados->map(function ($r) {
            $correcto = $r->f_mes == 1 ? 12 : $r->f_mes - 1;
            return [
                $r->id,
                $r->razon_social_id,
                $r->no_identifi,
                $r->mes_plano . '/' . $r->anio_plano,
                $correcto . '/' . $r->anio_plano,
                $r->f_mes . '/' . $r->f_anio,
                $r->n_plano,
            ];
        })->toArray();

        $this->table($headers, $rows);
        $this->line('');

        if (!$commit) {
            $this->info("ℹ️  Total afectados: {$total} plano(s). Use --commit para corregirlos.");
            return 0;
        }

        // Aplicar corrección
        $corregidos = 0;
        $errores    = 0;

        foreach ($afectados as $r) {
            $correcto = $r->f_mes == 1 ? 12 : $r->f_mes - 1;
            try {
                DB::table('planos')
                    ->where('id', $r->id)
                    ->whereNull('deleted_at')
                    ->update(['mes_plano' => $correcto]);

                $corregidos++;
                $this->line("  ✅ plano_id={$r->id} | {$r->no_identifi} | RS={$r->razon_social_id} | mes_plano {$r->mes_plano}/{$r->anio_plano} → {$correcto}/{$r->anio_plano}");
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  ❌ plano_id={$r->id} | Error: " . $e->getMessage());
            }
        }

        $this->line('');
        $this->info("══════════════════════════════════════════════════════");
        $this->info("  Resultado: {$corregidos} corregido(s) | {$errores} error(es)");
        $this->info("══════════════════════════════════════════════════════");
        $this->line('');

        return $errores > 0 ? 1 : 0;
    }
}
