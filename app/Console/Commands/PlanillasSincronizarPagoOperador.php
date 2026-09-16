<?php

namespace App\Console\Commands;

use App\Models\Plano;
use App\Services\EnlaceInformeIndividualService;
use App\Services\SuaporteApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Llena la fecha y hora exactas de pago de las planillas pagadas por ARUS o
 * Simple, una por planilla.
 *
 * Solo el informe individual del operador trae la hora; se baja el de una
 * persona de cada planilla (que de paso queda guardado en disco) y se lee el
 * encabezado. El operador de cada planilla sale del gasto `pago_planilla`, así
 * que no se prueba a ciegas contra operadores por los que no se pagó.
 *
 * Las planillas que se descarguen o envíen después la registran solas; esto es
 * para las anteriores.
 */
class PlanillasSincronizarPagoOperador extends Command
{
    protected $signature = 'planillas:sincronizar-pago-operador
                            {--aliado= : Solo este aliado}
                            {--planilla= : Solo este número de planilla}
                            {--desde= : Planos desde este período, AAAA-MM (por defecto, dos meses atrás)}
                            {--limite=50 : Cuántas planillas como máximo}
                            {--reintentar : Volver a consultar las que ya se marcaron como no encontradas, inválidas o sin acceso}
                            {--dry-run : Listar qué se consultaría, sin tocar el operador}';

    protected $description = 'Guarda la fecha y hora exactas de pago de cada planilla, leída del informe del operador';

    public function handle(EnlaceInformeIndividualService $servicio): int
    {
        $desde = $this->option('desde') ?: now()->subMonths(2)->format('Y-m');
        [$anioDesde, $mesDesde] = array_map('intval', explode('-', $desde));

        $operadores = DB::table('operadores_planilla')
            ->whereIn('codigo', array_keys(SuaporteApiService::HOSTS))
            ->pluck('id', 'nombre');

        $pendientes = DB::table('gastos AS g')
            ->join('planos AS p', function ($j) {
                $j->on('p.numero_planilla', '=', 'g.numero_planilla')->on('p.aliado_id', '=', 'g.aliado_id');
            })
            ->leftJoin('planillas_pago_operador AS po', function ($j) {
                $j->on('po.numero_planilla', '=', 'g.numero_planilla')->on('po.aliado_id', '=', 'g.aliado_id');
            })
            ->leftJoin('planillas_verificacion_operador AS pv', function ($j) {
                $j->on('pv.numero_planilla', '=', 'g.numero_planilla')->on('pv.aliado_id', '=', 'g.aliado_id');
            })
            ->when(! $this->option('reintentar'), fn ($q) => $q->where(
                fn ($w) => $w->whereNull('pv.estado')->orWhereNotIn('pv.estado', ['no_encontrada', 'invalida', 'sin_acceso'])
            ))
            ->where('g.tipo', 'pago_planilla')
            ->whereIn('g.pagado_a', $operadores->keys())
            ->whereNull('po.id')
            // Sin credenciales del operador no hay cómo bajar el informe.
            ->whereExists(function ($q) use ($operadores) {
                $q->from('operadores_credenciales AS oc')
                    ->whereColumn('oc.aliado_id', 'g.aliado_id')
                    ->whereIn('oc.operador_planilla_id', $operadores->values())
                    ->whereNull('oc.deleted_at');
            })
            ->whereNull('p.deleted_at')
            ->whereRaw('(p.anio_plano * 100 + p.mes_plano) >= ?', [$anioDesde * 100 + $mesDesde])
            ->when($this->option('aliado'), fn ($q, $a) => $q->where('g.aliado_id', (int) $a))
            ->when($this->option('planilla'), fn ($q, $n) => $q->where('g.numero_planilla', $n))
            ->groupBy('g.aliado_id', 'g.numero_planilla', 'g.pagado_a')
            ->orderByRaw('MIN(p.id) DESC')
            ->limit((int) $this->option('limite'))
            ->get([
                'g.aliado_id', 'g.numero_planilla', 'g.pagado_a',
                // Cualquier persona de la planilla sirve para leer el encabezado.
                DB::raw('MIN(p.id) AS plano_id'),
            ]);

        if ($pendientes->isEmpty()) {
            $this->info('No hay planillas por consultar.');

            return self::SUCCESS;
        }

        $this->info("Planillas por consultar: {$pendientes->count()}");
        $ok = $fallidas = 0;

        foreach ($pendientes as $fila) {
            $etiqueta = "[{$fila->aliado_id}] {$fila->numero_planilla} · {$fila->pagado_a}";

            if ($this->option('dry-run')) {
                $this->line("  [seco] {$etiqueta}");
                continue;
            }

            $plano = Plano::with('razonSocial')->find($fila->plano_id);
            $r = $servicio->obtener($plano, (int) $operadores[$fila->pagado_a]);

            $fecha = DB::table('planillas_pago_operador')
                ->where('aliado_id', $fila->aliado_id)
                ->where('numero_planilla', $fila->numero_planilla)
                ->value('fecha_pago');

            if ($r['success'] && $fecha) {
                $this->line("  ✔ {$etiqueta}: pagada {$fecha}");
                $ok++;
            } else {
                $this->warn("  – {$etiqueta}: ".($r['message'] ?? 'el informe no trae la fecha de pago'));
                $fallidas++;
            }
        }

        $this->newLine();
        $this->info("Guardadas: {$ok}   ·   Sin fecha: {$fallidas}");

        return self::SUCCESS;
    }
}
