<?php

namespace App\Console\Commands;

use App\Models\DataicoConfiguracion;
use App\Services\Dataico\EmisionService;
use App\Services\Dataico\SeleccionFacturasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Emite ante Dataico las facturas pendientes.
 *
 * Lo llama el programador en el cierre del día (configuraciones en modo
 * `diario`) y también sirve a mano para reintentar lo que quedó en error o
 * para revisar el JSON antes de activar nada:
 *
 *   php artisan dataico:emitir --aliado=2 --simular --limite=1
 */
class DataicoEmitir extends Command
{
    protected $signature = 'dataico:emitir
        {--aliado= : Solo este aliado. Sin él, todas las configuraciones en modo diario}
        {--numero= : Solo este numero_factura (requiere --aliado)}
        {--limite= : Tope de facturas de la corrida}
        {--simular : Arma el JSON y lo muestra, sin enviar nada}
        {--ignorar-hora : En el barrido diario, no filtrar por la hora de cierre}
        {--forzar : Emite aunque la configuración esté inactiva (tanda manual)}
        {--mes= : Emite un mes concreto (YYYY-MM), resolviendo la cuenta también desde el legacy}
        {--legacy-cuenta=8 : Id de la cuenta de la emisora en Brygar_BD (solo aliado 2)}';

    protected $description = 'Emite ante Dataico las facturas electrónicas pendientes';

