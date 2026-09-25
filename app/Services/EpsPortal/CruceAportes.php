<?php

namespace App\Services\EpsPortal;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que BryNex sabe de un trabajador y un período, para interpretar lo que
 * reporta el portal de una entidad.
 *
 * Vive aparte porque la pregunta es siempre la misma aunque la entidad cambie:
 * ¿tiene contrato en esa empresa?, ¿está retirado?, ¿se le hizo planilla ese
 * mes?, ¿esa planilla se pagó? De esas cuatro respuestas sale si hay que pagar,
 * reclamar o corregir una afiliación.
 */
class CruceAportes
{
    /** El contrato más reciente de esa cédula en esa razón social. */
    public static function contratoDe(string $nit, string $documento): ?object
    {
        return DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('rs.nit', preg_replace('/\D/', '', $nit))
            ->where('c.cedula', $documento)
            ->orderByDesc('c.id')
            ->first(['c.id', 'c.aliado_id', 'c.razon_social_id', 'c.fecha_ingreso', 'c.fecha_retiro', 'c.estado']);
    }

    /**
     * Los planos de esa cédula en esos períodos ('AAAA-MM'), de cualquier empresa.
     *
     * Sin filtrar por empresa a propósito: que la planilla haya salido por otra
     * razón social es en sí mismo el hallazgo, y explica moras que no son moras.
     *
     * @param  array<string>  $periodos
     */
    public static function planosDe(string $documento, array $periodos): \Illuminate\Support\Collection
    {
        if (! $periodos) {
            return collect();
        }

        $filas = DB::table('planos as p')
            ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'p.razon_social_id')
            ->where('p.no_identifi', $documento)
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($periodos) {
                foreach ($periodos as $periodo) {
                    [$anio, $mes] = array_pad(explode('-', $periodo), 2, '1');
                    $q->orWhere(fn ($w) => $w->where('p.anio_plano', (int) $anio)->where('p.mes_plano', (int) $mes));
                }
            })
            ->get(['p.id', 'p.numero_planilla', 'p.mes_plano', 'p.anio_plano', 'rs.nit', 'rs.razon_social']);

        return collect($filas);
    }

    /** La fecha en que se pagó esa planilla, si quedó registrada. */
    public static function pagoDe(?string $numeroPlanilla): ?string
    {
        if (! $numeroPlanilla) {
            return null;
        }

        $fila = DB::table('planillas_pago_operador')->where('numero_planilla', $numeroPlanilla)->first(['fecha_pago']);

        return $fila ? Carbon::parse($fila->fecha_pago)->format('d/m/Y') : null;
    }

    /** Un aliado que tenga esa razón social, para cuando no hay contrato. */
    public static function aliadoDe(string $nit): ?int
    {
        return DB::table('razones_sociales')->where('nit', preg_replace('/\D/', '', $nit))->orderBy('id')->value('aliado_id');
    }

    public static function razonSocialDe(string $nit): ?int
    {
        return DB::table('razones_sociales')->where('nit', preg_replace('/\D/', '', $nit))->orderBy('id')->value('id');
    }

    /** '2026-08' → 'agosto de 2026', que es como se lee una tarea. */
    public static function mesEnLetras(string $periodo): string
    {
        [$anio, $mes] = array_pad(explode('-', $periodo), 2, '1');

        return Carbon::createFromDate((int) $anio, (int) $mes, 1)->locale('es')->isoFormat('MMMM [de] YYYY');
    }

    /** $12.345 */
    public static function plata(int|float $valor): string
    {
        return '$'.number_format((float) $valor, 0, ',', '.');
    }
}
