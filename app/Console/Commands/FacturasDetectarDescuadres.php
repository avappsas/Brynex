<?php

namespace App\Console\Commands;

use App\Services\AlertaOperativaService;
use App\Services\IvaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Vigía de la facturación: busca las tres formas en que una factura termina
 * cobrando algo distinto de lo que el cliente pagó.
 *
 * Las tres se vieron en producción en septiembre de 2026 y las tres se
 * reconocen igual: la plata del lote cuadra, pero la factura individual no.
 * Por eso nadie las nota hasta que el saldo a favor aparece descontando en el
 * modal del mes siguiente.
 *
 *   A. Pago mal repartido — el lote se cobró bien, pero dentro de él una
 *      factura se llevó plata de las otras (un retiro que pesaba como un mes
 *      entero). Se reconoce porque los saldos internos se compensan entre sí.
 *   B. "Otros" fuera del total — el campo quedó guardado pero no se sumó al
 *      total, así que el cobro se perdió y el pago sobrante cayó a saldo.
 *   C. IVA que falta — factura de planilla con administración pero sin IVA,
 *      de un cliente que sí lo causa.
 *
 * Con --avisar manda un WhatsApp a guardia SOLO por los hallazgos nuevos: lo
 * que ya se revisó y se decidió dejar así no vuelve a sonar (ver la baseline).
 */
class FacturasDetectarDescuadres extends Command
{
    protected $signature = 'facturas:detectar-descuadres
                            {--dias=45         : Ventana hacia atrás, en días}
                            {--aliado=         : Limitar a un aliado}
                            {--avisar          : Enviar WhatsApp si hay hallazgos nuevos}
                            {--olvidar         : Vaciar la baseline y volver a reportar todo}';

    protected $description = 'Detecta facturas que cobran algo distinto de lo que el cliente pagó: pago mal repartido, "Otros" fuera del total e IVA faltante.';

    /** Lo ya reportado, para que una corrida diaria no repita lo mismo. */
    private const BASELINE = 'descuadres-vistos.json';

    public function handle(AlertaOperativaService $alertas): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $aliado = $this->option('aliado') ? (int) $this->option('aliado') : null;
        $desde = now()->subDays($dias)->startOfDay();

        if ($this->option('olvidar')) {
            Storage::delete(self::BASELINE);
            $this->warn('Baseline borrada: se reporta todo de nuevo.');
        }

        $hallazgos = array_merge(
            $this->pagoMalRepartido($desde, $aliado),
            $this->otrosFueraDelTotal($desde, $aliado),
            $this->ivaFaltante($desde, $aliado),
        );

        $this->render($hallazgos, $dias);

        $vistos = $this->baseline();
        $nuevos = array_values(array_filter($hallazgos, fn ($h) => ! in_array($h['clave'], $vistos, true)));

        if ($nuevos) {
            $this->newLine();
            $this->warn('Hallazgos NUEVOS desde la última corrida: '.count($nuevos));
            foreach ($nuevos as $h) {
                $this->line("  {$h['clave']} — {$h['detalle']}");
            }
        } else {
            $this->info('Sin hallazgos nuevos desde la última corrida.');
        }

        if ($nuevos && $this->option('avisar')) {
            $alertas->enviar('Facturación', $this->mensaje($nuevos))
                ? $this->info('Aviso enviado a guardia.')
                : $this->error('No se pudo enviar el aviso (queda en el log).');
        }

        $this->guardarBaseline(array_column($hallazgos, 'clave'));