    public function handle(EmisionService $emision): int
    {
        $simular = (bool) $this->option('simular');
        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;

        $configs = $this->configuraciones($simular);

        if ($configs->isEmpty()) {
            $this->warn('No hay configuraciones de Dataico activas y completas para procesar.');

            return self::SUCCESS;
        }

        $huboError = false;

        foreach ($configs as $cfg) {
            $etiqueta = "aliado {$cfg->aliado_id} / razón social {$cfg->razon_social_id}";
            $this->info("── Dataico: {$etiqueta}".($simular ? ' (SIMULACIÓN)' : ''));

            if ($mes = $this->option('mes')) {
                $this->emitirMes($emision, $cfg, $mes, $limite, $simular);

                continue;
            }

            if ($numero = $this->option('numero')) {
                $r = $emision->emitirNumeroFactura($cfg, (int) $numero, $simular);

                if ($r === null) {
                    $this->warn("  La factura {$numero} no califica para emitirse.");

                    continue;
                }

                $this->reportarUno($r, $simular);
                $huboError = $huboError || $r['resultado'] === 'errores';

                continue;
            }

            $resumen = $emision->emitirPendientes($cfg, $limite, $simular);

            $this->line("  intentadas: {$resumen['intentadas']}  "
                      ."enviadas: {$resumen['enviadas']}  "
                      ."errores: {$resumen['errores']}  "
                      ."omitidas: {$resumen['omitidas']}");

            if ($simular && ! empty($resumen['detalle'])) {
                $this->newLine();
                $this->line('  Payload de ejemplo ('.$resumen['detalle'][0]['numero_factura'].'):');
                $this->line(json_encode(
                    $resumen['detalle'][0]['payload'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
            }

            foreach ($resumen['detalle'] as $d) {
                if ($d['resultado'] === 'errores') {
                    $this->error("  #{$d['numero_factura']}: {$d['mensaje']}");
                    $huboError = true;
                }
            }
        }

        return $huboError ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Emite las facturas pendientes de un mes concreto.
     *
     * Para mayo en adelante basta con las consignaciones de Brynex. Para el
     * período migrado no: sus consignaciones quedaron todas en la cuenta 137 y
     * duplicadas siete veces, así que el filtro normal no las ve. La cuenta
     * real la dice `Brygar_BD` en `FACTURACION.Consignacion`, y de ahí se saca
     * la lista sin tocar la tabla dañada.
     *
     * Solo tiene sentido para el aliado 2: `Brygar_BD` es la base de BRYGAR y
     * el id de cuenta significaba algo distinto en la de cada aliado.
     */
    private function emitirMes(EmisionService $emision, DataicoConfiguracion $cfg, string $mes, ?int $limite, bool $simular): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $this->error("El mes debe ir como YYYY-MM; llegó «{$mes}».");

            return;
        }

        $desde = $mes.'-01';
        $hasta = date('Y-m-d', strtotime($desde.' +1 month'));

        $numeros = $this->gruposDelMes($cfg, $desde, $hasta);

        if (empty($numeros)) {
            $this->warn("  {$mes}: no hay facturas de la cuenta de la emisora.");

            return;
        }

        $clasificado = app(SeleccionFacturasService::class)->porNumeros($cfg, $numeros);
        $emitibles = $clasificado['emitibles'];

        $this->line("  {$mes}: {$emitibles->count()} por emitir de ".count($numeros).' que pasaron por la cuenta'
                   .($clasificado['sin_documento']->count() ? '  ('.$clasificado['sin_documento']->count().' retenidas sin documento)' : ''));

        if ($limite) {
            $emitibles = $emitibles->take($limite);
        }

        $ok = 0;
        $mal = 0;

        foreach ($emitibles as $grupo) {
            $r = $emision->emitirGrupo($cfg, $grupo, $simular);

            if ($simular) {
                $this->line('    #'.$r['numero_factura'].'  '.($r['payload']['invoice']['items'][0]['description'] ?? ''));

                continue;
            }

            $r['resultado'] === 'enviadas' ? $ok++ : $mal++;

            if ($r['resultado'] === 'errores') {
                $this->error("    #{$r['numero_factura']}: {$r['mensaje']}");
            }
        }

        if (! $simular) {
            $this->info("  {$mes}: {$ok} emitidas, {$mal} con error.");
        }
    }

    /**
     * Grupos de factura del mes que entraron por la cuenta de la emisora.
     *
     * Une las dos épocas: lo que Brynex registró con su cuenta, y lo que el
     * legacy dice que entró por la cuenta equivalente.
     */
    private function gruposDelMes(DataicoConfiguracion $cfg, string $desde, string $hasta): array
    {
        $numeros = DB::table('facturas as f')
            ->where('f.aliado_id', $cfg->aliado_id)
            ->whereNull('f.deleted_at')
            ->whereDate('f.fecha_pago', '>=', $desde)
            ->whereDate('f.fecha_pago', '<', $hasta)
            ->where(function ($w) use ($cfg) {
                $w->whereExists(fn ($s) => $s->select(DB::raw(1))->from('consignaciones as cs')
                    ->whereColumn('cs.factura_id', 'f.id')->where('cs.banco_cuenta_id', $cfg->banco_cuenta_id))
                    ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('abonos as ab')
                        ->whereColumn('ab.factura_id', 'f.id')->where('ab.banco_cuenta_id', $cfg->banco_cuenta_id));
            })
            ->distinct()
            ->pluck('f.numero_factura')
            ->all();

        // Período migrado: la cuenta la dice el legacy.
        $cuentaLegacy = (string) $this->option('legacy-cuenta');

        if ($cuentaLegacy !== '') {
            try {
                $ids = collect(DB::connection('sqlsrv_legacy')->select(
                    'SELECT Id_Factura id FROM FACTURACION WHERE Consignacion = ? AND Fecha_Pago >= ? AND Fecha_Pago < ?',
                    [$cuentaLegacy, $desde, $hasta]
                ))->pluck('id');

                foreach ($ids->chunk(400) as $ch) {
                    $numeros = array_merge($numeros, DB::table('facturas')
                        ->where('aliado_id', $cfg->aliado_id)->whereNull('deleted_at')
                        ->whereIn('id_legacy', $ch->all())->pluck('numero_factura')->all());
                }
            } catch (\Throwable $e) {
                $this->warn('  no se pudo consultar el legacy: '.$e->getMessage());
            }
        }

        return array_values(array_unique(array_filter($numeros)));
    }

    /**
     * En simulación no se exigen credenciales ni que esté activa: la gracia de
     * `--simular` es poder revisar el JSON ANTES de tener el token y de
     * encender nada. Solo hace falta el criterio de selección.
     */
    private function configuraciones(bool $simular = false)
    {
        $utilizable = fn (DataicoConfiguracion $c) => $simular
            ? ($c->banco_cuenta_id !== null && $c->fecha_inicio !== null)
            : $c->estaCompleta();

        // `activo` gobierna la emisión AUTOMÁTICA. Una tanda manual con
        // --forzar es una persona apretando el botón, y sirve justamente para
        // probar de a poco antes de encender el automático: si se exigiera
        // `activo`, la primera prueba de 10 obligaría a dejar programado el
        // barrido completo de la noche.
        $exigirActivo = ! $simular && ! $this->option('forzar');

        if ($aliado = $this->option('aliado')) {
            return DataicoConfiguracion::where('aliado_id', (int) $aliado)
                ->when($exigirActivo, fn ($q) => $q->where('activo', true))
                ->get()
                ->filter($utilizable)
                ->values();
        }

        // Sin --aliado corre el cierre del día. El programador dispara este
        // comando cada hora y aquí se filtra por la `hora_cierre` de cada
        // configuración, para que cada razón social pueda cerrar a su hora sin
        // tener que tocar el Kernel.
        $horaActual = now('America/Bogota')->format('H');

        return DataicoConfiguracion::operativas()
            ->filter(fn (DataicoConfiguracion $c) => $c->modo === DataicoConfiguracion::MODO_DIARIO)
            ->filter(function (DataicoConfiguracion $c) use ($horaActual) {
                if ($this->option('ignorar-hora')) {
                    return true;
                }

                return substr((string) $c->hora_cierre, 0, 2) === $horaActual;
            })
            ->values();
    }

    private function reportarUno(array $r, bool $simular): void
    {
        if ($simular) {
            $this->line(json_encode(
                $r['payload'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return;
        }

        $r['resultado'] === 'enviadas'
            ? $this->info("  #{$r['numero_factura']}: {$r['mensaje']}")
            : $this->error("  #{$r['numero_factura']}: {$r['mensaje']}");
    }
}
