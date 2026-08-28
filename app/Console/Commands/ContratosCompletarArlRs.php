<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le pone a los contratos la ARL que ya define su razón social.
 *
 * En una razón social de empresa la ARL la manda la empresa (`arl_nit`), y el
 * formulario del contrato deja ese selector bloqueado. Un `<select disabled>`
 * no viaja en el POST, así que `arl_id` se guardaba vacío por más que en
 * pantalla se viera la ARL correcta: la ficha quedaba en rojo pidiendo una ARL
 * que nadie podía escoger.
 *
 * Las planillas nunca se vieron afectadas —la ARL efectiva se resuelve desde la
 * razón social, ver `App\Traits\ResuelveArlEfectiva` y `Plano::resolverArlSnapshot`—
 * pero el dato del contrato contradecía lo que muestra la ficha.
 *
 * Solo toca contratos que cumplan todo esto:
 *   - `arl_id` vacío,
 *   - plan que incluye ARL,
 *   - razón social de empresa (`es_independiente` = 0) con `arl_nit` que cruza
 *     con una ARL registrada.
 *
 * Las razones sociales de independientes quedan fuera a propósito: ahí conviven
 * afiliados de varias ARL y la que manda es la del contrato, no la de la RS.
 *
 * Sin `--ejecutar` solo reporta.
 */
class ContratosCompletarArlRs extends Command
{
    protected $signature = 'contratos:completar-arl-rs
        {--aliado= : Solo los contratos de este aliado}
        {--estado=vigente : Estado de los contratos a revisar (vigente, retirado, todos)}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Completa arl_id en los contratos cuya ARL la define la razón social';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $estado = (string) $this->option('estado');
        $ejecutar = (bool) $this->option('ejecutar');

        $contratos = DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->join('planes_contrato as p', 'p.id', '=', 'c.plan_id')
            ->join('arls as a', 'a.nit', '=', 'rs.arl_nit')
            ->whereNull('c.arl_id')
            ->where('p.incluye_arl', 1)
            ->where('rs.es_independiente', 0)
            ->when($aliadoId, fn ($q) => $q->where('c.aliado_id', $aliadoId))
            ->when($estado !== 'todos', fn ($q) => $q->where('c.estado', $estado))
            ->select(
                'c.id', 'c.aliado_id', 'c.cedula', 'c.estado',
                'rs.razon_social', 'rs.arl_nit',
                'a.id as arl_id', 'a.nombre_arl'
            )
            ->orderBy('c.aliado_id')
            ->orderBy('c.id')
            ->get();

        if ($contratos->isEmpty()) {
            $this->info('No hay contratos sin ARL cuya razón social la defina.');

            return self::SUCCESS;
        }

        $this->info('Contratos por completar: '.$contratos->count());

        foreach ($contratos->groupBy('aliado_id') as $al => $grupo) {
            $this->line("  aliado {$al}: ".$grupo->count()
                .' ('.$grupo->pluck('nombre_arl')->countBy()->map(fn ($n, $arl) => "{$arl} × {$n}")->implode(', ').')');
        }

        if ($this->getOutput()->isVerbose()) {
            $this->table(
                ['Contrato', 'Aliado', 'Cédula', 'Estado', 'Razón social', 'ARL'],
                $contratos->map(fn ($c) => [
                    $c->id, $c->aliado_id, $c->cedula, $c->estado, $c->razon_social, $c->nombre_arl,
                ])->toArray()
            );
        }

        if (! $ejecutar) {
            $this->comment('Simulación. Con --ejecutar se escriben los cambios (-v para ver el detalle).');

            return self::SUCCESS;
        }

        $completados = 0;

        DB::transaction(function () use ($contratos, &$completados) {
            foreach ($contratos as $c) {
                DB::table('contratos')->where('id', $c->id)->update([
                    'arl_id' => $c->arl_id,
                    'updated_at' => now(),
                ]);

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Contrato',
                    registroId: (int) $c->id,
                    descripcion: "ARL {$c->nombre_arl} tomada de la razón social {$c->razon_social} (Cédula: {$c->cedula}).",
                    detalle: ['arl_id' => ['antes' => null, 'despues' => (int) $c->arl_id]],
                    alidoId: (int) $c->aliado_id
                );

                $completados++;
            }
        });

        $this->info("Contratos completados: {$completados}.");

        return self::SUCCESS;
    }
}
