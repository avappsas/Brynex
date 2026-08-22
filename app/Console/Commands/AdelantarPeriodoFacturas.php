<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Factura;
use App\Models\Plano;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Realinea al mes de cobro las planillas de contratos que quedaron corridas un mes.
 *
 * Contexto: la convención de Brynex es que `facturas.mes/anio` es el mes en que se
 * COBRA y el plano cubre el mes de servicio que ese cobro paga (Plano::periodoPlano).
 * El histórico que GiMave traía del sistema viejo usa la convención contraria —
 * etiqueta la factura con el mes cotizado — y la migración lo conservó tal cual.
 * Como verificarOrdenFacturacion() exige facturar el mes inmediatamente siguiente al
 * último, cada nueva factura emitida en Brynex hereda ese desfase y lo perpetúa.
 *
 * Síntoma: el listado de /admin/cobros del mes en curso no encuentra la factura
 * (busca por mes/anio) y la persona figura como pendiente, pero al entrar a cobrarla
 * el sistema muestra que ya está facturada.
 *
 * Qué se mueve, por contrato:
 *   • la última factura de planilla, y
 *   • el tramo consecutivo de facturas emitidas en Brynex (id_legacy NULL) que la
 *     precede — el histórico ya cerrado en el sistema viejo no se toca.
 * El plano acompaña a su factura con el mismo salto, conservando la distancia
 * factura↔plano del contrato; se avisa cuando eso difiere de Plano::periodoPlano.
 *
 * Detección automática (sin --contrato): contratos de independiente, vigentes, cuya
 * última factura pagada quedó un mes por detrás de su fecha_pago y cuya factura
 * anterior también venía corrida. El doble criterio descarta al que pagó tarde una
 * vez; el filtro de recencia descarta al moroso, a quien adelantarle el período le
 * inventaría un mes que no pagó.
 *
 * Se omite y se reporta, sin escribir nada, cuando:
 *   • el período de destino lo ocupa otra factura del contrato que no se mueve;
 *   • el plano ya se liquidó ante el operador (tiene numero_planilla) — moverlo
 *     desalinearía una planilla ya pagada. Con --forzar-liquidados se mueve igual.
 *
 *   php artisan facturas:adelantar-periodo --aliado=4                # simula
 *   php artisan facturas:adelantar-periodo --aliado=4 --ejecutar     # aplica
 */
