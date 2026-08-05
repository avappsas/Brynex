<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cuadre de cierre de un período de planilla. Sirve a dos informes distintos:
 *
 *  - **Validación de cierre** (solo BryNex): qué contratos vigentes se
 *    quedaron por fuera de la planilla — `resumen()` / `pendientes()`.
 *  - **Cierre de operación** (del aliado): qué le falta cerrar del mes —
 *    `lotesSinConfirmar()`, `vigentesSinFacturar()`, `afiliacionesDelMes()`.
 *
 * Comparten la traducción de período, que es la parte fácil de equivocar.
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
     * Desde qué período tiene sentido exigir número de planilla (AAAAMM).
     *
     * El campo se empezó a diligenciar de verdad en abril de 2026: antes hay
     * ~1.000 planos sin número que vienen del arranque del módulo y que nadie
     * va a resolver. Listarlos convertiría el informe en ruido y taparía los
     * pocos pendientes reales.
     */
    public const PERIODO_MINIMO = 202604;

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

    /** Contratos vigentes del aliado sin plano del período. */
    private function queryPendientes(int $aliadoId, int $mesPago, int $anioPago)
    {
        return DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existePlanoSql(), $this->bindingsPlano($this->periodo($mesPago, $anioPago)));
    }

    // ── Cierre de operación (informe del aliado) ─────────────────────────

    /**
     * Tandas de planilla sin número: o no se han liquidado, o se liquidaron y
     * falta registrar la confirmación del pago (que es lo que estampa
     * `planos.numero_planilla`, ver PlanoPagoController::confirmarPago).
     *
     * Se cruza con `operador_planillas_api` para distinguir los dos casos: si
     * hay liquidación validada, la tanda ya tiene número en Enlace y solo falta
     * confirmarla acá.
     */
    public function lotesSinConfirmar(int $aliadoId)
    {
        $lotes = DB::table('planos AS p')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->leftJoin('facturas AS f', function ($j) {
                $j->on('f.id', '=', 'p.factura_id')->whereNull('f.deleted_at');
            })
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')
            ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
            ->whereNotNull('p.razon_social_id')
            ->where(function ($q) {
                $q->whereNull('p.numero_planilla')->orWhere('p.numero_planilla', '');
            })
            ->whereRaw('(p.anio_plano * 100 + p.mes_plano) >= ?', [self::PERIODO_MINIMO])
            ->selectRaw("
                p.razon_social_id, p.anio_plano, p.mes_plano, p.n_plano,
                MAX(ISNULL(rs.razon_social, '(sin razón social)')) AS razon_social,
                COUNT(*) AS cotizantes,
                SUM(ISNULL(f.total_ss, 0)) AS valor_ss,
                MIN(CASE WHEN p.tipo_modalidad_id = 11 THEN 1 ELSE 0 END) AS todos_independientes
            ")
            ->groupBy('p.razon_social_id', 'p.anio_plano', 'p.mes_plano', 'p.n_plano')
            ->orderByDesc('p.anio_plano')->orderByDesc('p.mes_plano')->orderBy('p.n_plano')
            ->get();

        $api = $this->liquidacionesPorLote($aliadoId);

        return $lotes->map(function ($l) use ($api) {
            // `operador_planillas_api` guarda el mes de PAGO; el plano guarda el
            // mes vencido salvo en independientes (ver periodo()).
            $mesPago = $l->todos_independientes
                ? (int) $l->mes_plano
                : ((int) $l->mes_plano === 12 ? 1 : (int) $l->mes_plano + 1);
            $anioPago = ($l->todos_independientes || (int) $l->mes_plano !== 12)
                ? (int) $l->anio_plano
                : (int) $l->anio_plano + 1;

            $l->mes_pago = $mesPago;
            $l->anio_pago = $anioPago;
            $l->api = $api->get("{$l->razon_social_id}|{$anioPago}|{$mesPago}|{$l->n_plano}");
            $l->cotizantes = (int) $l->cotizantes;

            return $l;
        });
    }

    /**
     * Contratos vigentes sin factura del mes. Se mide contra `facturas` y no
     * contra `planos` a propósito: aquí el pendiente es de facturación, un paso
     * antes del archivo plano.
     */
    public function vigentesSinFacturar(int $aliadoId, int $mes, int $anio)
    {
        return $this->queryContratos($aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existeFacturaSql(), [$mes, $anio])
            ->orderBy('rs.razon_social')->orderBy('c.cedula')
            ->get();
    }

    /**
     * Afiliaciones del mes con algo pendiente de cobrar. Dos cosas distintas
     * que el aliado necesita ver juntas:
     *
     *  - `sin_factura`: se afilió y no se le generó ninguna factura.
     *  - `sin_cobro_afiliacion`: sí se le facturó la seguridad social, pero el
     *    valor de afiliación quedó en cero — plata que se dejó de cobrar y que
     *    no aparece en ningún otro lado.
     */
    public function afiliacionesDelMes(int $aliadoId, int $mes, int $anio)
    {
        return $this->queryContratos($aliadoId)
            ->whereMonth('c.fecha_ingreso', $mes)
            ->whereYear('c.fecha_ingreso', $anio)
            ->where(function ($q) {
                $q->whereRaw('NOT '.$this->existeFacturaSql(true))
                    ->orWhereRaw('NOT '.$this->existeFacturaSql(true, 'f.afiliacion > 0'));
            })
            ->selectRaw('CASE WHEN NOT '.$this->existeFacturaSql(true).' THEN 1 ELSE 0 END AS sin_factura')
            ->orderBy('rs.razon_social')->orderBy('c.fecha_ingreso')
            ->get();
    }

    /**
     * Los tres contadores del cierre de operación, sin traer las filas.
     * Es lo que pinta la tarjeta del hub de informes.
     *
     * @return array{lotes: int, vigentes: int, afiliaciones: int, total: int}
     */
    public function contadoresOperacion(int $aliadoId, int $mes, int $anio): array
    {
        $lotes = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->whereIn('tipo_reg', ['planilla', 'retiro'])
            ->whereNotNull('razon_social_id')
            ->where(function ($q) {
                $q->whereNull('numero_planilla')->orWhere('numero_planilla', '');
            })
            ->whereRaw('(anio_plano * 100 + mes_plano) >= ?', [self::PERIODO_MINIMO])
            ->distinct()
            ->count(DB::raw('CONCAT(razon_social_id, \'|\', anio_plano, \'|\', mes_plano, \'|\', n_plano)'));

        $vigentes = DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existeFacturaSql(), [$mes, $anio])
            ->count();

        $afiliaciones = DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->whereMonth('c.fecha_ingreso', $mes)
            ->whereYear('c.fecha_ingreso', $anio)
            ->whereRaw('NOT '.$this->existeFacturaSql(true, 'f.afiliacion > 0'))
            ->count();

        return [
            'lotes' => $lotes,
            'vigentes' => $vigentes,
            'afiliaciones' => $afiliaciones,
            'total' => $lotes + $vigentes + $afiliaciones,
        ];
    }

    /** Base común de los listados de contratos: quién es y a quién llamar. */
    private function queryContratos(int $aliadoId)
    {
        return DB::table('contratos AS c')
            ->leftJoin('clientes AS cl', function ($j) use ($aliadoId) {
                $j->on('cl.cedula', '=', 'c.cedula')->where('cl.aliado_id', '=', $aliadoId);
            })
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'c.razon_social_id')
            ->leftJoin('planes_contrato AS pl', 'pl.id', '=', 'c.plan_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->where('c.aliado_id', $aliadoId)
            ->selectRaw("
                c.id AS contrato_id, c.cedula, c.estado,
                ISNULL(rs.razon_social, '(sin razón social)') AS razon_social,
                LTRIM(RTRIM(ISNULL(cl.primer_nombre,'')+' '+ISNULL(cl.segundo_nombre,'')+' '
                      +ISNULL(cl.primer_apellido,'')+' '+ISNULL(cl.segundo_apellido,''))) AS nombre,
                cl.celular,
                pl.nombre AS plan_nombre,
                tm.tipo_modalidad AS modalidad,
                CONVERT(VARCHAR(10), c.fecha_ingreso, 23) AS fecha_ingreso
            ");
    }

    /**
     * EXISTS contra `facturas` del contrato. `$delContrato` busca cualquier
     * factura suya (para afiliaciones, que se cobran una sola vez y no
     * necesariamente en el mes del ingreso); si no, exige mes y año.
     */
    private function existeFacturaSql(bool $delContrato = false, string $extra = ''): string
    {
        $periodo = $delContrato ? '' : ' AND f.mes = ? AND f.anio = ?';

        return "EXISTS (
            SELECT 1 FROM facturas f
            WHERE f.contrato_id = c.id AND f.deleted_at IS NULL{$periodo}
            ".($extra ? "AND {$extra}" : '').'
        )';
    }

    /** Liquidaciones validadas del aliado, indexadas por lote. */
    private function liquidacionesPorLote(int $aliadoId)
    {
        return DB::table('operador_planillas_api AS a')
            ->leftJoin('operadores_planilla AS op', 'op.id', '=', 'a.operador_planilla_id')
            ->where('a.aliado_id', $aliadoId)
            ->whereNull('a.deleted_at')
            ->where('a.estado', 'validada')
            ->get(['a.razon_social_id', 'a.anio', 'a.mes', 'a.n_plano', 'a.numero_planilla',
                'a.valor_total', 'a.url_pago', 'a.updated_at', 'op.nombre AS operador'])
            ->keyBy(fn ($r) => "{$r->razon_social_id}|{$r->anio}|{$r->mes}|{$r->n_plano}");
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
