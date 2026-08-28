<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige las incapacidades que se registraron como "Pagada a Razón Social" con
 * la forma de pago "Directo al cliente".
 *
 * Esa combinación existía en la pantalla de gestión y creaba un abono
 * 'entrada_incapacidad' — es decir, plata entrando a la caja del aliado — cuando
 * lo que pasó fue lo contrario: la EPS/ARL/AFP le consignó al afiliado y a la
 * razón social nunca le llegó nada. El resultado eran entradas falsas que
 * inflaban el Canal 5 del informe financiero (28 prórrogas de una misma
 * incapacidad en BRYGAR, ago-2026: $15.2M que nunca existieron).
 *
 * La corrección deja las cosas como si se hubiera usado el flujo correcto:
 *   · el abono pasa a 'pago_directo_entidad' (cierra el saldo por cobrar, pero
 *     no figura en el Canal 5);
 *   · la incapacidad queda en 'pagada_afiliado' / estado_pago 'pagado_afiliado';
 *   · la gestión que hizo el cambio queda apuntando a 'pagada_afiliado' para que
 *     el historial no siga diciendo que la razón social recibió el dinero.
 *
 * También reclasifica la otra cara del mismo problema: los pagos directos que sí
 * se registraron por el flujo correcto pero con el tipo 'pago_cliente', que el
 * Canal 5 lee como plata saliendo de la caja y deja saldos negativos falsos.
 *
 * El botón que causaba esto ya no existe y el backend rechaza la combinación,
 * así que este comando se corre una sola vez.
 */
class CorregirEntradasIncapacidadDirectas extends Command
{
    protected $signature = 'incapacidades:corregir-entradas-directas
                            {--ejecutar : Aplica los cambios. Sin este flag solo muestra lo que haría}
                            {--aliado= : Limitar a un aliado}';

    protected $description = 'Reclasifica como pago directo de la entidad los abonos que quedaron mal tipificados y descuadran el Canal 5';

    /** Marca que dejó el flujo viejo en la observación del abono. */
    private const MARCA = 'Pago directo al cliente';

    /** Marca del flujo correcto, que antes usaba el tipo 'pago_cliente'. */
    private const MARCA_DIRECTO = 'Pago directo de la entidad';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        $q = DB::table('abonos_incapacidades')
            ->where('tipo', 'entrada_incapacidad')
            ->where('observacion', 'like', self::MARCA.'%');

        if ($this->option('aliado')) {
            $q->where('aliado_id', (int) $this->option('aliado'));
        }

        $abonos = $q->orderBy('aliado_id')->orderBy('id')->get();

        if ($abonos->isEmpty()) {
            $this->info('No hay entradas falsas por corregir.');
            $this->corregirPagosClienteDirectos($ejecutar);

            return self::SUCCESS;
        }

        $this->info(($ejecutar ? 'CORRIGIENDO ' : 'SIMULACIÓN — ').$abonos->count().' abono(s):');
        $this->newLine();

        $filas = [];
        $omitidos = [];
        $totalPorAliado = [];

        foreach ($abonos as $a) {
            $inc = DB::table('incapacidades')->where('id', $a->incapacidad_id)->first();

            if (! $inc) {
                $omitidos[] = "Abono #{$a->id}: la incapacidad #{$a->incapacidad_id} ya no existe.";

                continue;
            }

            // Una consignación significa que sí hubo un movimiento bancario real:
            // eso ya no es un pago directo y hay que mirarlo a mano.
            if ($a->consignacion_id) {
                $omitidos[] = "Abono #{$a->id} (inc. #{$inc->id}): tiene consignación #{$a->consignacion_id}, revisar a mano.";

                continue;
            }

            // Si ya se cerró, cambiarle el estado le quitaría el cierre. El abono
            // sí se corrige — la plata sigue sin haber entrado —, el estado no.
            $tocarEstado = $inc->estado !== 'cierre_exitoso';

            $filas[] = [
                $a->aliado_id,
                $inc->id,
                $inc->cedula_usuario,
                number_format((float) $a->valor, 0, ',', '.'),
                $inc->estado,
                $tocarEstado ? 'pagada_afiliado' : $inc->estado.' (se conserva)',
            ];

            $totalPorAliado[$a->aliado_id] = ($totalPorAliado[$a->aliado_id] ?? 0) + (float) $a->valor;

            if (! $ejecutar) {
                continue;
            }

            DB::transaction(function () use ($a, $inc, $tocarEstado) {
                $obs = 'Pago directo de la entidad al afiliado — no ingresó a la razón social'
                    ." — Incapacidad #{$inc->id} (corregido: se había registrado como entrada a la razón social)";

                DB::table('abonos_incapacidades')->where('id', $a->id)->update([
                    'tipo' => 'pago_directo_entidad',
                    'banco_cuenta_id' => null,
                    'observacion' => $obs,
                    'updated_at' => now(),
                ]);

                $cambios = [
                    'estado_pago' => 'pagado_afiliado',
                    'valor_pago' => $a->valor,
                    'fecha_pago' => $a->fecha,
                    'pagado_a' => 'cliente',
                    'detalle_pago' => $obs,
                    'updated_at' => now(),
                ];

                if ($tocarEstado) {
                    $cambios['estado'] = 'pagada_afiliado';
                }

                DB::table('incapacidades')->where('id', $inc->id)->update($cambios);

                // La gestión que registró el cambio: queda apuntando al estado
                // correcto para que el historial y el cierre exitoso cuadren.
                $g = DB::table('gestiones_incapacidad')
                    ->where('incapacidad_id', $inc->id)
                    ->where('estado_nuevo', 'pagada_razon_social')
                    ->whereNull('revertida_at')
                    ->orderByDesc('id')
                    ->first();

                if ($g) {
                    DB::table('gestiones_incapacidad')->where('id', $g->id)->update([
                        'estado_nuevo' => 'pagada_afiliado',
                        'estado_resultado' => 'pagada_afiliado',
                        'respuesta' => trim((string) $g->respuesta.' [Corregido: la entidad pagó directamente al afiliado; '
                            .'se había registrado como pago a la razón social.]'),
                    ]);
                }
            });
        }

