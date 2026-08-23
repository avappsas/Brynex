<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Factura;
use App\Models\Plano;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deshace los movimientos de `facturas:retroceder-periodo` y realinea los planos.
 *
 * Contexto: aquel comando reetiquetó las planillas con el mes COTIZADO y copió ese
 * mismo período al plano PILA. La convención vigente es la contraria: la factura
 * guarda el mes en que se COBRA y el plano cubre el mes de servicio que ese cobro
 * paga (ver Plano::periodoPlano). Al quedar factura y plano en el mismo mes, la
 * siguiente facturación del contrato generaba un plano en un período ya cubierto:
 * dos registros de la misma persona en la misma planilla.
 *
 * Fuente de verdad: la bitácora que dejó el propio comando, con el período previo
 * de cada factura. El plano NO se restaura desde la bitácora (allí no quedó
 * registrado) sino que se recalcula con Plano::periodoPlano, que es la regla única.
 *
 * Se omite y se reporta, sin escribir nada, cuando:
 *   • la factura fue anulada o ya volvió a moverse a otro período;
 *   • el período de destino lo ocupa otra factura del contrato que no se mueve;
 *   • el plano ya se liquidó ante el operador (tiene numero_planilla) y el recálculo
 *     lo sacaría de ese período — mover eso desalinea la planilla ya pagada.
 *
 *   php artisan facturas:restaurar-periodo                 # simula
 *   php artisan facturas:restaurar-periodo --ejecutar      # aplica
 */
