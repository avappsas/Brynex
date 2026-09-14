<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\SaldoAjuste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Los residuos del reparto proporcional.
 *
 * Cuando el pago de un lote se reparte entre las facturas, el redondeo deja
 * pesos sueltos: un lote de 23 personas termina con alguien a favor $100 y
 * otro debiendo $94. Esos centavos no son crédito de nadie —nadie los va a
 * reclamar ni a cobrar— pero el sistema los arrastra mes a mes y ensucian la
 * pantalla de saldos, donde se mezclan con los créditos que sí hay que revisar.
 *
 * Este comando los da por consumidos (SaldoAjuste), sin tocar las facturas:
 * solo los lotes cuyo saldo a favor ENTERO cabe bajo el tope, para no rozar
 * un lote donde el residuo convive con un crédito de verdad.
 */
class SaldosMarcarRuido extends Command
{
    protected $signature = 'saldos:marcar-ruido
                            {aliado : ID del aliado}
                            {--tope=500 : Hasta cuánto se considera residuo del reparto}
                            {--dry-run  : Mostrar sin escribir}
                            {--force    : Aplicar sin preguntar}';

    protected $description = 'Da por consumidos los saldos a favor que son residuo del reparto proporcional (por debajo del tope).';

    /** Los mismos estados en que una factura le reconoce saldo al cliente. */
    private const ESTADOS = ['pagada', 'prestamo', 'abono'];

    public function handle(): int
    {
        $aliadoId = (int) $this->argument('aliado');
        $tope = (int) $this->option('tope');
        $dry = (bool) $this->option('dry-run');

        $conSaldo = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->whereIn('estado', self::ESTADOS)
            ->where('saldo_proximo', '>', 0)
            ->get(['id', 'cedula', 'numero_factura', 'mes', 'anio', 'saldo_proximo']);

        // Un lote entra solo si TODO su saldo a favor es residuo: si además
        // carga un crédito real, el ajuste tendría que decidir cuánto de cada
        // cosa se lleva, y eso no lo decide un comando.
        $ruido = $conSaldo->groupBy('numero_factura')
            ->filter(fn ($lote) => $lote->sum(fn ($f) => (int) $f->saldo_proximo) < $tope)
            ->flatten(1);

        // La factura no se toca al ajustar, así que su saldo sigue ahí y una
        // segunda corrida volvería a pedirlo — esta vez mordiendo el crédito de
        // verdad del cliente. Las que ya tienen ajuste quedan fuera.
        $yaAjustadas = SaldoAjuste::where('aliado_id', $aliadoId)
            ->get(['id', 'detalle'])
            ->flatMap(fn ($a) => collect(is_array($a->detalle) ? $a->detalle : [])->pluck('factura_id'))
            ->filter()
            ->flip();

        $ruido = $ruido->reject(fn ($f) => $yaAjustadas->has($f->id));

        if ($ruido->isEmpty()) {
            $this->info("No quedan residuos por marcar en el aliado {$aliadoId}.");

            return Command::SUCCESS;
        }

        if ($ruido->isEmpty()) {
            $this->info("No hay saldos por debajo de \${$tope} en el aliado {$aliadoId}.");

            return Command::SUCCESS;
        }

        // Por cliente: el ajuste vive a nivel de cédula, no de factura.
        $porCliente = $ruido->groupBy('cedula');
        $ajustesPrevios = SaldoAjuste::mapaPorCedulas($aliadoId, $porCliente->keys());

        $netos = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->whereIn('estado', self::ESTADOS)
            ->whereIn('cedula', $porCliente->keys())
            ->groupBy('cedula')
            ->selectRaw('cedula, SUM(saldo_proximo) AS neto')
            ->pluck('neto', 'cedula');

        $plan = [];
        foreach ($porCliente as $cedula => $facturas) {
            $pedido = (int) $facturas->sum(fn ($f) => (int) $f->saldo_proximo);
            // Nunca más de lo que el cliente tiene hoy: otras facturas suyas
            // pueden estar en rojo y ya haberse comido el residuo.
            $disponible = max(0, (int) ($netos[$cedula] ?? 0) - (int) ($ajustesPrevios[(string) $cedula] ?? 0));
            $valor = min($pedido, $disponible);

            if ($valor > 0) {
                $plan[] = ['cedula' => (string) $cedula, 'valor' => $valor, 'facturas' => $facturas];
            }
        }

        if ($plan === []) {
            // Las facturas siguen ahí, pero sus dueños ya no tienen crédito:
            // otra factura en rojo se comió el residuo. No hay nada que ajustar.
            $this->info('Los residuos que quedan son de clientes sin saldo a favor vivo: nada que marcar.');

            return Command::SUCCESS;
        }

        $total = array_sum(array_column($plan, 'valor'));
        $this->info($dry ? '🔍 DRY-RUN — no se escribe nada' : '⚙️  Escribiendo en producción');
        $this->line("Aliado {$aliadoId}: {$ruido->count()} factura(s) en ".$ruido->groupBy('numero_factura')->count()
            .' lote(s), '.count($plan)." cliente(s), \$".number_format($total, 0, ',', '.'));
        $this->newLine();

        $this->table(
            ['cédula', 'facturas', 'ajuste'],
            collect($plan)->sortByDesc('valor')->take(15)->map(fn ($p) => [
                $p['cedula'],
                $p['facturas']->map(fn ($f) => "#{$f->numero_factura} ({$f->mes}/{$f->anio}) \$".(int) $f->saldo_proximo)->implode(', '),
                '$'.number_format($p['valor'], 0, ',', '.'),
            ])->all()
        );
        if (count($plan) > 15) {
            $this->line('… y '.(count($plan) - 15).' cliente(s) más');
        }

        if ($dry) {
            return Command::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Marcar estos saldos como consumidos?', false)) {
            $this->warn('Cancelado.');

            return Command::SUCCESS;
        }

        $motivo = SaldoAjuste::MOTIVO_ALIADO." — residuo del reparto (menor a \${$tope})";

        DB::transaction(function () use ($plan, $aliadoId, $motivo) {
            foreach ($plan as $p) {
                SaldoAjuste::create([
                    'aliado_id' => $aliadoId,
                    'cedula' => $p['cedula'],
                    'valor' => $p['valor'],
                    'motivo' => $motivo,
                    'usuario_id' => null,   // corrección de mantenimiento, no de una persona
                    'detalle' => $p['facturas']->map(fn ($f) => [
                        'factura_id' => $f->id,
                        'numero_factura' => $f->numero_factura,
                        'periodo' => "{$f->mes}/{$f->anio}",
                        'saldo' => (int) $f->saldo_proximo,
                    ])->values()->all(),
                ]);
            }
        });

        Bitacora::registrar(
            accion: 'ajustar',
            modelo: 'SaldoAjuste',
            registroId: null,
            descripcion: count($plan).' saldo(s) a favor por $'.number_format($total, 0, ',', '.')
                .' dados por consumidos: residuo del reparto proporcional',
            detalle: ['tope' => (int) $this->option('tope'), 'clientes' => array_column($plan, 'cedula')],
            alidoId: $aliadoId
        );

        $this->info('✅ '.count($plan).' cliente(s) ajustado(s) por $'.number_format($total, 0, ',', '.').'.');

        return Command::SUCCESS;
    }
}
