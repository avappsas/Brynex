<?php

namespace App\Console\Commands;

use App\Models\DataicoConfiguracion;
use App\Services\Dataico\EmisionService;
use Illuminate\Console\Command;

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
        {--forzar : Emite aunque la configuración esté inactiva (tanda manual)}';

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
