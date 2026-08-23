<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Contrato;
use App\Models\Plano;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quita el `paga_mes_actual` a los contratos que en realidad cotizan el mes
 * vencido, y baja los planos que la app ya generó adelantados.
 *
 * Escrito cuando "mes actual" era la modalidad 11; al jubilarse esa modalidad
 * la corrección pasó a hacerse sobre el flag, que es lo que hoy decide el
 * período. El caso que lo originó se conserva porque explica cómo se detecta.
 *
 * Caso GiMave (aliado 4), ago-2026: 65 contratos vigentes figuraban como mes actual
 * pero su operación real —la del sistema legacy, donde siguen facturando— pone
 * la planilla en el mes vencido. En [GiMave_Integral].dbo.PLANOS los 1.361
 * registros con Tipo_P=11 llevan MES_PLANO un mes por detrás de la factura, sin
 * una sola excepción, y las 8 planillas con número pagado lo confirman.
 *
 * Mientras la etiqueta decía 11, al facturar en BryNex el plano salía en el mes
 * de la factura (Plano::periodoPlano) y quedaba un mes sin cubrir: 25 contratos
 * se quedaron sin planilla de julio 2026.
 *
 * Se corrigen tres cosas a la vez, porque por separado no sirven:
 *   • el período del plano   → mes vencido;
 *   • su snapshot `paga_mes_actual` → 0, si no el plano desaparece del módulo
 *     de planos, que filtra el mes según ese snapshot (ver PlanoPagoController::index);
 *   • el flag del contrato → 0, o el problema se repite cada mes.
 *
 * No toca los planos ya liquidados ante el operador ni los que aterrizarían
 * sobre un período que otro plano del contrato ya cubre: los reporta.
 *
 *   php artisan contratos:corregir-modalidad-vencido --aliado=4
 *   php artisan contratos:corregir-modalidad-vencido --aliado=4 --ejecutar
 */
class CorregirModalidadMesActual extends Command
{
    /** Modalidad de independiente vencido, la que queda tras la corrección. */
    private const MODALIDAD_VENCIDO = 10;