class AdelantarPeriodoFacturas extends Command
{
    protected $signature = 'facturas:adelantar-periodo
                            {--aliado= : Limitar a un aliado_id}
                            {--contrato= : Forzar uno o varios contrato_id (separados por coma), saltando la detección}
                            {--incluir-dependientes : Detectar también contratos que no son de independiente}
                            {--forzar-liquidados : Mover también los planos que ya tienen planilla pagada}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo simula)}';

    protected $description = 'Adelanta un mes las planillas que quedaron etiquetadas con el mes cotizado en vez del mes de cobro';

    public function handle(): int
    {
        $ejecutar  = (bool) $this->option('ejecutar');
        $aliadoOpt = $this->option('aliado') ? (int) $this->option('aliado') : null;

        $contratoIds = $this->option('contrato')
            ? collect(explode(',', (string) $this->option('contrato')))
                ->map(fn ($v) => (int) trim($v))->filter()->values()->all()
            : $this->detectarContratosCorridos($aliadoOpt);

        if (empty($contratoIds)) {
            $this->warn('No hay contratos con el período corrido.');

            return self::SUCCESS;
        }

        // ── Facturas candidatas: la última de cada contrato más el tramo Brynex ──
        $facturas = Factura::whereIn('contrato_id', $contratoIds)
            ->where('tipo', 'planilla')
            ->where('numero_factura', '>', 0)
            ->whereNull('deleted_at')
            ->when($aliadoOpt, fn ($q) => $q->where('aliado_id', $aliadoOpt))
            ->orderBy('contrato_id')->orderByDesc('id')
            ->get(['id', 'aliado_id', 'contrato_id', 'numero_factura', 'mes', 'anio', 'fecha_pago', 'id_legacy']);

        $aMover = collect();
        foreach ($facturas->groupBy('contrato_id') as $delContrato) {
            foreach ($delContrato->values() as $i => $f) {
                // La más reciente entra siempre; hacia atrás, solo mientras sean de Brynex.
                if ($i > 0 && $f->id_legacy !== null) {
                    break;
                }
                $aMover->push($f);
            }
        }

        if ($aMover->isEmpty()) {
            $this->warn('Los contratos indicados no tienen facturas de planilla que mover.');

            return self::SUCCESS;
        }

        $moverSet = $aMover->pluck('id')->flip();

        // Ocupación del período destino: las facturas que también se mueven liberan
        // su casilla, así que no cuentan como choque.
        $ocupacion = [];
        foreach ($facturas as $f) {
            if (!isset($moverSet[$f->id])) {
                $ocupacion["{$f->contrato_id}-{$f->mes}-{$f->anio}"] = $f;
            }
        }

        $planos = Plano::whereIn('factura_id', $aMover->pluck('id'))
            ->whereNull('deleted_at')
            ->get(['id', 'factura_id', 'mes_plano', 'anio_plano', 'tipo_modalidad_id', 'numero_planilla'])
            ->keyBy('factura_id');

        $filas = [];
        $omitidas = [];

        // De la más nueva a la más vieja: dentro de un mismo contrato, mover primero
        // la de arriba deja libre la casilla de la siguiente.
        foreach ($aMover->sortByDesc('id') as $f) {
            $origen = sprintf('%02d/%d', (int) $f->mes, (int) $f->anio);
            [$mesAnt, $anioAnt] = [(int) $f->mes, (int) $f->anio];
            [$mesN, $anioN] = $mesAnt >= 12 ? [1, $anioAnt + 1] : [$mesAnt + 1, $anioAnt];
            $destino = sprintf('%02d/%d', $mesN, $anioN);

            if (isset($ocupacion["{$f->contrato_id}-{$mesN}-{$anioN}"])) {
                $ajena = $ocupacion["{$f->contrato_id}-{$mesN}-{$anioN}"];
                $omitidas[] = [$f->contrato_id, $f->id, "$origen → $destino", "destino ocupado por la factura {$ajena->id}"];
                continue;
            }

            $plano = $planos->get($f->id);
            $planoDestino = '—';

            if ($plano) {
                if ($plano->numero_planilla && !$this->option('forzar-liquidados')) {
                    $omitidas[] = [$f->contrato_id, $f->id, "$origen → $destino", "plano ya liquidado (planilla {$plano->numero_planilla})"];
                    continue;
                }
                // El plano acompaña a la factura con el mismo salto de un mes: eso
                // conserva la distancia factura↔plano que el contrato ya venía
                // usando. Recalcularlo con periodoPlano() sería la regla teórica,
                // pero el plano guarda un snapshot de la modalidad que en varios
                // registros de GiMave no coincide con la del contrato, y el
                // recálculo movería el plano dos meses. Se avisa cuando difieren.
                // sqlsrv devuelve los smallint como string: castear antes de comparar.
                $mesAct  = (int) $plano->mes_plano;
                $anioAct = (int) $plano->anio_plano;

                [$mesP, $anioP] = $mesAct >= 12 ? [1, $anioAct + 1] : [$mesAct + 1, $anioAct];

                [$mesR, $anioR] = Plano::periodoPlano($mesN, $anioN, (int) $plano->tipo_modalidad_id, false);
                $aviso = ($mesR !== $mesP || $anioR !== $anioP)
                    ? sprintf(' ⚠ regla: %02d/%d', $mesR, $anioR)
                    : '';

                $planoDestino = sprintf('%02d/%d → %02d/%d%s', $mesAct, $anioAct, $mesP, $anioP, $aviso);
                $plano->_destino = [$mesP, $anioP];
            }

            $filas[] = [
                'factura'       => $f,
                'origen'        => $origen,
                'destino'       => $destino,
                'mes_nuevo'     => $mesN,
                'anio_nuevo'    => $anioN,
                'plano'         => $plano,
                'plano_destino' => $planoDestino,
            ];

            // Su casilla original queda libre para la factura anterior del contrato.
            unset($ocupacion["{$f->contrato_id}-{$f->mes}-{$f->anio}"]);
        }

        $this->table(
            ['contrato', 'factura', 'recibo', 'pago', 'período', 'plano'],
            collect($filas)->map(fn ($r) => [
                $r['factura']->contrato_id,
                $r['factura']->id,
                $r['factura']->numero_factura,
                $r['factura']->fecha_pago?->format('Y-m-d') ?? '—',
                "{$r['origen']} → {$r['destino']}",
                $r['plano_destino'],
            ])->all()
        );

        if ($omitidas) {
            $this->newLine();
            $this->warn('Omitidas:');
            $this->table(['contrato', 'factura', 'período', 'motivo'], $omitidas);
        }

        $this->newLine();
        $this->info(count($filas).' factura(s) a adelantar un mes en '.collect($filas)->pluck('factura.contrato_id')->unique()->count().' contrato(s).');

        if (!$ejecutar) {
            $this->comment('Simulación. Repite con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($filas) {
            foreach ($filas as $r) {
                $f = $r['factura'];

                Factura::where('id', $f->id)->update([
                    'mes'        => $r['mes_nuevo'],
                    'anio'       => $r['anio_nuevo'],
                    'updated_at' => now(),
                ]);

                if ($r['plano'] && isset($r['plano']->_destino)) {
                    [$mesP, $anioP] = $r['plano']->_destino;
                    Plano::where('id', $r['plano']->id)->update([
                        'mes_plano'  => $mesP,
                        'anio_plano' => $anioP,
                        'updated_at' => now(),
                    ]);
                }

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Período realineado al mes de cobro: {$r['origen']} → {$r['destino']} "
                        . "(recibo #{$f->numero_factura}) por facturas:adelantar-periodo. "
                        . 'Venía etiquetada con el mes cotizado en vez del mes de cobro.',
                    detalle: [
                        'periodo_anterior' => $r['origen'],
                        'periodo_nuevo'    => $r['destino'],
                        'plano'            => $r['plano_destino'],
                    ],
                    alidoId: (int) $f->aliado_id
                );
            }
        });

        $this->info('Aplicado.');

        return self::SUCCESS;
    }

    /**
     * Contratos vigentes cuya última factura pagada quedó un mes por detrás de su
     * fecha de pago, y cuya factura anterior también venía corrida.
     *
     * El segundo criterio es el que separa el desfase de convención —constante,
     * factura tras factura— del cliente que pagó tarde una vez.
     */
    private function detectarContratosCorridos(?int $aliadoId): array
    {
        $filtroAliado = $aliadoId ? "AND f.aliado_id = {$aliadoId}" : '';

        // Solo modalidades de independiente, salvo que se pida lo contrario.
        $filtroModalidad = $this->option('incluir-dependientes')
            ? ''
            : 'AND c.tipo_modalidad_id IN ('.implode(',', \App\Models\TipoModalidad::IDS_INDEPENDIENTE).')';

        // La corrección es para cadenas al día: si la última factura es vieja, el
        // contrato está en mora y adelantarla le inventaría un mes que no pagó.
        $desde = now()->startOfMonth()->subMonth()->toDateString();

        $rows = DB::select("
            WITH u AS (
                SELECT f.contrato_id, f.fecha_pago, f.mes, f.anio,
                       (YEAR(f.fecha_pago) * 12 + MONTH(f.fecha_pago)) - (f.anio * 12 + f.mes) AS d,
                       ROW_NUMBER() OVER (PARTITION BY f.contrato_id ORDER BY f.id DESC) rn
                FROM facturas f
                WHERE f.deleted_at IS NULL AND f.tipo = 'planilla'
                  AND f.numero_factura > 0 AND f.fecha_pago IS NOT NULL
                  {$filtroAliado}
            )
            SELECT u.contrato_id
            FROM u
            JOIN contratos c ON c.id = u.contrato_id
            WHERE u.rn <= 2 AND c.estado IN ('vigente', 'activo')
              {$filtroModalidad}
            GROUP BY u.contrato_id
            HAVING MAX(CASE WHEN u.rn = 1 THEN u.d END) = 1
               AND MAX(CASE WHEN u.rn = 2 THEN u.d END) >= 1
               AND MAX(CASE WHEN u.rn = 1 THEN u.fecha_pago END) >= '{$desde}'
        ");

        return array_map(fn ($r) => (int) $r->contrato_id, $rows);
    }
}
