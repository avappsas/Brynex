<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Contrato;
use App\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrocede el período (mes/anio) de las planillas hacia el mes cotizado.
 *
 * Contexto: al facturar, el sistema guarda como período el mes en que se cobra.
 * Para los dependientes de empresa (modalidad 0) esa es la convención general y
 * NO debe tocarse. Para los independientes sí es un error: la modalidad 10
 * (Independiente Vencido) cotiza el mes vencido, así que un pago del 21 de julio
 * corresponde a la planilla de junio, no de julio.
 *
 * El criterio se aplicó factura por factura según quién facturara ese mes, así
 * que hay contratos MEZCLADOS: unas planillas en mes de pago y otras en vencido.
 * Por eso existe --solo-desfase: mueve únicamente las que están mal y deja
 * quietas las que ya estaban bien. Cuando ese movimiento aterrizaría encima de
 * una factura que no se mueve, el contrato entero se omite y se reporta.
 *
 * El desfase de una factura es (período) − (mes de fecha_pago), en meses:
 *    0 → se registró el mes en que se pagó   ← lo que hay que corregir
 *   -1 → se registró el mes cotizado         ← ya está bien
 *
 * No toca afiliaciones: van en el mes de ingreso.
 *
 *   # una persona
 *   php artisan facturas:retroceder-periodo 16828686 --aliado=4
 *
 *   # todos los independientes vigentes, solo las facturas mal registradas
 *   php artisan facturas:retroceder-periodo --aliado=2 --rs-independientes \
 *       --solo-vigentes --solo-desfase=0
 */