    protected $signature = 'contratos:corregir-modalidad-vencido
                            {--aliado= : aliado_id a corregir (obligatorio)}
                            {--contrato= : Limitar a uno o varios contrato_id (separados por coma)}
                            {--incluir-retirados : También cambia la modalidad de los contratos retirados}
                            {--solo-planos : Corrige los planos adelantados sin tocar la modalidad de los contratos}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo simula)}';

    protected $description = 'Quita el mes actual a los contratos que en realidad cotizan vencido, y baja los planos que quedaron adelantados';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $ejecutar = (bool) $this->option('ejecutar');

        if (!$aliadoId) {
            $this->error('Falta --aliado=. La corrección se limita siempre a un aliado.');

            return self::FAILURE;
        }

        $ctrFiltro = null;
        if ($this->option('contrato')) {
            $ctrFiltro = collect(explode(',', (string) $this->option('contrato')))
                ->map(fn ($v) => (int) trim($v))->filter()->all();
        }

        // ── Planos que la app generó en el mes de la factura ──────────────────
        // Son los que hay que bajar: el resto de la serie (la que vino del legacy)
        // ya está en vencido y no se toca.
        $planos = DB::table('planos AS p')
            ->join('facturas AS f', function ($j) use ($aliadoId) {
                $j->on('f.id', '=', 'p.factura_id')->where('f.aliado_id', $aliadoId);
            })
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')
            ->whereNull('f.deleted_at')
            ->where('p.tipo_reg', 'planilla')
            ->where('p.paga_mes_actual', 1)
            ->whereRaw('(p.anio_plano * 12 + p.mes_plano) = (f.anio * 12 + f.mes)')
            ->when($ctrFiltro, fn ($q) => $q->whereIn('p.contrato_id', $ctrFiltro))
            ->orderBy('p.contrato_id')
            ->get([
                'p.id AS plano_id', 'p.contrato_id', 'p.mes_plano', 'p.anio_plano',
                'p.numero_planilla', 'f.numero_factura', 'f.mes AS fmes', 'f.anio AS fanio',
            ]);

        // Ocupación de períodos por contrato, para no aterrizar sobre otro plano
        $ocupacion = [];
        if ($planos->isNotEmpty()) {
            DB::table('planos AS p')
                ->join('facturas AS f', function ($j) {
                    $j->on('f.id', '=', 'p.factura_id')->whereNull('f.deleted_at');
                })
                ->whereIn('p.contrato_id', $planos->pluck('contrato_id')->unique())
                ->whereNull('p.deleted_at')
                ->get(['p.id', 'p.contrato_id', 'p.mes_plano', 'p.anio_plano'])
                ->each(function ($p) use (&$ocupacion) {
                    $ocupacion["{$p->contrato_id}-{$p->mes_plano}-{$p->anio_plano}"] = $p->id;
                });
        }

        $bajar    = [];
        $omitidos = [];

        foreach ($planos as $p) {
            [$mDest, $aDest] = Plano::periodoPlano(
                (int) $p->fmes, (int) $p->fanio, false, false
            );

            if ($p->numero_planilla) {
                $omitidos[] = [$p->contrato_id, '#' . $p->numero_factura,
                    "ya liquidado ante el operador (planilla {$p->numero_planilla})"];
                continue;
            }
            if (isset($ocupacion["{$p->contrato_id}-{$mDest}-{$aDest}"])) {
                $omitidos[] = [$p->contrato_id, '#' . $p->numero_factura,
                    sprintf('el período %02d/%d ya lo cubre otro plano', $mDest, $aDest)];
                continue;
            }

            $bajar[] = ['plano' => $p, 'mes' => $mDest, 'anio' => $aDest];
            $ocupacion["{$p->contrato_id}-{$mDest}-{$aDest}"] = $p->plano_id;
        }

        // ── Contratos a reetiquetar ───────────────────────────────────────────
        $contratos = collect();
        if (!$this->option('solo-planos')) {
            $contratos = Contrato::where('aliado_id', $aliadoId)
                ->where('paga_mes_actual', 1)
                ->when(!$this->option('incluir-retirados'),
                    fn ($q) => $q->whereIn('estado', ['vigente', 'activo']))
                ->when($ctrFiltro, fn ($q) => $q->whereIn('id', $ctrFiltro))
                ->get(['id', 'cedula', 'estado', 'tipo_modalidad_id', 'paga_mes_actual']);
        }

        // ── Reporte ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(($ejecutar ? '▶ APLICANDO' : '🔍 SIMULACIÓN (dry-run)')
            . " — aliado {$aliadoId}: mes actual → mes vencido");
        $this->newLine();
        $this->line(sprintf('  Planos a bajar al mes vencido : %d', count($bajar)));
        $this->line(sprintf('  Contratos a reetiquetar       : %d', $contratos->count()));
        $this->line(sprintf('  Omitidos                      : %d', count($omitidos)));

        if ($bajar) {
            $this->newLine();
            $filas = array_map(fn ($b) => [
                $b['plano']->contrato_id,
                '#' . $b['plano']->numero_factura,
                sprintf('%02d/%d', $b['plano']->fmes, $b['plano']->fanio),
                sprintf('%02d/%d → %02d/%d', $b['plano']->mes_plano, $b['plano']->anio_plano, $b['mes'], $b['anio']),
            ], $bajar);
            $this->table(['Contrato', 'Recibo', 'Factura', 'Plano'], $filas);
        }

        if ($omitidos) {
            $this->newLine();
            $this->warn('⚠ Omitidos — revisar a mano:');
            $this->table(['Contrato', 'Recibo', 'Motivo'], $omitidos);
        }

        if (!$ejecutar) {
            $this->newLine();
            $this->warn('Nada se escribió. Repita con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        // ── Aplicar ───────────────────────────────────────────────────────────
        $nPlanos = $nContratos = 0;

        DB::transaction(function () use ($bajar, $contratos, $aliadoId, &$nPlanos, &$nContratos) {
            foreach ($bajar as $b) {
                $p       = $b['plano'];
                $desde   = sprintf('%02d/%d', $p->mes_plano, $p->anio_plano);
                $hasta   = sprintf('%02d/%d', $b['mes'], $b['anio']);

                // El snapshot de modalidad va junto con el período: el módulo de
                // planos decide en qué mes mostrar el registro según ese campo.
                DB::table('planos')->where('id', $p->plano_id)->update([
                    'mes_plano'         => $b['mes'],
                    'anio_plano'        => $b['anio'],
                    'tipo_modalidad_id' => self::MODALIDAD_VENCIDO,
                    'paga_mes_actual'   => 0,
                    'tipo_p'            => (string) self::MODALIDAD_VENCIDO,
                    'updated_at'        => now(),
                ]);
                $nPlanos++;

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Plano',
                    registroId: (int) $p->plano_id,
                    descripcion: "Plano bajado al mes vencido: {$desde} → {$hasta} "
                        . "(recibo #{$p->numero_factura}, contrato {$p->contrato_id}). "
                        . 'El plano decía mes actual pero el contrato cotiza vencido.',
                    detalle: [
                        'plano_anterior' => $desde,
                        'plano_nuevo'    => $hasta,
                        'paga_mes_actual' => ['antes' => 1, 'despues' => 0],
                    ],
                    alidoId: $aliadoId
                );
            }

            foreach ($contratos as $c) {
                DB::table('contratos')->where('id', $c->id)->update([
                    'tipo_modalidad_id' => self::MODALIDAD_VENCIDO,
                    'paga_mes_actual'   => 0,
                    'updated_at'        => now(),
                ]);
                $nContratos++;

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Contrato',
                    registroId: (int) $c->id,
                    descripcion: "Contrato pasado de mes actual a mes vencido "
                        . "(cédula {$c->cedula}). Cotiza el mes vencido según su historial de planillas.",
                    detalle: [
                        'paga_mes_actual' => ['antes' => 1, 'despues' => 0],
                        'estado'             => $c->estado,
                    ],
                    alidoId: $aliadoId
                );
            }
        });

        $this->newLine();
        $this->info("✔ {$nPlanos} plano(s) bajado(s) al mes vencido y {$nContratos} contrato(s) reetiquetado(s).");

        return self::SUCCESS;
    }
}