        return self::SUCCESS;
    }

    /**
     * A. Lotes donde los saldos internos se compensan: el cliente pagó lo suyo,
     * pero el reparto entre las facturas del lote quedó torcido.
     */
    private function pagoMalRepartido($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.aliado_id, f.numero_factura, COUNT(*) n, MIN(f.mes) mes, MIN(f.anio) anio,
                   SUM(CASE WHEN f.saldo_proximo > 0 THEN f.saldo_proximo ELSE 0 END) a_favor,
                   SUM(f.saldo_proximo) neto
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            GROUP BY f.aliado_id, f.numero_factura
            HAVING COUNT(*) > 1
               AND SUM(CASE WHEN f.saldo_proximo > 0 THEN f.saldo_proximo ELSE 0 END) > 5000
               AND ABS(SUM(f.saldo_proximo)) < SUM(CASE WHEN f.saldo_proximo > 0 THEN f.saldo_proximo ELSE 0 END) / 2
            ORDER BY SUM(CASE WHEN f.saldo_proximo > 0 THEN f.saldo_proximo ELSE 0 END) DESC
        ", [$desde]);

        return array_map(fn ($r) => [
            'tipo' => 'pago mal repartido',
            'clave' => "pago:{$r->aliado_id}:{$r->numero_factura}",
            'aliado' => $r->aliado_id,
            'monto' => (int) $r->a_favor,
            'detalle' => "recibo #{$r->numero_factura} ({$r->mes}/{$r->anio}, {$r->n} facturas): $"
                .number_format($r->a_favor, 0, ',', '.').' de saldo a favor falso, neto '.$r->neto,
            'arreglo' => "php artisan facturas:recalcular-lote-otros {$r->aliado_id} {$r->numero_factura} --solo-pago",
        ], $rows);
    }

    /** B. El campo "Otros" existe pero no está sumado al total. */
    private function otrosFueraDelTotal($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio,
                   f.otros + f.otros_admon AS otros, f.total, f.saldo_proximo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND (f.otros + f.otros_admon) > 0
              AND f.total = f.total_ss + f.admon + f.admin_asesor + f.seguro + f.afiliacion + f.iva + f.mora
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            ORDER BY (f.otros + f.otros_admon) DESC
        ", [$desde]);

        return array_map(fn ($r) => [
            'tipo' => '"Otros" fuera del total',
            'clave' => "otros:{$r->aliado_id}:{$r->id}",
            'aliado' => $r->aliado_id,
            'monto' => (int) $r->otros,
            'detalle' => "factura {$r->id} (recibo #{$r->numero_factura}, c.c. {$r->cedula}, {$r->mes}/{$r->anio}): $"
                .number_format($r->otros, 0, ',', '.').' cobrados que el total no incluye',
            'arreglo' => "php artisan facturas:recalcular-lote-otros {$r->aliado_id} {$r->numero_factura} --otros-fuera",
        ], $rows);
    }

    /** C. Planilla con administración, sin IVA, de un cliente que sí lo causa. */
    private function ivaFaltante($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio,
                   f.admon + ISNULL(f.admin_asesor,0) AS base, f.saldo_proximo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND f.tipo = 'planilla' AND f.iva = 0 AND f.admon > 0
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
        ", [$desde]);

        // La regla de IVA se resuelve por aliado y en bloque (ver IvaService).
        $porAliado = [];
        foreach ($rows as $r) {
            $porAliado[$r->aliado_id][] = $r->cedula;
        }
        $mapa = [];
        foreach ($porAliado as $al => $cedulas) {
            $mapa[$al] = IvaService::mapaPorCedulas((int) $al, $cedulas);
        }

        $out = [];
        foreach ($rows as $r) {
            if (empty($mapa[$r->aliado_id][$r->cedula])) {
                continue;   // no causa IVA: el cero está bien
            }
            $iva = IvaService::calcular((float) $r->base, true);
            $out[] = [
                'tipo' => 'IVA faltante',
                'clave' => "iva:{$r->aliado_id}:{$r->id}",
                'aliado' => $r->aliado_id,
                'monto' => $iva,
                'detalle' => "factura {$r->id} (recibo #{$r->numero_factura}, c.c. {$r->cedula}, {$r->mes}/{$r->anio}): sin IVA, le corresponden $"
                    .number_format($iva, 0, ',', '.'),
                'arreglo' => 'revisar la marca de IVA del cliente o su empresa',
            ];
        }

        return $out;
    }

    private function render(array $hallazgos, int $dias): void
    {
        $this->info("Facturas de los últimos {$dias} días");
        $this->newLine();

        if (! $hallazgos) {
            $this->info('✅ Nada raro: ningún descuadre detectado.');

            return;
        }

        foreach (collect($hallazgos)->groupBy('tipo') as $tipo => $g) {
            $this->line("<comment>{$tipo}</comment> — ".count($g).' caso(s), $'
                .number_format(collect($g)->sum('monto'), 0, ',', '.'));
            foreach ($g->take(10) as $h) {
                $this->line("   aliado {$h['aliado']}: {$h['detalle']}");
            }
            if (count($g) > 10) {
                $this->line('   ... y '.(count($g) - 10).' más');
            }
            $this->line('   arreglo: '.$g->first()['arreglo']);
            $this->newLine();
        }
    }

    private function mensaje(array $nuevos): string
    {
        $total = array_sum(array_column($nuevos, 'monto'));
        $tipos = collect($nuevos)->groupBy('tipo')
            ->map(fn ($g, $t) => count($g)." de {$t}")
            ->implode(', ');

        return 'Facturación: '.count($nuevos).' descuadre(s) nuevo(s) por $'
            .number_format($total, 0, ',', '.')." ({$tipos}). "
            .'Revisar con: php artisan facturas:detectar-descuadres';
    }

    /** @return array<string> */
    private function baseline(): array
    {
        if (! Storage::exists(self::BASELINE)) {
            return [];
        }

        return json_decode(Storage::get(self::BASELINE), true) ?: [];
    }

    private function guardarBaseline(array $claves): void
    {
        Storage::put(self::BASELINE, json_encode(array_values(array_unique($claves))));
    }
}