        $this->table(
            ['Aliado', 'Incapacidad', 'Cédula', 'Valor', 'Estado actual', 'Estado nuevo'],
            $filas
        );

        foreach ($totalPorAliado as $aliadoId => $total) {
            $this->line("Aliado {$aliadoId}: se sacan del Canal 5 $".number_format($total, 0, ',', '.'));
        }

        if ($omitidos) {
            $this->newLine();
            $this->warn('Omitidos:');
            foreach ($omitidos as $o) {
                $this->warn('  · '.$o);
            }
        }

        $this->newLine();
        if ($ejecutar) {
            $this->info('Listo. '.count($filas).' abono(s) corregidos.');
        } else {
            $this->comment('Simulación: no se cambió nada. Corre con --ejecutar para aplicarlo.');
        }

        $this->corregirPagosClienteDirectos($ejecutar);

        return self::SUCCESS;
    }

    /**
     * Segunda forma del mismo problema, por el lado contrario.
     *
     * El flujo correcto — estado 'Pagada al afiliado' con pago directo — sí
     * registraba el hecho, pero con el tipo 'pago_cliente', que el Canal 5 lee
     * como plata que salió de la caja del aliado. Como no hubo entrada que la
     * respalde, esas incapacidades aparecían con un saldo negativo que nunca
     * existió. Se reclasifican al tipo propio.
     *
     * Un pago_cliente CON gasto asociado sí movió caja (es un anticipo al
     * afiliado mientras la entidad paga) y se deja como está.
     */
    private function corregirPagosClienteDirectos(bool $ejecutar): void
    {
        $q = DB::table('abonos_incapacidades')
            ->where('tipo', 'pago_cliente')
            ->where('observacion', 'like', self::MARCA_DIRECTO.'%');

        if ($this->option('aliado')) {
            $q->where('aliado_id', (int) $this->option('aliado'));
        }

        $abonos = $q->orderBy('aliado_id')->orderBy('id')->get();

        if ($abonos->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info(($ejecutar ? 'CORRIGIENDO ' : 'SIMULACIÓN — ').$abonos->count()
            .' pago(s) directo(s) tipificados como pago al cliente:');

        $filas = [];
        $omitidos = [];

        foreach ($abonos as $a) {
            $tuvoGasto = DB::table('gastos')
                ->where('incapacidad_id', $a->incapacidad_id)
                ->whereIn('tipo', \App\Models\Gasto::TIPOS_INCAPACIDAD)
                ->exists();

            if ($tuvoGasto) {
                $omitidos[] = "Abono #{$a->id} (inc. #{$a->incapacidad_id}): tiene gasto asociado, "
                    .'sí movió caja. Se deja como pago al cliente.';

                continue;
            }

            $filas[] = [
                $a->aliado_id,
                $a->incapacidad_id,
                number_format((float) $a->valor, 0, ',', '.'),
                'pago_cliente → pago_directo_entidad',
            ];

            if ($ejecutar) {
                DB::table('abonos_incapacidades')->where('id', $a->id)->update([
                    'tipo' => 'pago_directo_entidad',
                    'updated_at' => now(),
                ]);
            }
        }

        if ($filas) {
            $this->table(['Aliado', 'Incapacidad', 'Valor', 'Cambio'], $filas);
        }

        foreach ($omitidos as $o) {
            $this->warn('  · '.$o);
        }
    }
}