class RetrocederPeriodoFacturas extends Command
{
    protected $signature = 'facturas:retroceder-periodo
                            {cedula? : Cédula a corregir (omitir si se usa --rs-independientes)}
                            {--aliado= : aliado_id propietario de las facturas (obligatorio)}
                            {--meses=1 : Cuántos meses retroceder}
                            {--contrato= : Limitar a uno o varios contrato_id (separados por coma)}
                            {--rs-independientes : Todos los contratos de razones sociales con es_independiente=1}
                            {--solo-vigentes : Limitar a contratos vigentes/activos}
                            {--solo-desfase= : Mover solo las facturas con este desfase (ej. 0)}
                            {--desde-anio= : Solo facturas de este año en adelante (deja quieta la historia vieja)}
                            {--desde-periodo= : Solo facturas desde este período AAAA-MM (para mover un tramo de la serie)}
                            {--detalle : Listar factura por factura en vez del resumen por contrato}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo simula)}';

    protected $description = 'Retrocede el período de las planillas hacia el mes cotizado (con sus planos PILA)';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $meses    = (int) $this->option('meses');
        $ejecutar = (bool) $this->option('ejecutar');
        $cedula   = $this->argument('cedula');
        $porRsInd = (bool) $this->option('rs-independientes');
        $desfase  = $this->option('solo-desfase');
        $desfase  = $desfase === null ? null : (int) $desfase;

        if (!$aliadoId) {
            $this->error('Falta --aliado=. Las facturas se filtran siempre por aliado_id.');

            return self::FAILURE;
        }
        if ($meses < 1) {
            $this->error('--meses debe ser 1 o más.');

            return self::FAILURE;
        }
        if (!$cedula && !$porRsInd && !$this->option('contrato')) {
            $this->error('Indique una cédula, --contrato= o --rs-independientes.');

            return self::FAILURE;
        }

        // ── Selección de facturas ─────────────────────────────────────────────
        $q = Factura::where('aliado_id', $aliadoId)
            ->where('tipo', 'planilla')
            ->where('numero_factura', '>', 0);

        if ($cedula) {
            $q->where('cedula', trim((string) $cedula));
        }
        if ($this->option('contrato')) {
            $ctrIds = collect(explode(',', (string) $this->option('contrato')))
                ->map(fn ($v) => (int) trim($v))
                ->filter()
                ->all();
            $q->whereIn('contrato_id', $ctrIds);
        }
        if ($this->option('desde-anio')) {
            $q->where('anio', '>=', (int) $this->option('desde-anio'));
        }
        if ($this->option('desde-periodo')) {
            if (!preg_match('/^(\d{4})-(\d{1,2})$/', (string) $this->option('desde-periodo'), $m)) {
                $this->error('--desde-periodo debe tener el formato AAAA-MM (ej. 2021-04).');

                return self::FAILURE;
            }
            // Un tramo de la serie: útil cuando el inicio ya está bien registrado
            // y solo se corrió de cierto mes en adelante.
            [$pAnio, $pMes] = [(int) $m[1], (int) $m[2]];
            $q->whereRaw('(anio * 12 + mes) >= ?', [$pAnio * 12 + $pMes]);
        }

        if ($porRsInd) {
            $rsIds = DB::table('razones_sociales')
                ->where('aliado_id', $aliadoId)
                ->where('es_independiente', 1)
                ->pluck('id');

            if ($rsIds->isEmpty()) {
                $this->warn("El aliado {$aliadoId} no tiene razones sociales con es_independiente=1.");

                return self::SUCCESS;
            }
            $this->line('Razones sociales independientes: ' . $rsIds->join(', '));

            $ctrQ = Contrato::where('aliado_id', $aliadoId)->whereIn('razon_social_id', $rsIds);
            if ($this->option('solo-vigentes')) {
                $ctrQ->whereIn('estado', ['vigente', 'activo']);
            }
            $q->whereIn('contrato_id', $ctrQ->pluck('id'));
        } elseif ($this->option('solo-vigentes')) {
            $q->whereIn('contrato_id', Contrato::where('aliado_id', $aliadoId)
                ->whereIn('estado', ['vigente', 'activo'])->pluck('id'));
        }

        $facturas = $q->orderBy('contrato_id')->orderBy('anio')->orderBy('mes')->get();

        // Filtro por desfase — necesita fecha_pago para poder calcularlo
        $sinFecha = 0;
        if ($desfase !== null) {
            $antes = $facturas->count();
            $facturas = $facturas->filter(function ($f) use ($desfase) {
                if (!$f->fecha_pago) {
                    return false;
                }

                return $this->desfase($f) === $desfase;
            });
            $sinFecha = $antes - $facturas->count();
        }

        if ($facturas->isEmpty()) {
            $this->warn('No hay planillas que cumplan los filtros.');

            return self::SUCCESS;
        }

        // ── Plan de movimiento, agrupado por contrato ─────────────────────────
        $idsQueSeMueven = $facturas->pluck('id')->all();
        $porContrato    = $facturas->groupBy('contrato_id');

        $datosContrato = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('id', $porContrato->keys())
            ->with(['cliente' => fn ($q) => $q->where('aliado_id', $aliadoId)])
            ->get()
            ->keyBy('id');

        // Ocupación actual de períodos, en una sola query en vez de una por factura
        // (con miles de facturas contra SQL Server remoto la diferencia es de
        // minutos a segundos). Índice: "contratoId-mes-anio" => numero_factura,
        // ya sin las que se van a mover, que liberan su casilla.
        $idsMoverSet = array_flip($idsQueSeMueven);
        $ocupacion   = [];
        Factura::where('aliado_id', $aliadoId)
            ->whereIn('contrato_id', $porContrato->keys())
            ->where('tipo', 'planilla')
            ->where('numero_factura', '>', 0)
            ->get(['id', 'contrato_id', 'mes', 'anio', 'numero_factura'])
            ->each(function ($f) use (&$ocupacion, $idsMoverSet) {
                if (isset($idsMoverSet[$f->id])) {
                    return;
                }
                $ocupacion["{$f->contrato_id}-{$f->mes}-{$f->anio}"] = $f->numero_factura;
            });

        $mover   = [];   // contrato_id => [ ['factura'=>, 'mesDest'=>, 'anioDest'=>], ... ]
        $omitidos = [];  // contrato_id => motivo

        foreach ($porContrato as $contratoId => $lote) {
            $movimientos = [];
            $choque      = null;

            foreach ($lote as $f) {
                [$mesDest, $anioDest] = $this->restarMeses((int) $f->mes, (int) $f->anio, $meses);

                // El destino solo estorba si lo ocupa una factura que NO se mueve.
                // Con --solo-desfase eso pasa de verdad: las planillas que ya estaban
                // en vencido se quedan quietas y pueden estar justo en el destino.
                $ocupado = $ocupacion["{$f->contrato_id}-{$mesDest}-{$anioDest}"] ?? null;

                if ($ocupado) {
                    $choque = sprintf('recibo #%s (%02d/%d) ya ocupa el destino de #%s',
                        $ocupado, $mesDest, $anioDest, $f->numero_factura);
                    break;
                }

                $movimientos[] = ['factura' => $f, 'mesDest' => $mesDest, 'anioDest' => $anioDest];
            }

            // Nunca dejar una planilla en un mes en que el contrato aún no existía.
            // Pasa cuando la primera planilla ya coincide con el mes de ingreso:
            // retrocederla la sacaría fuera de la vida del contrato.
            if (!$choque && $movimientos) {
                $contrato = $datosContrato->get($contratoId);
                if ($contrato?->fecha_ingreso) {
                    $primero = collect($movimientos)
                        ->sortBy(fn ($m) => $m['anioDest'] * 12 + $m['mesDest'])
                        ->first();
                    $kDest = $primero['anioDest'] * 12 + $primero['mesDest'];
                    $kIng  = (int) $contrato->fecha_ingreso->year * 12 + (int) $contrato->fecha_ingreso->month;

                    if ($kDest < $kIng) {
                        $choque = sprintf('el destino %02d/%d queda antes del ingreso (%s)',
                            $primero['mesDest'], $primero['anioDest'],
                            $contrato->fecha_ingreso->format('Y-m-d'));
                    }
                }
            }

            if ($choque) {
                $omitidos[$contratoId] = $choque;
            } else {
                $mover[$contratoId] = $movimientos;
            }
        }

        // ── Reporte ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(($ejecutar ? '▶ APLICANDO' : '🔍 SIMULACIÓN (dry-run)')
            . " — aliado {$aliadoId}, retroceso de {$meses} mes(es)"
            . ($desfase !== null ? ", solo facturas con desfase {$desfase}" : ''));
        if ($sinFecha > 0) {
            $this->line("  ({$sinFecha} planilla(s) descartada(s): sin fecha_pago o con otro desfase)");
        }
        $this->newLine();

        if ($this->option('detalle')) {
            $filas = [];
            foreach ($mover as $contratoId => $movs) {
                foreach ($movs as $m) {
                    $filas[] = [
                        $m['factura']->numero_factura,
                        $contratoId,
                        sprintf('%02d/%d', $m['factura']->mes, $m['factura']->anio),
                        sprintf('%02d/%d', $m['mesDest'], $m['anioDest']),
                        $m['factura']->fecha_pago?->format('Y-m-d') ?? '—',
                    ];
                }
            }
            $this->table(['N° recibo', 'Contrato', 'Período', '→ Destino', 'Fecha pago'], $filas);
        } else {
            $filas = [];
            foreach ($mover as $contratoId => $movs) {
                $c = $datosContrato->get($contratoId);
                $primera = $movs[0]['factura'];
                $ultima  = end($movs)['factura'];
                $filas[] = [
                    $contratoId,
                    $c?->cedula ?? '',
                    mb_substr(trim(($c?->cliente?->primer_nombre ?? '') . ' ' . ($c?->cliente?->primer_apellido ?? '')), 0, 24),
                    $c?->tipo_modalidad_id ?? '',
                    count($movs),
                    sprintf('%02d/%d … %02d/%d', $primera->mes, $primera->anio, $ultima->mes, $ultima->anio),
                ];
            }
            $this->table(['Contrato', 'Cédula', 'Nombre', 'Mod', 'Facturas', 'Rango de períodos'], $filas);
        }

        $totalFacturas = collect($mover)->flatten(1)->count();
        $this->info("A mover: {$totalFacturas} factura(s) en " . count($mover) . ' contrato(s).');

        if (!empty($omitidos)) {
            $this->newLine();
            $this->warn('⚠ ' . count($omitidos) . ' contrato(s) omitido(s) — el destino ya está ocupado, revíselos a mano:');
            foreach ($omitidos as $contratoId => $motivo) {
                $c = $datosContrato->get($contratoId);
                $this->line(sprintf('   ctr=%-7s ced=%-12s %-24s → %s',
                    $contratoId, $c?->cedula ?? '',
                    mb_substr(trim(($c?->cliente?->primer_nombre ?? '') . ' ' . ($c?->cliente?->primer_apellido ?? '')), 0, 24),
                    $motivo));
            }
        }

        if (!$ejecutar) {
            $this->newLine();
            $this->warn('Nada se escribió. Repita con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        // ── Aplicar ───────────────────────────────────────────────────────────
        $movidas = 0;
        $planosMovidos = 0;

        DB::transaction(function () use ($mover, $aliadoId, $meses, &$movidas, &$planosMovidos) {
            foreach ($mover as $movs) {
                foreach ($movs as $m) {
                    /** @var Factura $f */
                    $f       = $m['factura'];
                    $origen  = sprintf('%02d/%d', $f->mes, $f->anio);
                    $destino = sprintf('%02d/%d', $m['mesDest'], $m['anioDest']);

                    $f->mes  = $m['mesDest'];
                    $f->anio = $m['anioDest'];
                    $f->save();
                    $movidas++;

                    // Los planos PILA guardan su propio período
                    $planosMovidos += DB::table('planos')
                        ->where('factura_id', $f->id)
                        ->whereNull('deleted_at')
                        ->update([
                            'mes_plano'  => $m['mesDest'],
                            'anio_plano' => $m['anioDest'],
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
            }
        });

        $this->newLine();
        $this->info("✔ {$movidas} factura(s) y {$planosMovidos} plano(s) actualizados.");

        return self::SUCCESS;
    }

    /** Desfase en meses entre el período de la factura y el mes de su fecha_pago. */
    private function desfase(Factura $f): int
    {
        return ((int) $f->anio * 12 + (int) $f->mes)
            - ((int) $f->fecha_pago->year * 12 + (int) $f->fecha_pago->month);
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
