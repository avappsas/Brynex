<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cuadre de cierre de un período de planilla: qué contratos vigentes se
 * quedaron por fuera.
 *
 * Nace de las advertencias `eo.val.2.270` de Enlace ("no se reportó novedad de
 * retiro y no se encuentra reportado en esta planilla"): el operador compara
 * contra la planilla pagada del período anterior y reclama por todo el que
 * desapareció. Mientras el mes va corriendo eso es normal —la nómina se liquida
 * en tandas—, pero al cerrar el mes el que siga sin plano ya no es una tanda
 * pendiente: es un retiro que nunca se registró, y esa persona se quedó sin
 * seguridad social sin que nadie lo note.
 *
 * Un contrato se considera cubierto si tiene plano de tipo `planilla` o
 * `retiro` en el período; las filas de `afiliacion` no cuentan, porque no van
 * al archivo plano (mismo criterio que PlanoPilaTxtService).
 */
class CierrePeriodoService
{
    /**
     * Traduce el mes de PAGO al período que guardan los planos.
     *
     * Los independientes (modalidad 11) guardan el mes de pago; el resto
     * guarda el mes vencido, el anterior al de pago.
     *
     * @return array{mes_pago: int, anio_pago: int, mes_vencido: int, anio_vencido: int}
     */
    public function periodo(int $mesPago, int $anioPago): array
    {
        return [
            'mes_pago' => $mesPago,
            'anio_pago' => $anioPago,
            'mes_vencido' => $mesPago === 1 ? 12 : $mesPago - 1,
            'anio_vencido' => $mesPago === 1 ? $anioPago - 1 : $anioPago,
        ];
    }

    /**
     * Una fila por razón social con contratos vigentes: cuántos entraron a
     * planilla, cuántos quedaron pendientes y cómo va la liquidación por API.
     */
    public function resumen(int $aliadoId, int $mesPago, int $anioPago)
    {
        $p = $this->periodo($mesPago, $anioPago);

        // SQL Server no agrega sobre una subconsulta, así que el EXISTS se
        // resuelve por fila en una tabla derivada y se suma por fuera.
        $porContrato = DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->selectRaw(
                'c.razon_social_id, CASE WHEN '.$this->existePlanoSql().' THEN 1 ELSE 0 END AS con_plano',
                $this->bindingsPlano($p)
            );

        $filas = DB::query()->fromSub($porContrato, 't')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 't.razon_social_id')
            ->selectRaw("
                t.razon_social_id,
                MAX(ISNULL(rs.razon_social, '(sin razón social)')) AS razon_social,
                MAX(rs.nit)     AS nit,
                COUNT(*)        AS vigentes,
                SUM(t.con_plano) AS con_plano
            ")
            ->groupBy('t.razon_social_id')
            ->get();

        $liquidaciones = $this->liquidacionesApi($aliadoId, $mesPago, $anioPago);

        return $filas->map(function ($f) use ($liquidaciones) {
            $f->vigentes = (int) $f->vigentes;
            $f->con_plano = (int) $f->con_plano;
            $f->pendientes = $f->vigentes - $f->con_plano;
            $f->api = $liquidaciones->get($f->razon_social_id);

            return $f;
        })->sortByDesc('pendientes')->values();
    }

    /**
     * Contratos vigentes sin plano del período, con lo mínimo para saber a
     * quién llamar: desde cuándo está, qué plan tiene y cuándo fue la última
     * vez que sí entró a una planilla.
     */
    public function pendientes(int $aliadoId, int $mesPago, int $anioPago, ?int $razonSocialId = null)
    {
        $p = $this->periodo($mesPago, $anioPago);

        $query = DB::table('contratos AS c')
            ->leftJoin('clientes AS cl', function ($j) use ($aliadoId) {
                $j->on('cl.cedula', '=', 'c.cedula')->where('cl.aliado_id', '=', $aliadoId);
            })
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'c.razon_social_id')
            ->leftJoin('planes_contrato AS pl', 'pl.id', '=', 'c.plan_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existePlanoSql(), $this->bindingsPlano($p));

        if ($razonSocialId) {
            $query->where('c.razon_social_id', $razonSocialId);
        }

        return $query->selectRaw("
                c.id AS contrato_id,
                c.cedula,
                c.razon_social_id,
                ISNULL(rs.razon_social, '(sin razón social)') AS razon_social,
                LTRIM(RTRIM(ISNULL(cl.primer_nombre,'')+' '+ISNULL(cl.segundo_nombre,'')+' '
                      +ISNULL(cl.primer_apellido,'')+' '+ISNULL(cl.segundo_apellido,''))) AS nombre,
                cl.celular,
                pl.nombre AS plan_nombre,
                tm.tipo_modalidad AS modalidad,
                CONVERT(VARCHAR(10), c.fecha_ingreso, 23) AS fecha_ingreso,
                (SELECT MAX(p2.anio_plano * 100 + p2.mes_plano) FROM planos p2
                  WHERE p2.aliado_id = c.aliado_id AND p2.no_identifi = c.cedula
                    AND p2.deleted_at IS NULL AND p2.tipo_reg IN ('planilla','retiro')) AS ultimo_periodo
            ")
            ->orderBy('rs.razon_social')
            ->orderBy('c.cedula')
            ->get();
    }

    /** Solo el conteo de una razón social, para el aviso de /admin/planos. */
    public function contarPendientes(int $aliadoId, int $razonSocialId, int $mesPago, int $anioPago): int
    {
        return $this->queryPendientes($aliadoId, $mesPago, $anioPago)
            ->where('c.razon_social_id', $razonSocialId)
            ->count();
    }

    /** Conteo de todo el aliado, para la tarjeta del hub de informes. */
    public function pendientesTotal(int $aliadoId, int $mesPago, int $anioPago): int
    {
        return $this->queryPendientes($aliadoId, $mesPago, $anioPago)->count();
    }

    /** Contratos vigentes del aliado sin plano del período. */
    private function queryPendientes(int $aliadoId, int $mesPago, int $anioPago)
    {
        return DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existePlanoSql(), $this->bindingsPlano($this->periodo($mesPago, $anioPago)));
    }

    // ── Internos ─────────────────────────────────────────────────────────

    /**
     * EXISTS correlacionado contra `planos`, con el desfase de período por
     * modalidad. Se cruza por cédula y no por `contrato_id` a propósito: si el
     * contrato se renovó a mitad de período, el plano quedó colgado del
     * anterior y aun así la persona sí entró a la planilla.
     */
    private function existePlanoSql(): string
    {
        return "EXISTS (
            SELECT 1 FROM planos p
            WHERE p.aliado_id       = c.aliado_id
              AND p.no_identifi     = c.cedula
              AND p.razon_social_id = c.razon_social_id
              AND p.deleted_at IS NULL
              AND p.tipo_reg IN ('planilla','retiro')
              AND (
                    (c.tipo_modalidad_id = 11 AND p.mes_plano = ? AND p.anio_plano = ?)
                 OR (ISNULL(c.tipo_modalidad_id, 0) <> 11 AND p.mes_plano = ? AND p.anio_plano = ?)
              )
        )";
    }

    private function bindingsPlano(array $p): array
    {
        return [$p['mes_pago'], $p['anio_pago'], $p['mes_vencido'], $p['anio_vencido']];
    }

    /**
     * Liquidaciones por API del período, por razón social: cuántas planillas y
     * cuál fue la última. Es lo que responde "¿hasta dónde va este cierre?".
     */
    private function liquidacionesApi(int $aliadoId, int $mesPago, int $anioPago)
    {
        return DB::table('operador_planillas_api AS a')
            ->leftJoin('operadores_planilla AS op', 'op.id', '=', 'a.operador_planilla_id')
            ->where('a.aliado_id', $aliadoId)
            ->where('a.anio', $anioPago)
            ->where('a.mes', $mesPago)
            ->whereNull('a.deleted_at')
            ->selectRaw("
                a.razon_social_id,
                COUNT(*) AS intentos,
                SUM(CASE WHEN a.estado = 'validada' THEN 1 ELSE 0 END) AS liquidadas,
                SUM(CASE WHEN a.estado = 'validada' THEN ISNULL(a.valor_total, 0) ELSE 0 END) AS valor_liquidado,
                MAX(a.updated_at) AS ultima_fecha,
                MAX(op.nombre)    AS operador
            ")
            ->groupBy('a.razon_social_id')
            ->get()
            ->keyBy('razon_social_id');
    }
}
