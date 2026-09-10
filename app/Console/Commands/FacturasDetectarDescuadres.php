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
 *   D. Cargo del lote cobrado a cada trabajador — el mismo valor de "Otros" u
 *      "Otros admón" quedó copiado en todas las facturas del lote, así que se
 *      cobró tantas veces como trabajadores. Se reconoce porque el cliente pagó
 *      como si fuera una sola vez y el lote quedó debiendo casi exactamente las
 *      copias de más (Javier Tabares, ago-2026: $5.000 × 5 = $25.000).
 *   E. Saldo peleado con sus propios números — el saldo guardado no es lo
 *      recibido menos el total. Ojo: `valor_prestamo` NO es plata recibida, es
 *      lo que el cliente queda debiendo, así que no entra en la cuenta.
 *      Mientras el saldo y el pago se contradigan, ninguno de los dos sirve
 *      para decidir nada: hay que mirar la consignación real.
 *   F. Anticipo que el saldo no refleja — el cliente entregó plata por
 *      adelantado, la factura la registra en `anticipo_aplicado`, pero su saldo
 *      se calculó sin contarla y por eso figura debiendo. No se arregla sumando
 *      el anticipo al saldo: en muchas el anticipo aplicado es mayor que la
 *      factura entera, así que hacerlo regalaría crédito que el cliente no
 *      tiene. Lo que hay que revisar es a cuál factura se imputó cada peso.
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
            $this->cargoDuplicado($desde, $aliado),
            $this->saldoIncoherente($desde, $aliado),
            $this->anticipoSinReflejar($desde, $aliado),
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
        // El JOIN es solo un pre-filtro barato: sin él la consulta trae miles de
        // facturas sin IVA (la mayoría legítimas) y revienta la memoria. Quién
        // causa IVA de verdad lo decide IvaService más abajo, que es la
        // autoridad: si la regla cambia allá, esto sigue funcionando porque
        // solo acota, nunca concluye.
        $rows = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio,
                   f.admon + ISNULL(f.admin_asesor,0) AS base, f.saldo_proximo
            FROM facturas f
            JOIN clientes cl ON cl.aliado_id = f.aliado_id AND cl.cedula = f.cedula
            LEFT JOIN empresas e ON e.id = cl.cod_empresa AND e.aliado_id = cl.aliado_id
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND f.tipo = 'planilla' AND f.iva = 0 AND f.admon > 0
              AND (
                   (e.id IS NOT NULL AND UPPER(LTRIM(RTRIM(ISNULL(e.iva,'')))) = 'SI')
                OR (e.id IS NULL     AND UPPER(LTRIM(RTRIM(ISNULL(cl.iva,'')))) = 'SI')
              )
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

    /**
     * D. El mismo valor en todas las facturas del lote, y el cliente pagó como
     * si fuera uno solo. Sin ese segundo filtro sonarían los lotes donde de
     * verdad a cada trabajador le toca el mismo cargo.
     */
    private function cargoDuplicado($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.aliado_id, f.numero_factura, COUNT(*) n, MIN(f.mes) mes, MIN(f.anio) anio,
                   MAX(f.otros) otros, MAX(f.otros_admon) otros_admon,
                   SUM(f.total) total, SUM(f.saldo_proximo) saldo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            GROUP BY f.aliado_id, f.numero_factura
            HAVING COUNT(*) > 1
               AND SUM(f.saldo_proximo) < 0
               AND ((MIN(f.otros) = MAX(f.otros) AND MAX(f.otros) > 0)
                 OR (MIN(f.otros_admon) = MAX(f.otros_admon) AND MAX(f.otros_admon) > 0))
        ", [$desde]);

        $out = [];
        foreach ($rows as $r) {
            $valor = (int) $r->otros + (int) $r->otros_admon;
            $cobradoDeMas = $valor * ((int) $r->n - 1);
            $deuda = -(int) $r->saldo;

            // La deuda tiene que parecerse a las copias de más: si el cliente
            // simplemente pagó de menos por otra razón, esto no es el caso.
            if ($cobradoDeMas <= 0 || $deuda < $cobradoDeMas * 0.8 || $deuda > $cobradoDeMas * 1.5) {
                continue;
            }

            $out[] = [
                'tipo' => 'cargo del lote cobrado a cada trabajador',
                'clave' => "duplicado:{$r->aliado_id}:{$r->numero_factura}",
                'aliado' => $r->aliado_id,
                'monto' => $cobradoDeMas,
                'detalle' => "recibo #{$r->numero_factura} ({$r->mes}/{$r->anio}, {$r->n} facturas): $"
                    .number_format($valor, 0, ',', '.').' cobrados '.$r->n.' veces; el cliente quedó debiendo $'
                    .number_format($deuda, 0, ',', '.'),
                'arreglo' => "php artisan facturas:recalcular-lote-otros {$r->aliado_id} {$r->numero_factura} --otros={$r->otros} --otros-admon={$r->otros_admon}",
            ];
        }

        return $out;
    }

    /**
     * E. El saldo guardado le cobra al cliente más de lo que sus propios
     * números dicen, sin anticipos de por medio (esos van al chequeo F, que es
     * otro problema y se arregla de otra forma). Solo se mira en esa dirección a propósito: cuando el saldo
     * favorece al cliente más de lo pagado suele ser un crédito de la empresa
     * que se está consumiendo, y eso el sistema lo graba así queriendo (ver
     * facturar(), saldoEmpresaAplicar). No se arregla con el recalculador:
     * recalcular sobre un pago que también puede estar mal empeora la cosa.
     */
    private function saldoIncoherente($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio, f.estado, f.total,
                   f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado AS recibido,
                   f.valor_prestamo, f.saldo_proximo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND f.saldo_proximo < (f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado - f.total)
              AND f.anticipo_aplicado = 0
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            ORDER BY ABS(f.saldo_proximo - (f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado - f.total)) DESC
        ", [$desde]);

        return array_map(function ($r) {
            $deberia = (int) $r->recibido - (int) $r->total;

            return [
                'tipo' => 'saldo peleado con sus propios números',
                'clave' => "saldo:{$r->aliado_id}:{$r->id}",
                'aliado' => $r->aliado_id,
                'monto' => abs($deberia - (int) $r->saldo_proximo),
                'detalle' => "factura {$r->id} (recibo #{$r->numero_factura}, c.c. {$r->cedula}, {$r->mes}/{$r->anio}, {$r->estado}): "
                    ."saldo dice {$r->saldo_proximo} pero recibido - total da {$deberia}",
                'arreglo' => 'revisar contra la consignación: NO recalcular, el pago tambien puede estar mal',
            ];
        }, $rows);
    }

    /**
     * F. Anticipo que el saldo ignora. Se reporta aparte de E porque la causa y
     * el arreglo son distintos: aquí el dinero del cliente está registrado, lo
     * dudoso es a cuál factura se le imputó.
     */
    private function anticipoSinReflejar($desde, ?int $aliado): array
    {
        $rows = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio, f.total,
                   f.anticipo_aplicado, f.saldo_proximo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND f.anticipo_aplicado > 0
              AND f.saldo_proximo < (f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado - f.total)
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            ORDER BY f.anticipo_aplicado DESC
        ", [$desde]);

        return array_map(fn ($r) => [
            'tipo' => 'anticipo que el saldo no refleja',
            'clave' => "anticipo:{$r->aliado_id}:{$r->id}",
            'aliado' => $r->aliado_id,
            'monto' => (int) $r->anticipo_aplicado,
            'detalle' => "factura {$r->id} (recibo #{$r->numero_factura}, c.c. {$r->cedula}, {$r->mes}/{$r->anio}): "
                .'anticipo de $'.number_format($r->anticipo_aplicado, 0, ',', '.')
                .' sobre una factura de $'.number_format($r->total, 0, ',', '.')
                .", y el saldo dice {$r->saldo_proximo}",
            'arreglo' => 'revisar a cuál factura se imputó el anticipo: NO sumarlo al saldo sin más',
        ], $rows);
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
