<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\BancoCuenta;
use App\Models\Factura;
use App\Models\PagoAsesor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComisionesController extends Controller
{
    // Corte histórico: solo facturas desde mayo 2026 en adelante
    private const CORTE_MES  = 7;
    private const CORTE_ANIO = 2026;

    /**
     * Una afiliación está sin distribuir cuando no se le asignó nada a nadie.
     * La utilidad queda fuera a propósito: la factura recién creada trae ahí
     * todo el valor, y eso es exactamente lo que falta repartir.
     */
    private const SQL_SIN_DISTRIBUIR = "(ISNULL(f.dist_asesor, 0) + ISNULL(f.dist_retiro, 0) + ISNULL(f.dist_encargado, 0) + ISNULL(f.dist_admon, 0)) = 0";

    /**
     * Categorías de la liquidación. Ingreso-Retiro (12) y Gestión ARL (15) se separan de las
     * afiliaciones normales porque, aunque se facturan como afiliación, el asesor cobra por
     * ellas todos los meses: mezclarlas inflaba el renglón de "Afiliaciones".
     *
     * Se decide por la modalidad del contrato, así que aplica igual al histórico. Es un cambio
     * SOLO de presentación: el total y el saldo se calculan exactamente como antes.
     */
    private const SQL_CATEGORIA = "CASE
        WHEN c.tipo_modalidad_id = 12 THEN 'ingreso_retiro'
        WHEN c.tipo_modalidad_id = 15 THEN 'gestion_arl'
        WHEN f.tipo = 'afiliacion'    THEN 'afiliaciones'
        ELSE 'planillas'
    END";

    /** Etiqueta, icono y color de cada categoría, para la vista. */
    public const CATEGORIAS = [
        'afiliaciones'   => ['label' => 'Afiliaciones',   'icono' => '🤝', 'color' => '#a78bfa'],
        'planillas'      => ['label' => 'Planillas',      'icono' => '📋', 'color' => '#38bdf8'],
        'ingreso_retiro' => ['label' => 'Ingreso-Retiro', 'icono' => '🔄', 'color' => '#fbbf24'],
        'gestion_arl'    => ['label' => 'Gestión ARL',    'icono' => '🦺', 'color' => '#34d399'],
    ];

    private function aliadoId(): int
    {
        return (int) session('aliado_id_activo');
    }

    private function checkAcceso(): void
    {
        if (! Auth::user()->can('comisiones.ver')) {
            abort(403, 'No tienes permiso para «Ver comisiones (Comisiones de asesores)».');
        }
    }

    /** Condición SQL para aplicar el corte histórico */
    private function condicionCorte(string $prefijo = 'f'): string
    {
        return "({$prefijo}.anio > " . self::CORTE_ANIO . " OR ({$prefijo}.anio = " . self::CORTE_ANIO . " AND {$prefijo}.mes >= " . self::CORTE_MES . "))";
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /admin/informes/comisiones
    // Vista principal: seleccionar asesor, ver consolidado + detalle
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $this->checkAcceso();
        $aid     = $this->aliadoId();
        $mes     = (int) $request->input('mes',     now()->month);
        $anio    = (int) $request->input('anio',    now()->year);
        $asesorId = $request->input('asesor_id', '');

        // Cargar los asesores del aliado (incluyendo inactivos que tengan comisiones o pagos en el periodo)
        $asesoresConMovimientos = DB::table('facturas as f')
            ->join('contratos as c', 'c.id', '=', 'f.contrato_id')
            ->where('f.aliado_id', $aid)
            ->whereNull('f.deleted_at')
            ->where('f.mes', $mes)
            ->where('f.anio', $anio)
            ->where(function ($q) {
                $q->where('f.dist_asesor', '>', 0)
                  ->orWhere('f.admin_asesor', '>', 0);
            })
            ->pluck('c.asesor_id')
            ->unique()
            ->toArray();

        $asesoresConPagos = DB::table('pagos_asesores')
            ->where('aliado_id', $aid)
            ->where('periodo_mes', $mes)
            ->where('periodo_anio', $anio)
            ->pluck('asesor_id')
            ->unique()
            ->toArray();

        $idsAsesoresAdicionales = array_filter(array_unique(array_merge($asesoresConMovimientos, $asesoresConPagos)));

        $asesoresReales = Asesor::where('aliado_id', $aid)
            ->where(function ($q) use ($idsAsesoresAdicionales) {
                $q->where('activo', true)
                  ->orWhereIn('id', $idsAsesoresAdicionales);
            })
            ->orderBy('nombre')
            ->get();

        // Buscar usuarios que han sido encargados en facturas de afiliación pagadas con dist_encargado > 0
        $usuariosEncargadosIds = DB::table('facturas as f')
            ->join('contratos as c', 'c.id', '=', 'f.contrato_id')
            ->where('f.aliado_id', $aid)
            ->whereNull('f.deleted_at')
            ->whereNotNull('f.fecha_pago')
            ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
            ->where('f.dist_encargado', '>', 0)
            ->whereNotNull('c.encargado_id')
            ->pluck('c.encargado_id')
            ->unique()
            ->toArray();

        $usuariosEncargados = \App\Models\User::whereIn('id', $usuariosEncargadosIds)->get();

        $asesores = collect();
        foreach ($asesoresReales as $ar) {
            $asesores->push((object)[
                'id'      => 'asesor_' . $ar->id,
                'real_id' => $ar->id,
                'tipo'    => 'asesor',
                'nombre'  => $ar->nombre . ($ar->activo ? '' : ' (Inactivo)'),
                'cedula'  => $ar->cedula,
            ]);
        }
        foreach ($usuariosEncargados as $ue) {
            $asesores->push((object)[
                'id'      => 'encargado_' . $ue->id,
                'real_id' => $ue->id,
                'tipo'    => 'encargado',
                'nombre'  => $ue->nombre . ' (Encargado)',
                'cedula'  => $ue->cedula ?? 0,
            ]);
        }

        // Si se seleccionó un asesor/encargado, cargar detalle
        $asesor       = null;
        $facturas     = collect();
        $consolidado  = [];
        $pagos        = collect();
        $saldoTotal   = 0;

        $realId = 0;
        $tipoSeleccionado = 'asesor';

        if ($asesorId) {
            if (str_starts_with($asesorId, 'asesor_')) {
                $realId = (int) str_replace('asesor_', '', $asesorId);
                $tipoSeleccionado = 'asesor';
            } elseif (str_starts_with($asesorId, 'encargado_')) {
                $realId = (int) str_replace('encargado_', '', $asesorId);
                $tipoSeleccionado = 'encargado';
            } else {
                $realId = (int) $asesorId;
                $tipoSeleccionado = 'asesor';
            }
        }

        if ($realId) {
            $asesor = $asesores->firstWhere('id', $asesorId) ?? $asesores->firstWhere('real_id', $realId);
            $corte  = $this->condicionCorte('f');

            if ($tipoSeleccionado === 'asesor') {
                // ── Facturas del período seleccionado con comisión del asesor ─
                $facturas = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->leftJoin('clientes AS cl', function ($j) use ($aid) {
                        $j->on('cl.cedula', '=', 'f.cedula')->where('cl.aliado_id', $aid);
                    })
                    ->leftJoin('empresas AS em', 'em.id', '=', DB::raw(
                        "(SELECT TOP 1 cl2.cod_empresa FROM clientes cl2 WHERE cl2.cedula = f.cedula AND cl2.aliado_id = {$aid})"
                    ))
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.asesor_id', $realId)
                    ->where('f.mes', $mes)
                    ->where('f.anio', $anio)
                    ->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('f.tipo', 'afiliacion')->where('f.dist_asesor', '>', 0);
                        })->orWhere(function ($q2) {
                            $q2->where('f.tipo', 'planilla')->where('f.admin_asesor', '>', 0);
                        });
                    })
                    ->selectRaw("
                        f.id,
                        f.numero_factura,
                        f.tipo,
                        f.mes,
                        f.anio,
                        f.estado,
                        f.cedula,
                        CONVERT(VARCHAR(10), f.fecha_pago, 120) AS fecha_pago,
                        CASE f.tipo
                            WHEN 'afiliacion' THEN f.dist_asesor
                            ELSE f.admin_asesor
                        END AS valor_comision,
                        ".self::SQL_CATEGORIA." AS categoria,
                        LTRIM(RTRIM(
                            ISNULL(cl.primer_nombre,'') + ' ' +
                            ISNULL(cl.segundo_nombre,'') + ' ' +
                            ISNULL(cl.primer_apellido,'') + ' ' +
                            ISNULL(cl.segundo_apellido,'')
                        )) AS nombre_cliente,
                        ISNULL(em.empresa, '—') AS empresa_nombre
                    ")
                    ->orderBy('f.numero_factura')
                    ->get();

                // ── Consolidado del período ───────────────────────────────────
                $resumenPeriodo = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.asesor_id', $realId)
                    ->where('f.mes', $mes)
                    ->where('f.anio', $anio)
                    ->selectRaw("
                        ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN f.dist_asesor ELSE 0 END), 0) AS afiliaciones,
                        ISNULL(SUM(CASE WHEN f.tipo = 'planilla'   THEN f.admin_asesor ELSE 0 END), 0) AS planillas
                    ")
                    ->first();

                // Desglose por categoría: los MISMOS pesos del resumen de arriba, repartidos en
                // 4 renglones. La suma de las 4 es idéntica a afiliaciones + planillas.
                $porCategoria = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.asesor_id', $realId)
                    ->where('f.mes', $mes)
                    ->where('f.anio', $anio)
                    ->selectRaw(self::SQL_CATEGORIA . " AS categoria,
                        ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN f.dist_asesor ELSE f.admin_asesor END), 0) AS valor")
                    ->groupBy(DB::raw(self::SQL_CATEGORIA))
                    ->pluck('valor', 'categoria');

                // Pagos del período
                $pagosPeriodo = PagoAsesor::where('aliado_id', $aid)
                    ->where('asesor_id', $realId)
                    ->where('periodo_mes', $mes)
                    ->where('periodo_anio', $anio)
                    ->sum('valor');

                $consolidado = [
                    'afiliaciones' => (int) ($resumenPeriodo->afiliaciones ?? 0),
                    'planillas'    => (int) ($resumenPeriodo->planillas    ?? 0),
                    'total'        => (int) ($resumenPeriodo->afiliaciones ?? 0) + (int) ($resumenPeriodo->planillas ?? 0),
                    'pagado'       => (int) $pagosPeriodo,
                    'saldo'        => ((int) ($resumenPeriodo->afiliaciones ?? 0) + (int) ($resumenPeriodo->planillas ?? 0)) - (int) $pagosPeriodo,
                    // Solo presentación: no entra en total ni en saldo.
                    'categorias'   => collect(self::CATEGORIAS)
                        ->map(fn ($meta, $clave) => $meta + ['valor' => (int) ($porCategoria[$clave] ?? 0)])
                        ->all(),
                ];

                // ── Saldo acumulado total (desde mayo 2025) ──────────────────
                $totalGanado = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.asesor_id', $realId)
                    ->whereRaw($this->condicionCorte('f'))
                    ->selectRaw("
                        ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN f.dist_asesor ELSE 0 END), 0) +
                        ISNULL(SUM(CASE WHEN f.tipo = 'planilla'   THEN f.admin_asesor ELSE 0 END), 0) AS total
                    ")
                    ->value('total');

                $totalPagado = PagoAsesor::where('aliado_id', $aid)
                    ->where('asesor_id', $realId)
                    ->whereRaw("(periodo_anio > " . self::CORTE_ANIO . " OR (periodo_anio = " . self::CORTE_ANIO . " AND periodo_mes >= " . self::CORTE_MES . "))")
                    ->sum('valor');

                $saldoTotal = max(0, (int) $totalGanado - (int) $totalPagado);

                // ── Historial de pagos del período ───────────────────────────
                $pagos = PagoAsesor::with('bancoCuenta', 'usuario')
                    ->where('aliado_id', $aid)
                    ->where('asesor_id', $realId)
                    ->where('periodo_mes', $mes)
                    ->where('periodo_anio', $anio)
                    ->orderByDesc('fecha')
                    ->get();
            } else {
                // Consulta para Encargado (Usuario)
                $facturas = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->leftJoin('clientes AS cl', function ($j) use ($aid) {
                        $j->on('cl.cedula', '=', 'f.cedula')->where('cl.aliado_id', $aid);
                    })
                    ->leftJoin('empresas AS em', 'em.id', '=', DB::raw(
                        "(SELECT TOP 1 cl2.cod_empresa FROM clientes cl2 WHERE cl2.cedula = f.cedula AND cl2.aliado_id = {$aid})"
                    ))
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.encargado_id', $realId)
                    ->where('f.tipo', 'afiliacion')
                    ->where('f.dist_encargado', '>', 0)
                    ->where('f.mes', $mes)
                    ->where('f.anio', $anio)
                    ->selectRaw("
                        f.id,
                        f.numero_factura,
                        f.tipo,
                        f.mes,
                        f.anio,
                        f.estado,
                        f.cedula,
                        CONVERT(VARCHAR(10), f.fecha_pago, 120) AS fecha_pago,
                        f.dist_encargado AS valor_comision,
                        LTRIM(RTRIM(
                            ISNULL(cl.primer_nombre,'') + ' ' +
                            ISNULL(cl.segundo_nombre,'') + ' ' +
                            ISNULL(cl.primer_apellido,'') + ' ' +
                            ISNULL(cl.segundo_apellido,'')
                        )) AS nombre_cliente,
                        ISNULL(em.empresa, '—') AS empresa_nombre
                    ")
                    ->orderBy('f.numero_factura')
                    ->get();

                $resumenPeriodo = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.encargado_id', $realId)
                    ->where('f.tipo', 'afiliacion')
                    ->where('f.mes', $mes)
                    ->where('f.anio', $anio)
                    ->selectRaw("
                        ISNULL(SUM(f.dist_encargado), 0) AS afiliaciones
                    ")
                    ->first();

                $pagosPeriodo = PagoAsesor::where('aliado_id', $aid)
                    ->where('encargado_usuario_id', $realId)
                    ->where('periodo_mes', $mes)
                    ->where('periodo_anio', $anio)
                    ->sum('valor');

                $consolidado = [
                    'afiliaciones' => (int) ($resumenPeriodo->afiliaciones ?? 0),
                    'planillas'    => 0,
                    'total'        => (int) ($resumenPeriodo->afiliaciones ?? 0),
                    'pagado'       => (int) $pagosPeriodo,
                    'saldo'        => (int) ($resumenPeriodo->afiliaciones ?? 0) - (int) $pagosPeriodo,
                ];

                $totalGanado = DB::table('facturas AS f')
                    ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                    ->where('f.aliado_id', $aid)
                    ->whereNull('f.deleted_at')
                    ->whereNotNull('f.fecha_pago')
                    ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
                    ->where('c.encargado_id', $realId)
                    ->where('f.tipo', 'afiliacion')
                    ->whereRaw($this->condicionCorte('f'))
                    ->sum('f.dist_encargado');

                $totalPagado = PagoAsesor::where('aliado_id', $aid)
                    ->where('encargado_usuario_id', $realId)
                    ->whereRaw("(periodo_anio > " . self::CORTE_ANIO . " OR (periodo_anio = " . self::CORTE_ANIO . " AND periodo_mes >= " . self::CORTE_MES . "))")
                    ->sum('valor');

                $saldoTotal = max(0, (int) $totalGanado - (int) $totalPagado);

                $pagos = PagoAsesor::with('bancoCuenta', 'usuario')
                    ->where('aliado_id', $aid)
                    ->where('encargado_usuario_id', $realId)
                    ->where('periodo_mes', $mes)
                    ->where('periodo_anio', $anio)
                    ->orderByDesc('fecha')
                    ->get();
            }
        }

        $bancos = BancoCuenta::paraFacturacion($aid);

        return view('admin.informes.comisiones.index', compact(
            'asesores', 'asesor', 'asesorId',
            'mes', 'anio',
            'facturas', 'consolidado', 'pagos',
            'saldoTotal', 'bancos'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /admin/informes/comisiones/afiliaciones
    // Vista redistribución: todas las afiliaciones, editar dist_*
    // ─────────────────────────────────────────────────────────────────
    public function afiliaciones(Request $request)
    {
        $this->checkAcceso();
        $aid      = $this->aliadoId();
        $mes      = (int) $request->input('mes',  now()->month);
        $anio     = (int) $request->input('anio', now()->year);
        $asesorId = (int) $request->input('asesor_id', 0);
        $filtro   = $request->input('filtro', 'todas'); // 'todas' | 'sin_distribuir'
        $empresa  = trim((string) $request->input('empresa', ''));
        $planMod  = trim((string) $request->input('plan_mod', '')); // 'plan:ID' | 'mod:ID'
        $sort     = (string) $request->input('sort', 'factura');
        $dir      = strtolower($request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = DB::table('facturas AS f')
            ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
            ->leftJoin('asesores AS a', 'a.id', '=', 'c.asesor_id')
            ->leftJoin('planes_contrato AS pc', 'pc.id', '=', 'c.plan_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'c.tipo_modalidad_id')
            ->leftJoin('clientes AS cl', function ($j) use ($aid) {
                $j->on('cl.cedula', '=', 'f.cedula')->where('cl.aliado_id', $aid);
            })
            ->leftJoin('empresas AS em', 'em.id', '=', DB::raw(
                "(SELECT TOP 1 cl2.cod_empresa FROM clientes cl2 WHERE cl2.cedula = f.cedula AND cl2.aliado_id = {$aid})"
            ))
            ->where('f.aliado_id', $aid)
            ->whereNull('f.deleted_at')
            ->where('f.tipo', 'afiliacion')
            ->where('f.mes', $mes)
            ->where('f.anio', $anio);

        // Opciones de los filtros de columna: se sacan del período completo,
        // antes de aplicar los filtros, para que no se vacíen al filtrar.
        $opciones = (clone $query)->selectRaw("DISTINCT
                ISNULL(c.asesor_id, 0)         AS asesor_id,
                ISNULL(a.nombre, '—')          AS asesor_nombre,
                ISNULL(em.empresa, '—')        AS empresa_nombre,
                ISNULL(c.plan_id, 0)           AS plan_id,
                ISNULL(pc.nombre, '—')         AS plan_nombre,
                ISNULL(c.tipo_modalidad_id, 0) AS modalidad_id,
                ISNULL(tm.tipo_modalidad, '—') AS modalidad_nombre
            ")->get();

        $asesores = $opciones->where('asesor_id', '>', 0)
            ->unique('asesor_id')->sortBy('asesor_nombre')
            ->map(fn ($o) => (object) ['id' => (int) $o->asesor_id, 'nombre' => $o->asesor_nombre])
            ->values();

        $empresasDisponibles = $opciones->pluck('empresa_nombre')->unique()->sort()->values();

        $planesDisponibles = $opciones->where('plan_id', '>', 0)
            ->unique('plan_id')->sortBy('plan_nombre')
            ->map(fn ($o) => (object) ['id' => (int) $o->plan_id, 'nombre' => $o->plan_nombre])
            ->values();

        $modalidadesDisponibles = $opciones->where('modalidad_id', '>', 0)
            ->unique('modalidad_id')->sortBy('modalidad_nombre')
            ->map(fn ($o) => (object) ['id' => (int) $o->modalidad_id, 'nombre' => $o->modalidad_nombre])
            ->values();

        if ($asesorId) {
            $query->where('c.asesor_id', $asesorId);
        }

        if ($empresa !== '') {
            $query->whereRaw("ISNULL(em.empresa, '—') = ?", [$empresa]);
        }

        if (str_starts_with($planMod, 'plan:')) {
            $query->where('c.plan_id', (int) substr($planMod, 5));
        } elseif (str_starts_with($planMod, 'mod:')) {
            $query->where('c.tipo_modalidad_id', (int) substr($planMod, 4));
        }

        // Sin distribuir = no se le asignó nada a nadie. La utilidad NO cuenta:
        // una afiliación recién facturada llega con todo el valor ahí, y eso es
        // justamente lo que falta repartir.
        if ($filtro === 'sin_distribuir') {
            $query->whereRaw(self::SQL_SIN_DISTRIBUIR);
        }

        // Orden por columna. Lista blanca: el valor entra por querystring.
        $columnasOrden = [
            'factura'   => 'f.numero_factura',
            'fecha'     => 'f.fecha_pago',
            'cliente'   => 'nombre_cliente',
            'empresa'   => 'empresa_nombre',
            'asesor'    => 'asesor_nombre',
            'plan'      => 'plan_nombre',
            'afiliacion'=> 'f.afiliacion',
            'v_asesor'  => 'f.dist_asesor',
            'v_retiro'  => 'f.dist_retiro',
            'v_encarg'  => 'f.dist_encargado',
            'v_admon'   => 'f.dist_admon',
            'v_util'    => 'f.dist_utilidad',
        ];
        $colOrden = $columnasOrden[$sort] ?? 'f.numero_factura';
        if (! isset($columnasOrden[$sort])) {
            $sort = 'factura';
        }

        $facturas = $query->selectRaw("
                f.id,
                f.numero_factura,
                f.afiliacion,
                f.dist_asesor,
                f.dist_retiro,
                f.dist_encargado,
                f.dist_admon,
                f.dist_utilidad,
                f.estado,
                f.cedula,
                ISNULL(NULLIF(LTRIM(RTRIM(cl.tipo_doc)), ''), 'CC') AS tipo_doc,
                CONVERT(VARCHAR(10), f.fecha_pago, 120) AS fecha_pago,
                ISNULL(a.nombre, '—') AS asesor_nombre,
                c.asesor_id,
                ISNULL(pc.nombre, '—') AS plan_nombre,
                ISNULL(tm.tipo_modalidad, '—') AS modalidad_nombre,
                LTRIM(RTRIM(
                    ISNULL(cl.primer_nombre,'') + ' ' +
                    ISNULL(cl.segundo_nombre,'') + ' ' +
                    ISNULL(cl.primer_apellido,'') + ' ' +
                    ISNULL(cl.segundo_apellido,'')
                )) AS nombre_cliente,
                ISNULL(em.empresa, '—') AS empresa_nombre
            ")
            ->orderBy(DB::raw($colOrden), $dir)
            ->get()
            ->map(function ($f) {
                // Mismo criterio que el filtro: sin la utilidad.
                $f->distribuida = (
                    ((int)$f->dist_asesor + (int)$f->dist_retiro +
                     (int)$f->dist_admon  + (int)($f->dist_encargado ?? 0)) > 0
                );
                return $f;
            });

        $totalSinDistribuir = $facturas->where('distribuida', false)->count();

        return view('admin.informes.comisiones.afiliaciones', compact(
            'facturas', 'asesores', 'mes', 'anio', 'asesorId',
            'filtro', 'totalSinDistribuir', 'empresa', 'planMod',
            'empresasDisponibles', 'planesDisponibles', 'modalidadesDisponibles',
            'sort', 'dir'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/informes/comisiones/afiliaciones/{id}
    // Guardar distribución de una factura de afiliación
    // ─────────────────────────────────────────────────────────────────
    public function distribuir(Request $request, int $id)
    {
        $this->checkAcceso();
        $aid = $this->aliadoId();

        $factura = \App\Models\Factura::where('id', $id)
            ->where('aliado_id', $aid)
            ->where('tipo', 'afiliacion')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $validated = $request->validate([
            'dist_asesor'   => 'required|integer|min:0',
            'dist_retiro'   => 'required|integer|min:0',
            'dist_admon'    => 'required|integer|min:0',
            'dist_utilidad' => 'required|integer|min:0',
            'dist_encargado'=> 'required|integer|min:0',
        ]);

        $suma = $validated['dist_asesor'] + $validated['dist_retiro']
              + $validated['dist_admon']  + $validated['dist_utilidad']
              + $validated['dist_encargado'];

        if ($suma !== (int) $factura->afiliacion) {
            return response()->json([
                'ok'     => false,
                'error'  => "La suma de distribución ($suma) no coincide con el valor de afiliación ({$factura->afiliacion}).",
            ], 422);
        }

        $factura->update($validated);

        \App\Models\Bitacora::registrar(
            'updated', 'Factura', $id,
            "Distribución de afiliación editada por superadmin. Factura #{$factura->numero_factura}.",
            ['dist' => $validated],
            $aid
        );

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/informes/comisiones/asesores/{id}/pagar
    // Registrar pago (parcial o total) a un asesor
    // ─────────────────────────────────────────────────────────────────
    public function pagar(Request $request, string $asesorId)
    {
        $this->checkAcceso();
        $aid = $this->aliadoId();

        $realId = 0;
        $tipo = 'asesor';
        if (str_starts_with($asesorId, 'asesor_')) {
            $realId = (int) str_replace('asesor_', '', $asesorId);
            $tipo = 'asesor';
        } elseif (str_starts_with($asesorId, 'encargado_')) {
            $realId = (int) str_replace('encargado_', '', $asesorId);
            $tipo = 'encargado';
        } else {
            $realId = (int) $asesorId;
            $tipo = 'asesor';
        }

        $nombreDestinatario = '';
        if ($tipo === 'asesor') {
            $asesor = Asesor::where('id', $realId)
                ->where('aliado_id', $aid)
                ->firstOrFail();
            $nombreDestinatario = $asesor->nombre;
        } else {
            $usuario = \App\Models\User::findOrFail($realId);
            $nombreDestinatario = $usuario->nombre;
        }

        $validated = $request->validate([
            'valor'           => 'required|integer|min:1',
            'fecha'           => 'required|date',
            'tipo'            => 'required|in:efectivo,banco',
            'banco_cuenta_id' => 'nullable|required_if:tipo,banco|integer',
            'periodo_mes'     => 'required|integer|between:1,12',
            'periodo_anio'    => 'required|integer|min:2025',
            'observacion'     => 'nullable|string|max:500',
        ]);

        $pagoData = [
            'aliado_id'      => $aid,
            'valor'          => $validated['valor'],
            'fecha'          => $validated['fecha'],
            'tipo'           => $validated['tipo'],
            'banco_cuenta_id'=> $validated['banco_cuenta_id'] ?? null,
            'periodo_mes'    => $validated['periodo_mes'],
            'periodo_anio'   => $validated['periodo_anio'],
            'observacion'    => $validated['observacion'] ?? null,
            'usuario_id'     => Auth::id(),
        ];

        if ($tipo === 'asesor') {
            $pagoData['asesor_id'] = $realId;
        } else {
            $pagoData['encargado_usuario_id'] = $realId;
        }

        $pago = PagoAsesor::create($pagoData);

        \App\Models\Bitacora::registrar(
            'created', 'PagoAsesor', $pago->id,
            "Pago registrado a {$tipo} {$nombreDestinatario}: \${$pago->valor} ({$pago->tipo}).",
            ['pago' => $pago->toArray()],
            $aid
        );

        return response()->json([
            'ok'    => true,
            'pago'  => [
                'id'    => $pago->id,
                'valor' => $pago->valor,
                'fecha' => $pago->fecha->format('d/m/Y'),
                'tipo'  => $pago->tipo,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // JSON: saldo retenido para asesores (para el card del informe)
    // ─────────────────────────────────────────────────────────────────
    public static function calcularSaldoRetenido(int $aliadoId): int
    {
        $corte_mes  = self::CORTE_MES;
        $corte_anio = self::CORTE_ANIO;

        $ganado = DB::table('facturas AS f')
            ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
            ->where('f.aliado_id', $aliadoId)
            ->whereNull('f.deleted_at')
            ->whereNotNull('f.fecha_pago')
            ->whereIn('f.estado', ['pagada', 'abono', 'prestamo'])
            ->whereRaw("(f.anio > {$corte_anio} OR (f.anio = {$corte_anio} AND f.mes >= {$corte_mes}))")
            ->selectRaw("
                ISNULL(SUM(
                    CASE WHEN f.tipo = 'afiliacion' THEN 
                        (CASE WHEN c.asesor_id IS NOT NULL AND f.dist_asesor > 0 THEN f.dist_asesor ELSE 0 END) + 
                        (CASE WHEN c.encargado_id IS NOT NULL AND f.dist_encargado > 0 THEN f.dist_encargado ELSE 0 END)
                    ELSE 0 END
                ), 0) +
                ISNULL(SUM(
                    CASE WHEN f.tipo = 'planilla' AND c.asesor_id IS NOT NULL AND f.admin_asesor > 0 THEN f.admin_asesor ELSE 0 END
                ), 0) AS total
            ")
            ->value('total');

        $pagado = DB::table('pagos_asesores')
            ->where('aliado_id', $aliadoId)
            ->whereRaw("(periodo_anio > {$corte_anio} OR (periodo_anio = {$corte_anio} AND periodo_mes >= {$corte_mes}))")
            ->sum('valor');

        return max(0, (int) $ganado - (int) $pagado);
    }
}