class RestaurarPeriodoFacturas extends Command
{
    protected $signature = 'facturas:restaurar-periodo
                            {--aliado= : Limitar a un aliado_id}
                            {--contrato= : Limitar a uno o varios contrato_id (separados por coma)}
                            {--forzar-liquidados : Realinear también los planos que ya tienen planilla pagada}
                            {--resolver-arrastre : Adelantar un mes las facturas nuevas que ocuparon el hueco del retroceso}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo simula)}';

    protected $description = 'Devuelve las planillas movidas por facturas:retroceder-periodo a su período de cobro y realinea sus planos';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        // ── Qué se movió, y desde dónde ───────────────────────────────────────
        // El filtro ancla al inicio de la descripción que escribe aquel comando.
        // Un LIKE '%retroceder-periodo%' también engancharía las entradas que deja
        // este comando cuando las menciona, y una segunda corrida desharía su propio
        // trabajo.
        $bitacora = Bitacora::where('descripcion', 'like', 'Período retrocedido%')
            ->when($this->option('aliado'), fn ($q) => $q->where('aliado_id', (int) $this->option('aliado')))
            ->get(['id', 'registro_id', 'detalle', 'aliado_id']);

        if ($bitacora->isEmpty()) {
            $this->warn('No hay movimientos de facturas:retroceder-periodo en la bitácora.');

            return self::SUCCESS;
        }

        $origen = [];   // factura_id => [mes, anio] al que hay que devolverla
        $destino = [];  // factura_id => [mes, anio] donde la dejó el comando
        foreach ($bitacora as $row) {
            $d = is_array($row->detalle) ? $row->detalle : json_decode((string) $row->detalle, true);
            if (empty($d['periodo_anterior']) || empty($d['periodo_nuevo'])) {
                continue;
            }
            [$mA, $aA] = array_map('intval', explode('/', $d['periodo_anterior']));
            [$mN, $aN] = array_map('intval', explode('/', $d['periodo_nuevo']));
            $origen[(int) $row->registro_id] = [$mA, $aA];
            $destino[(int) $row->registro_id] = [$mN, $aN];
        }

        $facturaIds = array_keys($origen);

        if ($this->option('contrato')) {
            $ctrIds = collect(explode(',', (string) $this->option('contrato')))
                ->map(fn ($v) => (int) trim($v))->filter()->all();
            $facturaIds = Factura::whereIn('id', $facturaIds)->whereIn('contrato_id', $ctrIds)->pluck('id')->all();
        }

        $facturas = Factura::whereIn('id', $facturaIds)
            ->get(['id', 'aliado_id', 'contrato_id', 'numero_factura', 'tipo', 'mes', 'anio', 'deleted_at'])
            ->keyBy('id');

        $planos = Plano::whereIn('factura_id', $facturaIds)
            ->get(['id', 'factura_id', 'mes_plano', 'anio_plano', 'tipo_modalidad_id', 'paga_mes_actual', 'numero_planilla'])
            ->keyBy('factura_id');

        // Ocupación de períodos por contrato, en una sola query. Las facturas que
        // también se mueven liberan su casilla actual, así que no cuentan como choque.
        $moverSet = array_flip($facturaIds);
        $ajenas = Factura::whereIn('contrato_id', $facturas->pluck('contrato_id')->unique())
            ->where('tipo', 'planilla')
            ->whereNull('deleted_at')
            ->get(['id', 'contrato_id', 'mes', 'anio', 'numero_factura', 'fecha_pago', 'created_at'])
            ->reject(fn ($f) => isset($moverSet[(int) $f->id]));

        $ocupacion = [];
        foreach ($ajenas as $f) {
            $ocupacion["{$f->contrato_id}-{$f->mes}-{$f->anio}"] = $f;
        }

        // ── Arrastre ──────────────────────────────────────────────────────────
        // Al retroceder las facturas quedó libre el último período del contrato, y
        // la siguiente facturación se registró ahí en vez de en su mes de cobro.
        // Esas son las que hoy estorban el destino de la restauración: se adelantan
        // un mes, pero solo si ese mes es el de su propia fecha_pago y está libre.
        $arrastre = [];
        $corrida = $bitacora->max(fn ($b) => (string) $b->created_at);

        if ($this->option('resolver-arrastre')) {
            foreach ($facturaIds as $fid) {
                $f = $facturas->get($fid);
                if (!$f || $f->deleted_at) {
                    continue;
                }
                [$mOrig, $aOrig] = $origen[$fid];
                $ocupa = $ocupacion["{$f->contrato_id}-{$mOrig}-{$aOrig}"] ?? null;
                if (!$ocupa || isset($arrastre[(int) $ocupa->id])) {
                    continue;
                }
                // Solo las posteriores a la corrida: las viejas no son arrastre.
                if ((string) $ocupa->created_at <= $corrida) {
                    continue;
                }
                $mSig = $mOrig < 12 ? $mOrig + 1 : 1;
                $aSig = $mOrig < 12 ? $aOrig : $aOrig + 1;

                // El mes de cobro lo dice su fecha_pago; si no coincide, no se toca.
                $pago = $ocupa->fecha_pago ? \Carbon\Carbon::parse($ocupa->fecha_pago) : null;
                if (!$pago || (int) $pago->month !== $mSig || (int) $pago->year !== $aSig) {
                    continue;
                }
                if (isset($ocupacion["{$f->contrato_id}-{$mSig}-{$aSig}"])) {
                    continue;
                }

                $arrastre[(int) $ocupa->id] = ['factura' => $ocupa, 'mes' => $mSig, 'anio' => $aSig];
                unset($ocupacion["{$f->contrato_id}-{$mOrig}-{$aOrig}"]);
                $ocupacion["{$f->contrato_id}-{$mSig}-{$aSig}"] = $ocupa;
            }
        }

        // Datos de los planos del arrastre, para recalcular su período
        $planosArrastre = $arrastre
            ? Plano::whereIn('factura_id', array_keys($arrastre))
                ->get(['id', 'factura_id', 'mes_plano', 'anio_plano', 'tipo_modalidad_id', 'paga_mes_actual', 'numero_planilla'])
                ->keyBy('factura_id')
            : collect();

        // ── Plan ──────────────────────────────────────────────────────────────
        $mover = [];
        $omitidos = [];

        foreach ($facturaIds as $fid) {
            $f = $facturas->get($fid);
            if (!$f) {
                $omitidos[] = [$fid, '—', 'la factura ya no existe'];

                continue;
            }
            if ($f->deleted_at) {
                $omitidos[] = [$fid, $f->contrato_id, 'factura anulada'];

                continue;
            }

            [$mDest, $aDest] = $destino[$fid];
            if ((int) $f->mes !== $mDest || (int) $f->anio !== $aDest) {
                $omitidos[] = [$fid, $f->contrato_id, sprintf('ya no está en %02d/%d (hoy %02d/%d): se movió después',
                    $mDest, $aDest, $f->mes, $f->anio)];

                continue;
            }

            [$mOrig, $aOrig] = $origen[$fid];

            if ($ocupa = ($ocupacion["{$f->contrato_id}-{$mOrig}-{$aOrig}"] ?? null)) {
                $omitidos[] = [$fid, $f->contrato_id, sprintf('el destino %02d/%d ya lo ocupa el recibo #%s%s',
                    $mOrig, $aOrig, $ocupa->numero_factura,
                    $this->option('resolver-arrastre') ? '' : ' (pruebe --resolver-arrastre)')];

                continue;
            }

            $plano = $planos->get($fid);
            $planoDest = null;

            if ($plano) {
                [$mPl, $aPl] = Plano::periodoPlano($mOrig, $aOrig, (bool) $plano->paga_mes_actual, $f->tipo === 'afiliacion');
                $cambia = (int) $plano->mes_plano !== $mPl || (int) $plano->anio_plano !== $aPl;

                if ($cambia && $plano->numero_planilla && !$this->option('forzar-liquidados')) {
                    $omitidos[] = [$fid, $f->contrato_id, sprintf(
                        'el plano %02d/%d ya se liquidó (planilla %s) y el recálculo lo llevaría a %02d/%d',
                        $plano->mes_plano, $plano->anio_plano, $plano->numero_planilla, $mPl, $aPl)];

                    continue;
                }
                $planoDest = $cambia ? [$plano->id, $mPl, $aPl] : null;
            }

            $mover[] = [
                'factura' => $f,
                'mesOrig' => $mOrig,
                'anioOrig' => $aOrig,
                'planoDest' => $planoDest,
                'plano' => $plano,
            ];
        }

        // ── Reporte ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(($ejecutar ? '▶ APLICANDO' : '🔍 SIMULACIÓN (dry-run)')
            . ' — devolver planillas al mes de cobro y realinear planos');
        $this->newLine();

        $planosACambiar = collect($mover)->filter(fn ($m) => $m['planoDest'])->count();
        $this->line(sprintf('  Facturas a devolver : %d', count($mover)));
        $this->line(sprintf('  Planos a realinear  : %d', $planosACambiar));
        $this->line(sprintf('  Arrastre a adelantar: %d', count($arrastre)));
        $this->line(sprintf('  Omitidas            : %d', count($omitidos)));

        if ($arrastre) {
            $this->newLine();
            $this->line('Facturas nuevas que se adelantan a su mes de cobro:');
            $filas = [];
            foreach ($arrastre as $fid => $a) {
                $pl = $planosArrastre->get($fid);
                $plTxt = '—';
                if ($pl) {
                    [$mPl, $aPl] = Plano::periodoPlano($a['mes'], $a['anio'], (bool) $pl->paga_mes_actual, false);
                    $plTxt = sprintf('%02d/%d → %02d/%d', $pl->mes_plano, $pl->anio_plano, $mPl, $aPl);
                }
                $filas[] = [
                    $a['factura']->contrato_id,
                    '#' . $a['factura']->numero_factura,
                    sprintf('%02d/%d → %02d/%d', $a['factura']->mes, $a['factura']->anio, $a['mes'], $a['anio']),
                    $plTxt,
                    $a['factura']->fecha_pago,
                ];
            }
            $this->table(['Contrato', 'Recibo', 'Factura', 'Plano', 'Fecha pago'], $filas);
        }

        if ($planosACambiar > 0) {
            $this->newLine();
            $this->line('Planos que cambian de período:');
            $filas = [];
            foreach ($mover as $m) {
                if (!$m['planoDest']) {
                    continue;
                }
                [$pid, $mPl, $aPl] = $m['planoDest'];
                $filas[] = [
                    $m['factura']->contrato_id,
                    '#' . $m['factura']->numero_factura,
                    sprintf('%02d/%d → %02d/%d', $m['factura']->mes, $m['factura']->anio, $m['mesOrig'], $m['anioOrig']),
                    sprintf('%02d/%d → %02d/%d', $m['plano']->mes_plano, $m['plano']->anio_plano, $mPl, $aPl),
                ];
            }
            $this->table(['Contrato', 'Recibo', 'Factura', 'Plano'], $filas);
        }

        if ($omitidos) {
            $this->newLine();
            $this->warn('⚠ Omitidas — revisar a mano:');
            $this->table(['Factura', 'Contrato', 'Motivo'], $omitidos);
        }

        if (!$ejecutar) {
            $this->newLine();
            $this->warn('Nada se escribió. Repita con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        // ── Aplicar ───────────────────────────────────────────────────────────
        $movidas = $planosMovidos = $adelantadas = 0;

        DB::transaction(function () use ($mover, $arrastre, $planosArrastre, &$movidas, &$planosMovidos, &$adelantadas) {
            // Primero el arrastre: libera el período que la restauración necesita.
            foreach ($arrastre as $fid => $a) {
                $f = Factura::find($fid);
                $desde = sprintf('%02d/%d', $f->mes, $f->anio);
                $hasta = sprintf('%02d/%d', $a['mes'], $a['anio']);

                $f->mes = $a['mes'];
                $f->anio = $a['anio'];
                $f->save();
                $adelantadas++;

                $detalle = ['periodo_anterior' => $desde, 'periodo_nuevo' => $hasta];

                if ($pl = $planosArrastre->get($fid)) {
                    [$mPl, $aPl] = Plano::periodoPlano($a['mes'], $a['anio'], (bool) $pl->paga_mes_actual, false);
                    if ((int) $pl->mes_plano !== $mPl || (int) $pl->anio_plano !== $aPl) {
                        DB::table('planos')->where('id', $pl->id)->update([
                            'mes_plano' => $mPl,
                            'anio_plano' => $aPl,
                            'updated_at' => now(),
                        ]);
                        $planosMovidos++;
                        $detalle['plano_anterior'] = sprintf('%02d/%d', $pl->mes_plano, $pl->anio_plano);
                        $detalle['plano_nuevo'] = sprintf('%02d/%d', $mPl, $aPl);
                    }
                }

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Período adelantado a su mes de cobro: {$desde} → {$hasta} "
                        . "(recibo #{$f->numero_factura}) por facturas:restaurar-periodo. "
                        . 'Se había registrado en el hueco que dejó el retroceso de períodos.',
                    detalle: $detalle,
                    alidoId: $f->aliado_id
                );
            }

            foreach ($mover as $m) {
                /** @var Factura $f */
                $f = $m['factura'];
                $desde = sprintf('%02d/%d', $f->mes, $f->anio);
                $hasta = sprintf('%02d/%d', $m['mesOrig'], $m['anioOrig']);

                $f->mes = $m['mesOrig'];
                $f->anio = $m['anioOrig'];
                $f->save();
                $movidas++;

                $detalle = ['periodo_anterior' => $desde, 'periodo_nuevo' => $hasta];

                if ($m['planoDest']) {
                    [$pid, $mPl, $aPl] = $m['planoDest'];
                    DB::table('planos')->where('id', $pid)->update([
                        'mes_plano' => $mPl,
                        'anio_plano' => $aPl,
                        'updated_at' => now(),
                    ]);
                    $planosMovidos++;
                    $detalle['plano_anterior'] = sprintf('%02d/%d', $m['plano']->mes_plano, $m['plano']->anio_plano);
                    $detalle['plano_nuevo'] = sprintf('%02d/%d', $mPl, $aPl);
                }

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Período devuelto al mes de cobro: {$desde} → {$hasta} "
                        . "(recibo #{$f->numero_factura}) por facturas:restaurar-periodo.",
                    detalle: $detalle,
                    alidoId: $f->aliado_id
                );
            }
        });

        $this->newLine();
        $this->info("✔ {$movidas} factura(s) devuelta(s), {$adelantadas} adelantada(s) y {$planosMovidos} plano(s) realineado(s).");

        return self::SUCCESS;
    }
}
