<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retrocede el período (mes/anio) de las planillas de un cotizante.
 *
 * Contexto: al facturar, el sistema guarda como período el mes en que se cobra,
 * no el mes cotizado. Para los dependientes (modalidad 0) esa es la convención
 * general — 18.501 facturas del aliado 4 están así contra 96 en mes vencido —,
 * pero los independientes vencidos (modalidad 10) sí van al mes anterior. Cuando
 * un cotizante quedó registrado con el criterio equivocado, sus recibos muestran
 * "Jul 2026" para una planilla que en realidad cubre junio.
 *
 * Este comando corre las planillas N meses hacia atrás junto con sus planos PILA.
 * NO toca las afiliaciones: esas corresponden al mes de ingreso y ya están bien.
 *
 * Es un ajuste puntual y dirigido — se ejecuta por cédula, muestra el plan
 * completo antes de escribir y aborta si algún destino ya está ocupado por una
 * factura que no forma parte del mismo movimiento.
 *
 *   php artisan facturas:retroceder-periodo 16828686 --aliado=4            # dry-run
 *   php artisan facturas:retroceder-periodo 16828686 --aliado=4 --ejecutar # aplica
 */
class RetrocederPeriodoFacturas extends Command
{
    protected $signature = 'facturas:retroceder-periodo
                            {cedula : Cédula del cotizante a corregir}
                            {--aliado= : aliado_id propietario de las facturas (obligatorio)}
                            {--meses=1 : Cuántos meses retroceder}
                            {--contrato= : Limitar a un contrato_id específico}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo simula)}';

    protected $description = 'Retrocede el período de las planillas de una cédula (con sus planos PILA)';

    public function handle(): int
    {
        $cedula   = trim((string) $this->argument('cedula'));
        $aliadoId = (int) $this->option('aliado');
        $meses    = (int) $this->option('meses');
        $ejecutar = (bool) $this->option('ejecutar');

        if (!$aliadoId) {
            $this->error('Falta --aliado=. Las facturas se filtran siempre por aliado_id.');

            return self::FAILURE;
        }
        if ($meses < 1) {
            $this->error('--meses debe ser 1 o más.');

            return self::FAILURE;
        }

        $q = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->where('tipo', 'planilla')
            ->where('numero_factura', '>', 0)
            ->orderBy('anio')
            ->orderBy('mes');

        if ($this->option('contrato')) {
            $q->where('contrato_id', (int) $this->option('contrato'));
        }

        $facturas = $q->get();

        if ($facturas->isEmpty()) {
            $this->warn("No hay planillas activas para la cédula {$cedula} en el aliado {$aliadoId}.");

            return self::SUCCESS;
        }

        // ── Plan de movimiento ────────────────────────────────────────────────
        $plan     = [];
        $idsLote  = $facturas->pluck('id')->all();
        $colision = false;

        foreach ($facturas as $f) {
            [$mesDest, $anioDest] = $this->restarMeses((int) $f->mes, (int) $f->anio, $meses);

            // Un destino solo es conflictivo si lo ocupa una factura que NO se mueve
            // con este lote (las del propio lote liberan su casilla al desplazarse).
            $ocupado = Factura::where('aliado_id', $aliadoId)
                ->where('contrato_id', $f->contrato_id)
                ->where('tipo', 'planilla')
                ->where('mes', $mesDest)
                ->where('anio', $anioDest)
                ->where('numero_factura', '>', 0)
                ->whereNotIn('id', $idsLote)
                ->first(['id', 'numero_factura']);

            $planos = DB::table('planos')
                ->where('factura_id', $f->id)
                ->whereNull('deleted_at')
                ->count();

            if ($ocupado) {
                $colision = true;
            }

            $plan[] = [
                'factura'  => $f,
                'mesDest'  => $mesDest,
                'anioDest' => $anioDest,
                'planos'   => $planos,
                'ocupado'  => $ocupado,
            ];
        }

        $this->info(($ejecutar ? '▶ APLICANDO' : '🔍 SIMULACIÓN (dry-run)')
            . " — cédula {$cedula}, aliado {$aliadoId}, retroceso de {$meses} mes(es)");
        $this->newLine();

        $this->table(
            ['N° recibo', 'Contrato', 'Período actual', '→ Destino', 'Planos', 'Conflicto'],
            collect($plan)->map(fn ($p) => [
                $p['factura']->numero_factura,
                $p['factura']->contrato_id,
                sprintf('%02d/%d', $p['factura']->mes, $p['factura']->anio),
                sprintf('%02d/%d', $p['mesDest'], $p['anioDest']),
                $p['planos'],
                $p['ocupado'] ? "⚠ recibo #{$p['ocupado']->numero_factura}" : '',
            ])->all()
        );

        if ($colision) {
            $this->error('Abortado: hay destinos ocupados por facturas ajenas a este movimiento.');
            $this->line('Revise esos recibos antes de reintentar — el comando no sobreescribe períodos ocupados.');

            return self::FAILURE;
        }

        // Las afiliaciones quedan fuera a propósito: van en el mes de ingreso.
        $afiliaciones = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->where('tipo', 'afiliacion')
            ->count();
        if ($afiliaciones > 0) {
            $this->line("ℹ {$afiliaciones} afiliación(es) sin tocar — corresponden al mes de ingreso.");
        }

        if (!$ejecutar) {
            $this->newLine();
            $this->warn('Nada se escribió. Repita con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        // ── Aplicar ───────────────────────────────────────────────────────────
        $movidas = 0;
        $planosMovidos = 0;

        DB::transaction(function () use ($plan, $aliadoId, $meses, &$movidas, &$planosMovidos) {
            foreach ($plan as $p) {
                /** @var Factura $f */
                $f = $p['factura'];
                $origen = sprintf('%02d/%d', $f->mes, $f->anio);
                $destino = sprintf('%02d/%d', $p['mesDest'], $p['anioDest']);

                $f->mes  = $p['mesDest'];
                $f->anio = $p['anioDest'];
                $f->save();
                $movidas++;

                // Los planos PILA guardan su propio período
                $planosMovidos += DB::table('planos')
                    ->where('factura_id', $f->id)
                    ->whereNull('deleted_at')
                    ->update([
                        'mes_plano'  => $p['mesDest'],
                        'anio_plano' => $p['anioDest'],
                        'updated_at' => now(),
                    ]);

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Período retrocedido {$meses} mes(es): {$origen} → {$destino} "
                        . "(recibo #{$f->numero_factura}) por facturas:retroceder-periodo.",
                    detalle: [
                        'periodo_anterior' => $origen,
                        'periodo_nuevo'    => $destino,
                        'meses'            => $meses,
                    ],
                    alidoId: $aliadoId
                );
            }
        });

        $this->newLine();
        $this->info("✔ {$movidas} factura(s) y {$planosMovidos} plano(s) actualizados.");

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} [mes, anio]
     */
    private function restarMeses(int $mes, int $anio, int $meses): array
    {
        $total = ($anio * 12 + ($mes - 1)) - $meses;

        return [($total % 12) + 1, intdiv($total, 12)];
    }
}
