<?php

namespace App\Services\Caja;

use App\Models\Tarea;
use Illuminate\Support\Facades\DB;

/**
 * A quién hay que preguntarle a la caja por sus bloqueos de subsidio.
 *
 * Consultar los 799 afiliados con Comfandi cuesta horas, porque el portal
 * responde de a un trabajador. Pero el bloqueo más común —"en espera de
 * aportes"— lo produce algo que BryNex ya sabe: que la planilla se pagó tarde.
 * Así que la revisión diaria se limita a los sospechosos:
 *
 *  1. **Pago con mora** en los últimos meses: es el que la caja retiene.
 *  2. **Tarea de subsidio abierta**: hay que mirarlos hasta que se libere.
 *  3. **Afiliados nuevos**: su primer subsidio es el que más se traba.
 *
 * Una vez al mes, después del ciclo de la caja (los bloqueos salen hacia el 27
 * o 28), se corre el barrido completo con `todos()` y se cubre lo que la lista
 * de sospechosos no vio.
 */
class SubsidioCandidatosService
{
    /** Cuántos meses atrás se mira la mora. */
    private const MESES_MORA = 3;

    private const CAJA = 'COMFANDI';

    /**
     * Los sospechosos del día, agrupados por NIT de la razón social.
     *
     * @return array<string, array<int, array{cedula:string, contrato_id:int, nombre:string, motivo:string}>>
     */
    public function candidatos(int $aliadoId, ?string $nit = null): array
    {
        $porMora = $this->base($aliadoId, $nit)
            ->join('facturas as f', function ($j) {
                $j->on('f.contrato_id', '=', 'c.id')->whereNull('f.deleted_at')->where('f.mora', '>', 0);
            })
            ->where(function ($q) {
                $desde = now()->subMonths(self::MESES_MORA);
                $q->where('f.anio', '>', $desde->year)
                    ->orWhere(fn ($i) => $i->where('f.anio', $desde->year)->where('f.mes', '>=', $desde->month));
            })
            ->distinct()
            ->get(['c.id as contrato_id', 'c.cedula', 'rs.nit', 'cl.primer_nombre', 'cl.primer_apellido'])
            ->map(fn ($f) => (array) $f + ['motivo' => 'pago con mora']);

        $conTarea = $this->base($aliadoId, $nit)
            ->join('tareas as t', function ($j) use ($aliadoId) {
                $j->on('t.cedula', '=', 'c.cedula')->where('t.aliado_id', $aliadoId)
                    ->whereNull('t.deleted_at')->whereIn('t.estado', Tarea::ESTADOS_ACTIVOS)
                    ->where('t.llave_auto', 'like', 'comfandi:%');
            })
            ->distinct()
            ->get(['c.id as contrato_id', 'c.cedula', 'rs.nit', 'cl.primer_nombre', 'cl.primer_apellido'])
            ->map(fn ($f) => (array) $f + ['motivo' => 'tarea abierta']);

        $nuevos = $this->base($aliadoId, $nit)
            ->whereDate('c.fecha_ingreso', '>=', now()->subMonths(2)->startOfMonth())
            ->distinct()
            ->get(['c.id as contrato_id', 'c.cedula', 'rs.nit', 'cl.primer_nombre', 'cl.primer_apellido'])
            ->map(fn ($f) => (array) $f + ['motivo' => 'afiliado nuevo']);

        return $this->agrupar($porMora->concat($conTarea)->concat($nuevos));
    }

    /**
     * Todos los vigentes con esa caja: el barrido completo del mes.
     *
     * @return array<string, array<int, array{cedula:string, contrato_id:int, nombre:string, motivo:string}>>
     */
    public function todos(int $aliadoId, ?string $nit = null): array
    {
        return $this->agrupar(
            $this->base($aliadoId, $nit)
                ->get(['c.id as contrato_id', 'c.cedula', 'rs.nit', 'cl.primer_nombre', 'cl.primer_apellido'])
                ->map(fn ($f) => (array) $f + ['motivo' => 'barrido completo'])
        );
    }

    /**
     * Contratos vigentes con la caja, ya afiliados (el ingreso no es futuro) y
     * con NIT de verdad: sin NIT no hay empresa que consultar en el portal.
     */
    private function base(int $aliadoId, ?string $nit)
    {
        return DB::table('contratos as c')
            ->join('cajas as k', 'k.id', '=', 'c.caja_id')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->leftJoin('clientes as cl', function ($j) use ($aliadoId) {
                $j->on('cl.cedula', '=', 'c.cedula')->where('cl.aliado_id', $aliadoId);
            })
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereDate('c.fecha_ingreso', '<=', today())
            ->where('k.nombre', 'like', '%'.self::CAJA.'%')
            ->where('rs.es_independiente', false)
            ->whereNotNull('rs.nit')
            ->whereRaw("LEN(LTRIM(RTRIM(rs.nit))) >= 9")
            ->when($nit, fn ($q) => $q->where('rs.nit', preg_replace('/\D/', '', $nit)));
    }

    /** @return array<string, array<int, array>> */
    private function agrupar($filas): array
    {
        return collect($filas)
            ->unique('contrato_id')
            ->map(fn ($f) => [
                'cedula' => (string) $f['cedula'],
                'contrato_id' => (int) $f['contrato_id'],
                'nit' => (string) $f['nit'],
                'nombre' => trim(($f['primer_nombre'] ?? '').' '.($f['primer_apellido'] ?? '')),
                'motivo' => $f['motivo'],
            ])
            ->groupBy('nit')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }
}
