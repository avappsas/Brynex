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
     * Gestión ARL: según su propia definición «NO es una planilla mensual, es
     * únicamente el radicado de afiliación y el servicio ante la ARL». Nunca
     * debe aparecer como pendiente de planilla.
     */
    public const MODALIDAD_GESTION_ARL = 15;

    /** Independientes mes actual: el único que cotiza el mes de pago. */
    public const MODALIDAD_INDEP_ACTUAL = 11;

    /**
     * BRYGAR: sus razones sociales son la operación principal de BryNex, así
     * que la validación de cierre las muestra de primeras y deja las de los
     * demás aliados debajo.
     */
    public const ALIADO_PRINCIPAL = 2;

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
     *
     * `$aliadoId` en null recorre TODOS los aliados: el informe es de BryNex,
     * no de uno solo. Con un id se filtra a ese aliado.
     */
    public function resumen(?int $aliadoId, int $mesPago, int $anioPago)
    {
        $p = $this->periodo($mesPago, $anioPago);

        // SQL Server no agrega sobre una subconsulta, así que el EXISTS se
        // resuelve por fila en una tabla derivada y se suma por fuera.
        // Se mantienen las dos cifras: el total de vigentes —que es el número
        // que el aliado reconoce— y cuántos de ellos deben cotizar de verdad
        // este período. El pendiente se mide contra el segundo.
        $porContrato = DB::table('contratos AS c')
            ->where('c.estado', 'vigente')
            ->when($aliadoId, fn ($q) => $q->where('c.aliado_id', $aliadoId))
            ->selectRaw(
                'c.aliado_id,
                 c.razon_social_id,
                 CASE WHEN '.$this->existePlanoSql().' THEN 1 ELSE 0 END AS con_plano,
                 CASE WHEN '.$this->aplicaPlanillaSql().' THEN 1 ELSE 0 END AS aplica',
                array_merge($this->bindingsPlano($p), $this->bindingsAplica($p))
            );

        $filas = DB::query()->fromSub($porContrato, 't')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 't.razon_social_id')
            ->leftJoin('aliados AS a', 'a.id', '=', 't.aliado_id')
            ->selectRaw("
                t.aliado_id,
                t.razon_social_id,
                MAX(ISNULL(a.nombre, '(sin aliado)'))          AS aliado,
                MAX(ISNULL(rs.razon_social, '(sin razón social)')) AS razon_social,
                MAX(rs.nit)  AS nit,
                COUNT(*)     AS vigentes,
                SUM(t.aplica) AS deben_cotizar,
                SUM(CASE WHEN t.aplica = 1 AND t.con_plano = 1 THEN 1 ELSE 0 END) AS con_plano
            ")
            ->groupBy('t.aliado_id', 't.razon_social_id')
            ->get();

        return $this->agruparPorNit($filas, $this->liquidacionesApi($aliadoId, $mesPago, $anioPago));
    }

    /**
     * La misma empresa vive repetida como razón social en varios aliados —cada
     * uno la creó por su lado—, así que el informe global la mostraría tres
     * veces y los totales se leerían mal. Se junta por NIT en una sola fila que
     * suma los contadores y guarda de qué aliados viene.
     *
     * Sin NIT no hay manera de saber si dos filas son la misma empresa: esas
     * quedan cada una por su lado, con su propio id.
     */
    private function agruparPorNit($filas, $liquidaciones)
    {
        return $filas
            ->groupBy(fn ($f) => filled($f->nit) ? 'nit:'.trim((string) $f->nit) : 'rs:'.$f->razon_social_id)
            ->map(function ($grupo, $clave) use ($liquidaciones) {
                $aliados = $grupo->map(function ($f) {
                    $f->aliado_id = (int) $f->aliado_id;
                    $f->vigentes = (int) $f->vigentes;
                    $f->deben_cotizar = (int) $f->deben_cotizar;
                    $f->con_plano = (int) $f->con_plano;
                    $f->pendientes = $f->deben_cotizar - $f->con_plano;
                    $f->sin_planilla = $f->vigentes - $f->deben_cotizar;

                    return $f;
                })->sortByDesc('pendientes')->values();

                // El nombre que manda es el que le puso el aliado principal:
                // los demás suelen tenerla escrita a su manera.
                $principal = $aliados->firstWhere('aliado_id', self::ALIADO_PRINCIPAL) ?? $aliados->first();

                return (object) [
                    'clave' => $clave,
                    'razon_social' => $principal->razon_social,
                    'nit' => $principal->nit,
                    'razon_social_id' => $principal->razon_social_id,
                    'razon_social_ids' => $aliados->pluck('razon_social_id')->map(fn ($i) => (int) $i)->all(),
                    'es_principal' => $aliados->contains('aliado_id', self::ALIADO_PRINCIPAL),
                    'aliados' => $aliados,
                    'vigentes' => $aliados->sum('vigentes'),
                    'deben_cotizar' => $aliados->sum('deben_cotizar'),
                    'con_plano' => $aliados->sum('con_plano'),
                    'pendientes' => $aliados->sum('pendientes'),
                    'sin_planilla' => $aliados->sum('sin_planilla'),
                    'api' => $this->fusionarApi($aliados->pluck('razon_social_id')
                        ->map(fn ($id) => $liquidaciones->get($id))->filter()),
                ];
            })
            ->sort(fn ($a, $b) => [$b->es_principal, $b->pendientes] <=> [$a->es_principal, $a->pendientes])
            ->values();
    }

    /** Las liquidaciones por API de todas las razones sociales del grupo, en una. */
    private function fusionarApi($api)
    {
        if ($api->isEmpty()) {
            return null;
        }

        return (object) [
            'intentos' => $api->sum('intentos'),
            'liquidadas' => $api->sum('liquidadas'),
            'valor_liquidado' => $api->sum('valor_liquidado'),
            'ultima_fecha' => $api->max('ultima_fecha'),
            'operador' => $api->pluck('operador')->filter()->unique()->implode(', ') ?: null,
        ];
    }

    /**
     * Contratos vigentes sin plano del período, con lo mínimo para saber a
     * quién llamar: de qué aliado es, desde cuándo está, qué plan tiene y
     * cuándo fue la última vez que sí entró a una planilla.
     *
     * `$razonSocialIds` acepta la lista completa del grupo, porque una fila del
     * informe puede venir de varias razones sociales con el mismo NIT.
     */
    public function pendientes(?int $aliadoId, int $mesPago, int $anioPago, array|int|null $razonSocialIds = null)
    {
        $p = $this->periodo($mesPago, $anioPago);

        $query = $this->queryDetalle($aliadoId, $razonSocialIds)
            ->whereRaw('NOT '.$this->existePlanoSql(), $this->bindingsPlano($p));

        $this->soloLosQueCotizan($query, $p);

        return $query->selectRaw($this->columnasDetalle().",
                (SELECT MAX(p2.anio_plano * 100 + p2.mes_plano) FROM planos p2
                  WHERE p2.aliado_id = c.aliado_id AND p2.no_identifi = c.cedula
                    AND p2.deleted_at IS NULL AND p2.tipo_reg IN ('planilla','retiro')) AS ultimo_periodo
            ")
            ->orderBy('rs.razon_social')
            ->orderBy('a.nombre')
            ->orderBy('c.cedula')
            ->get();
    }

    /**
     * El otro lado de la moneda: los vigentes a los que este período NO les
     * toca planilla, con el porqué. Es la diferencia entre «vigentes» y «deben
     * cotizar», que sin explicación se lee como gente que se dejó por fuera.
     */
    public function noAplican(?int $aliadoId, int $mesPago, int $anioPago, array|int|null $razonSocialIds = null)
    {
        $p = $this->periodo($mesPago, $anioPago);

        $gestionArl = self::MODALIDAD_GESTION_ARL;

        return $this->queryDetalle($aliadoId, $razonSocialIds)
            ->whereRaw('NOT '.$this->aplicaPlanillaSql(), $this->bindingsAplica($p))
            ->selectRaw($this->columnasDetalle().",
                CASE WHEN c.tipo_modalidad_id = {$gestionArl}
                     THEN 'Gestión ARL'
                     ELSE 'Afiliado dentro del período o después' END AS motivo
            ")
            ->orderBy('rs.razon_social')
            ->orderBy('a.nombre')
            ->orderBy('c.cedula')
            ->get();
    }

    /**
     * Base común de los dos detalles. El cliente se cruza por el aliado del
     * contrato —no por el de la sesión— porque el informe es global.
     */
    private function queryDetalle(?int $aliadoId, array|int|null $razonSocialIds)
    {
        $query = DB::table('contratos AS c')
            ->leftJoin('clientes AS cl', function ($j) {
                $j->on('cl.cedula', '=', 'c.cedula')->on('cl.aliado_id', '=', 'c.aliado_id');
            })
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'c.razon_social_id')
            ->leftJoin('aliados AS a', 'a.id', '=', 'c.aliado_id')
            ->leftJoin('planes_contrato AS pl', 'pl.id', '=', 'c.plan_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->where('c.estado', 'vigente');

        if ($aliadoId) {
            $query->where('c.aliado_id', $aliadoId);
        }

        if ($razonSocialIds !== null) {
            $query->whereIn('c.razon_social_id', (array) $razonSocialIds);
        }

        return $query;
    }

    private function columnasDetalle(): string
    {
        return "c.id AS contrato_id,
                c.cedula,
                c.aliado_id,
                ISNULL(a.nombre, '—') AS aliado,
                c.razon_social_id,
                ISNULL(rs.razon_social, '(sin razón social)') AS razon_social,
                cl.id AS cliente_id,
                LTRIM(RTRIM(ISNULL(cl.primer_nombre,'')+' '+ISNULL(cl.segundo_nombre,'')+' '
                      +ISNULL(cl.primer_apellido,'')+' '+ISNULL(cl.segundo_apellido,''))) AS nombre,
                cl.celular,
                pl.nombre AS plan_nombre,
                tm.tipo_modalidad AS modalidad,
                CONVERT(VARCHAR(10), c.fecha_ingreso, 23) AS fecha_ingreso";
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
        $p = $this->periodo($mesPago, $anioPago);

        $query = DB::table('contratos AS c')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.estado', 'vigente')
            ->whereRaw('NOT '.$this->existePlanoSql(), $this->bindingsPlano($p));

        return $this->soloLosQueCotizan($query, $p);
    }

    /**
     * Deja fuera a los contratos vigentes a los que NO les toca planilla en
     * este período. Sin esto el informe acusa como pendiente a gente que está
     * perfectamente al día, y el número deja de ser creíble.
     *
     * Dos casos, los dos confirmados contra los datos:
     *
     *  1. **Gestión ARL** (modalidad 15): no es una planilla mensual, es solo
     *     el radicado y el servicio ante la ARL. Nunca paga planilla.
     *  2. **El mes de la afiliación no se paga.** La primera planilla es la del
     *     mes siguiente al ingreso — explícito en la modalidad Ingreso-Retiro
     *     («al mes siguiente se paga una planilla de pocos días») y verificado
     *     en los dependientes. Así que si el contrato nació dentro del mes
     *     cubierto, o después, todavía no le corresponde.
     *
     * Los RETIROS sí siguen contando: a quien se retira hay que reportarlo en
     * la planilla, y su plano de retiro es lo que lo da por cubierto.
     */
    private function soloLosQueCotizan($query, array $p)
    {
        return $query->whereRaw($this->aplicaPlanillaSql(), $this->bindingsAplica($p));
    }

    /** La condición de arriba como expresión, para usarla también en un CASE. */
    private function aplicaPlanillaSql(): string
    {
        $gestionArl = self::MODALIDAD_GESTION_ARL;
        $indepActual = self::MODALIDAD_INDEP_ACTUAL;

        // Sin fecha de ingreso no se puede decidir: se deja dentro para que el
        // dato incompleto se note en vez de desaparecer del informe.
        return "(
            (c.tipo_modalidad_id IS NULL OR c.tipo_modalidad_id <> {$gestionArl})
            AND (c.fecha_ingreso IS NULL
                 OR c.fecha_ingreso < CASE WHEN c.tipo_modalidad_id = {$indepActual} THEN ? ELSE ? END)
        )";
    }

    /** Primer día del mes cubierto, según cotice el mes de pago o el vencido. */
    private function bindingsAplica(array $p): array
    {
        return [
            sprintf('%04d-%02d-01', $p['anio_pago'], $p['mes_pago']),
            sprintf('%04d-%02d-01', $p['anio_vencido'], $p['mes_vencido']),
        ];
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
        $lotes = $this->planosSinNumero($aliadoId)
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->leftJoin('facturas AS f', function ($j) {
                $j->on('f.id', '=', 'p.factura_id')->whereNull('f.deleted_at');
            })
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
            // Misma llave que cotizantesDeLotes(), para colgarle el detalle.
            $l->llave = "{$l->razon_social_id}|{$l->anio_plano}|{$l->mes_plano}|{$l->n_plano}";

            return $l;
        });
    }

    /**
     * Planos que de verdad esperan un número de planilla. Es la base de las
     * tres consultas del bloque, en un solo sitio para que no se desincronicen.
     *
     * Deja fuera dos cosas que nunca van a tener número, y que si se listan
     * convierten el bloque en ruido:
     *
     *  - **Gestión ARL**: no es una planilla mensual, solo el radicado y el
     *    servicio ante la ARL. Su pendiente es de facturación, y ahí sí sale,
     *    en «vigentes sin facturar».
     *  - **Planos de cero días**: `PlanoPilaTxtService` exige `num_dias > 0`
     *    para incluir una línea en el archivo, así que un plano de 0 días
     *    jamás llega al operador y jamás recibe número.
     */
    private function planosSinNumero(int $aliadoId)
    {
        return DB::table('planos AS p')
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')
            ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
            ->whereNotNull('p.razon_social_id')
            ->where(function ($q) {
                $q->whereNull('p.numero_planilla')->orWhere('p.numero_planilla', '');
            })
            ->where(function ($q) {
                $q->whereNull('p.tipo_modalidad_id')
                    ->orWhere('p.tipo_modalidad_id', '<>', self::MODALIDAD_GESTION_ARL);
            })
            ->whereRaw('ISNULL(p.num_dias, 0) > 0')
            ->whereRaw('(p.anio_plano * 100 + p.mes_plano) >= ?', [self::PERIODO_MINIMO]);
    }

    /**
     * Los cotizantes de cada tanda sin número, indexados por la misma llave
     * que arma lotesSinConfirmar(). Es el detalle de "quién está ahí dentro":
     * sin esto el informe da un conteo que no se puede auditar.
     */
    public function cotizantesDeLotes(int $aliadoId)
    {
        return $this->planosSinNumero($aliadoId)
            ->leftJoin('facturas AS f', function ($j) {
                $j->on('f.id', '=', 'p.factura_id')->whereNull('f.deleted_at');
            })
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
            ->selectRaw("
                p.id AS plano_id, p.razon_social_id, p.anio_plano, p.mes_plano, p.n_plano,
                p.no_identifi AS cedula, p.tipo_reg, p.num_dias,
                LTRIM(RTRIM(ISNULL(p.primer_nombre,'')+' '+ISNULL(p.segundo_nombre,'')+' '
                      +ISNULL(p.primer_ape,'')+' '+ISNULL(p.segundo_ape,''))) AS nombre,
                tm.tipo_modalidad AS modalidad,
                ISNULL(f.total_ss, 0) AS valor_ss
            ")
            ->orderBy('p.primer_ape')
            ->get()
            ->groupBy(fn ($r) => "{$r->razon_social_id}|{$r->anio_plano}|{$r->mes_plano}|{$r->n_plano}");
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
        $lotes = $this->planosSinNumero($aliadoId)
            ->distinct()
            ->count(DB::raw('CONCAT(p.razon_social_id, \'|\', p.anio_plano, \'|\', p.mes_plano, \'|\', p.n_plano)'));

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
    private function liquidacionesApi(?int $aliadoId, int $mesPago, int $anioPago)
    {
        return DB::table('operador_planillas_api AS a')
            ->leftJoin('operadores_planilla AS op', 'op.id', '=', 'a.operador_planilla_id')
            ->when($aliadoId, fn ($q) => $q->where('a.aliado_id', $aliadoId))
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
