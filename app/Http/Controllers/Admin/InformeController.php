<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BancoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InformeController extends Controller
{
    private function aliadoId(): int
    {
        return (int) session('aliado_id_activo');
    }

    private function checkAdmin(): void
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403, 'Acceso restringido a administradores.');
        }
    }

    private function checkFinanciero(): void
    {
        if (!Auth::user()->hasRole(['superadmin', 'contador'])) {
            abort(403, 'Acceso restringido a superadmin y contador.');
        }
    }

    // ── HUB principal ─────────────────────────────────────────────────
    public function hub()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $kpis = [
            'clientes_activos'   => DB::table('contratos')->where('aliado_id',$aid)->where('estado','vigente')->count(),
            'razones_sociales'   => DB::table('razones_sociales')->where('aliado_id',$aid)->where('estado','Activa')->count(),
            'afiliaciones_mes'   => DB::table('contratos')->where('aliado_id',$aid)->whereMonth('fecha_ingreso', now()->month)->whereYear('fecha_ingreso', now()->year)->count(),
            'retiros_mes'        => DB::table('contratos')->where('aliado_id',$aid)->where('estado','retirado')->whereMonth('fecha_retiro', now()->month)->whereYear('fecha_retiro', now()->year)->count(),
            'empresas'           => DB::table('empresas')->where('aliado_id',$aid)->count(),
            'incapacidades'      => DB::table('incapacidades')->where('aliado_id',$aid)->whereNull('deleted_at')->whereNotIn('estado',['cerrado','rechazado'])->count(),
            'tareas'             => DB::table('tareas')->where('aliado_id',$aid)->whereNull('deleted_at')->whereIn('estado',['pendiente','en_gestion','en_espera'])->count(),
        ];

        $esFinanciero = Auth::user()->hasRole(['superadmin','contador']);
        if ($esFinanciero) {
            $mes  = now()->month;
            $anio = now()->year;
            $kpis['ingresos_mes'] = DB::table('facturas')
                ->where('aliado_id',$aid)->whereNull('deleted_at')
                ->where('mes',$mes)->where('anio',$anio)
                ->whereIn('estado',['pagada','abono'])
                ->sum(DB::raw('admon + seguro + afiliacion + mensajeria + otros + iva + retiro'));
        }

        return view('admin.informes.hub', compact('kpis','esFinanciero'));
    }

    // ── 1. Clientes activos ───────────────────────────────────────────
    public function clientesActivos(Request $request)
    {
        $this->checkAdmin();
        $aid    = $this->aliadoId();
        $buscar = $request->input('q','');

        $query = DB::table('contratos AS c')
            ->join('clientes AS cl', function($j) use($aid){ $j->on('cl.cedula','=','c.cedula')->where('cl.aliado_id',$aid); })
            ->leftJoin('razones_sociales AS rs','rs.id','=','c.razon_social_id')
            ->leftJoin('empresas AS em','em.id','=','cl.cod_empresa')
            ->leftJoin('eps AS e','e.id','=','c.eps_id')
            ->where('c.aliado_id',$aid)
            ->where('c.estado','vigente')
            ->select('c.id','c.cedula','c.fecha_ingreso','c.salario',
                DB::raw("LTRIM(RTRIM(cl.primer_nombre+' '+ISNULL(cl.segundo_nombre,'')+' '+cl.primer_apellido+' '+ISNULL(cl.segundo_apellido,''))) AS nombre_completo"),
                'rs.razon_social','em.empresa','e.nombre AS eps_nombre');

        if ($buscar) {
            $query->where(function($q) use($buscar){
                $q->where('c.cedula','like',"%$buscar%")
                  ->orWhere('cl.primer_nombre','like',"%$buscar%")
                  ->orWhere('cl.primer_apellido','like',"%$buscar%");
            });
        }

        $clientes = $query->orderBy('cl.primer_apellido')->paginate(50)->withQueryString();
        $total    = DB::table('contratos')->where('aliado_id',$aid)->where('estado','vigente')->count();

        if ($request->input('excel')) return $this->exportCsv($clientes->getCollection(), 'clientes_activos',
            ['Cédula','Nombre','Razón Social','Empresa','EPS','Fecha Ingreso','Salario'],
            fn($r)=>[$r->cedula,$r->nombre_completo,$r->razon_social,$r->empresa,$r->eps_nombre,sqldate($r->fecha_ingreso)?->format('d/m/Y'),$r->salario]);

        return view('admin.informes.clientes_activos', compact('clientes','total','buscar'));
    }

    // ── 2. Por razón social ───────────────────────────────────────────
    public function porRazonSocial()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $data = DB::table('contratos AS c')
            ->join('razones_sociales AS rs','rs.id','=','c.razon_social_id')
            ->where('c.aliado_id',$aid)->where('c.estado','vigente')
            ->groupBy('rs.id','rs.razon_social','rs.estado')
            ->select('rs.id','rs.razon_social','rs.estado', DB::raw('COUNT(*) AS total'))
            ->orderByDesc('total')->get();

        $max = $data->max('total') ?: 1;
        return view('admin.informes.por_razon_social', compact('data','max'));
    }

    // ── 3. Afiliaciones y retiros ─────────────────────────────────────
    public function afiliacionesRetiros(Request $request)
    {
        $this->checkAdmin();
        $aid  = $this->aliadoId();
        $mes  = (int)$request->input('mes', now()->month);
        $anio = (int)$request->input('anio', now()->year);

        $afiliaciones = DB::table('contratos AS c')
            ->leftJoin('motivos_afiliacion AS ma','ma.id','=','c.motivo_afiliacion_id')
            ->where('c.aliado_id',$aid)
            ->whereMonth('c.fecha_ingreso',$mes)->whereYear('c.fecha_ingreso',$anio)
            ->groupBy('ma.id','ma.nombre')
            ->select('ma.nombre AS motivo', DB::raw('COUNT(*) AS total'))
            ->orderByDesc('total')->get();

        $retiros = DB::table('contratos AS c')
            ->leftJoin('motivos_retiro AS mr','mr.id','=','c.motivo_retiro_id')
            ->where('c.aliado_id',$aid)->where('c.estado','retirado')
            ->whereMonth('c.fecha_retiro',$mes)->whereYear('c.fecha_retiro',$anio)
            ->groupBy('mr.id','mr.nombre')
            ->select('mr.nombre AS motivo', DB::raw('COUNT(*) AS total'))
            ->orderByDesc('total')->get();

        return view('admin.informes.afiliaciones_retiros', compact('afiliaciones','retiros','mes','anio'));
    }

    // ── 4. Empresas clientes ──────────────────────────────────────────
    public function empresasClientes()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $data = DB::table('empresas AS em')
            ->where('em.aliado_id',$aid)
            ->leftJoin('clientes AS cl','cl.cod_empresa','=','em.id')
            ->leftJoin('contratos AS co', function($j) use($aid){
                $j->on('co.cedula','=','cl.cedula')->where('co.aliado_id',$aid)->where('co.estado','vigente');
            })
            ->groupBy('em.id','em.empresa','em.nit')
            ->select('em.id','em.empresa','em.nit',
                DB::raw('COUNT(DISTINCT cl.cedula) AS clientes'),
                DB::raw('COUNT(DISTINCT co.id) AS contratos'))
            ->orderByDesc('contratos')->get();

        return view('admin.informes.empresas_clientes', compact('data'));
    }

    // ── 5. Por entidades ──────────────────────────────────────────────
    public function porEntidades()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $eps = DB::table('contratos AS c')->join('eps AS e','e.id','=','c.eps_id')
            ->where('c.aliado_id',$aid)->where('c.estado','vigente')
            ->groupBy('e.id','e.nombre')->select('e.nombre', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();

        $pension = DB::table('contratos AS c')->join('pensiones AS p','p.id','=','c.pension_id')
            ->where('c.aliado_id',$aid)->where('c.estado','vigente')
            ->groupBy('p.id','p.razon_social')->select('p.razon_social AS nombre', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();

        $arl = DB::table('contratos AS c')->join('arls AS a','a.id','=','c.arl_id')
            ->where('c.aliado_id',$aid)->where('c.estado','vigente')
            ->groupBy('a.id','a.nombre_arl')->select('a.nombre_arl AS nombre', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();

        $caja = DB::table('contratos AS c')->join('cajas AS cj','cj.id','=','c.caja_id')
            ->where('c.aliado_id',$aid)->where('c.estado','vigente')
            ->groupBy('cj.id','cj.nombre')->select('cj.nombre', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();

        return view('admin.informes.por_entidades', compact('eps','pension','arl','caja'));
    }

    // ── 6. Retirados del mes ──────────────────────────────────────────
    public function retiradosMes(Request $request)
    {
        $this->checkAdmin();
        $aid  = $this->aliadoId();
        $mes  = (int)$request->input('mes', now()->month);
        $anio = (int)$request->input('anio', now()->year);

        $retirados = DB::table('contratos AS c')
            ->join('clientes AS cl', function($j) use($aid){ $j->on('cl.cedula','=','c.cedula')->where('cl.aliado_id',$aid); })
            ->leftJoin('razones_sociales AS rs','rs.id','=','c.razon_social_id')
            ->leftJoin('motivos_retiro AS mr','mr.id','=','c.motivo_retiro_id')
            ->where('c.aliado_id',$aid)->where('c.estado','retirado')
            ->whereMonth('c.fecha_retiro',$mes)->whereYear('c.fecha_retiro',$anio)
            ->select('c.cedula','c.fecha_retiro','c.observacion',
                DB::raw("LTRIM(RTRIM(cl.primer_nombre+' '+ISNULL(cl.segundo_nombre,'')+' '+cl.primer_apellido+' '+ISNULL(cl.segundo_apellido,''))) AS nombre_completo"),
                'rs.razon_social','mr.nombre AS motivo')
            ->orderBy('c.fecha_retiro')->get();

        if ($request->input('excel')) return $this->exportCsv($retirados,'retirados_mes',
            ['Cédula','Nombre','Razón Social','Fecha Retiro','Motivo','Observación'],
            fn($r)=>[$r->cedula,$r->nombre_completo,$r->razon_social,sqldate($r->fecha_retiro)?->format('d/m/Y'),$r->motivo,$r->observacion]);

        return view('admin.informes.retirados_mes', compact('retirados','mes','anio'));
    }

    // ── 7. Incapacidades ──────────────────────────────────────────────
    public function resumenIncapacidades()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $base = DB::table('incapacidades')->where('aliado_id',$aid)->whereNull('deleted_at');

        $kpis = [
            'total'    => (clone $base)->count(),
            'activas'  => (clone $base)->whereNotIn('estado',['cerrado','rechazado','pagado_afiliado'])->count(),
            'dias'     => (clone $base)->sum('dias_incapacidad'),
            'v_esperado'=> (clone $base)->sum('valor_esperado'),
        ];

        $porTipo    = (clone $base)->groupBy('tipo_incapacidad')->select('tipo_incapacidad', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();
        $porEstado  = (clone $base)->groupBy('estado')->select('estado', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();
        $porEntidad = (clone $base)->groupBy('tipo_entidad')->select('tipo_entidad', DB::raw('COUNT(*) AS total, SUM(valor_esperado) AS valor'))->orderByDesc('total')->get();

        return view('admin.informes.incapacidades', compact('kpis','porTipo','porEstado','porEntidad'));
    }

    // ── 8. Tareas ─────────────────────────────────────────────────────
    public function resumenTareas()
    {
        $this->checkAdmin();
        $aid = $this->aliadoId();

        $base = DB::table('tareas')->where('aliado_id',$aid)->whereNull('deleted_at');

        $kpis = [
            'total'      => (clone $base)->count(),
            'pendiente'  => (clone $base)->where('estado','pendiente')->count(),
            'en_gestion' => (clone $base)->where('estado','en_gestion')->count(),
            'en_espera'  => (clone $base)->where('estado','en_espera')->count(),
            'cerradas'   => (clone $base)->where('estado','cerrada')->count(),
        ];

        $porTipo      = (clone $base)->whereIn('estado',['pendiente','en_gestion','en_espera'])->groupBy('tipo')->select('tipo', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();
        $porEncargado = (clone $base)->whereIn('estado',['pendiente','en_gestion','en_espera'])
            ->join('users AS u','u.id','=','tareas.encargado_id')
            ->groupBy('u.id','u.nombre')->select('u.nombre', DB::raw('COUNT(*) AS total'))->orderByDesc('total')->get();

        return view('admin.informes.tareas', compact('kpis','porTipo','porEncargado'));
    }

    // ── 9. Estado financiero ──────────────────────────────────────────
    public function estadoFinanciero(Request $request)
    {
        $this->checkFinanciero();
        $aid  = $this->aliadoId();
        $mes  = (int)$request->input('mes', now()->month);
        $anio = (int)$request->input('anio', now()->year);

        // ── Ingresos base CAJA: dinero recibido este mes (fecha_pago) ────────────
        // Se usa fecha_pago (no mes/anio del período) para reflejar el efectivo
        // real cobrado en el mes — incluye facturas de meses anteriores pagadas ahora.
        $facturasBase = DB::table('facturas')
            ->where('aliado_id',$aid)->whereNull('deleted_at')
            ->whereNotNull('fecha_pago')
            ->whereMonth('fecha_pago',$mes)->whereYear('fecha_pago',$anio)
            ->whereIn('estado',['pagada','abono']);

        $ingresos = [
            'planillas'   => (clone $facturasBase)->where('tipo','planilla')->sum(DB::raw('admon + seguro + mensajeria + otros + iva + retiro')),
            'afiliaciones'=> (clone $facturasBase)->where('tipo','afiliacion')->sum(DB::raw('afiliacion + admon + seguro + iva')),
            'tramites'    => (clone $facturasBase)->where('tipo','otro_ingreso')->sum(DB::raw('admon + otros')),
        ];
        $ingresos['total'] = $ingresos['planillas'] + $ingresos['afiliaciones'] + $ingresos['tramites'];

        // ── SS de terceros (base CAJA) ────────────────────────────────
        // Recaudo SS real: facturas pagadas este mes con numero_factura > 0
        $facturasSSBase = (clone $facturasBase)->where('numero_factura', '>', 0);

        $recaudoSS = (clone $facturasSSBase)->sum('total_ss');

        // ── Mora recogida (NO es ingreso — es multa al cliente por pago tardío) ──
        // Se separa del recaudoSS para que el informe muestre cuánto fue cargo
        // real de SS vs cuánto fue por penalización de mora.
        $moraRecogida = (clone $facturasBase)->sum('mora');

        // Desglose ingresos SS por componente (EPS, ARL, AFP, Caja)
        // $facturasSSBase filtra por fecha_pago (caja) y numero_factura > 0
        $ingresosSSRaw = (clone $facturasSSBase)
            ->where('tipo','planilla')
            ->selectRaw('SUM(v_eps) AS eps, SUM(v_arl) AS arl, SUM(v_afp) AS afp, SUM(v_caja) AS caja,
                         SUM(total_ss) AS total_ss')
            ->first();

        // SS proveniente de facturas de PERÍODOS ANTERIORES pagadas este mes
        // (la factura tiene mes/anio distinto al mes consultado, pero fecha_pago en este mes)
        $ssAnteriores = (clone $facturasSSBase)
            ->where('tipo','planilla')
            ->where(function($q) use ($mes, $anio) {
                $q->where('anio', '<', $anio)
                  ->orWhere(fn($i) => $i->where('anio', $anio)->where('mes', '<', $mes));
            })
            ->sum('total_ss');

        // Campo `retiro` de facturas — fee de retiro separado de la afiliación
        // (NO es el SS de retiro; es la tarifa cobrada al cliente por el proceso de retiro)
        $retiroCampo = (clone $facturasBase)->sum('retiro');

        $ingresosSS = [
            'eps'          => (float)($ingresosSSRaw->eps      ?? 0),
            'arl'          => (float)($ingresosSSRaw->arl      ?? 0),
            'afp'          => (float)($ingresosSSRaw->afp      ?? 0),
            'caja'         => (float)($ingresosSSRaw->caja     ?? 0),
            'total_ss'     => (float)($ingresosSSRaw->total_ss ?? 0),
            'ss_anteriores'=> (float)$ssAnteriores,
            'retiro_campo' => (float)$retiroCampo,
        ];

        // Egresos SS: gastos pago_planilla del mes seleccionado
        $egresosSSDetalle = DB::table('gastos AS g')
            ->leftJoin('banco_cuentas AS bc', 'bc.id', '=', 'g.banco_origen_id')
            ->where('g.aliado_id', $aid)
            ->where('g.tipo', 'pago_planilla')
            ->whereMonth('g.fecha', $mes)
            ->whereYear('g.fecha', $anio)
            ->selectRaw("
                g.numero_planilla, g.descripcion, g.pagado_a,
                MAX(g.fecha) AS fecha,
                SUM(g.valor) AS total,
                COUNT(*) AS cantidad,
                MAX(bc.banco) AS banco_nombre,
                MAX(bc.nombre) AS banco_titular,
                ISNULL((
                    SELECT SUM(f.total_ss)
                    FROM planos p2
                    INNER JOIN facturas f ON f.id = p2.factura_id
                    WHERE p2.aliado_id = {$aid}
                      AND p2.numero_planilla = g.numero_planilla
                      AND p2.deleted_at IS NULL
                      AND f.deleted_at IS NULL
                      AND f.numero_factura > 0
                ), 0) AS ss_cobrado_facturas,
                ISNULL((
                    SELECT SUM(f.total_ss)
                    FROM planos p2
                    INNER JOIN facturas f ON f.id = p2.factura_id
                    WHERE p2.aliado_id = {$aid}
                      AND p2.numero_planilla = g.numero_planilla
                      AND p2.deleted_at IS NULL
                      AND f.deleted_at IS NULL
                      AND f.numero_factura = 0
                ), 0) AS ss_retiro_facturas
            ")
            ->groupBy('g.numero_planilla', 'g.descripcion', 'g.pagado_a')
            ->orderByDesc('total')
            ->get();


        // ── Base CAJA: anticipos y cobradosAntes ya no aplican como ajuste ─────
        // Con base caja (fecha_pago), TODAS las facturas pagadas este mes ya están
        // en $facturasBase — no hay que sumar ni restar períodos futuros/anteriores.
        // Se mantienen como informativos con valor 0 para no romper la vista.
        $anticipos     = ['admon' => 0, 'ss' => 0, 'cant' => 0, 'total' => 0];
        $cobradosAntes = ['admon' => 0, 'ss' => 0, 'cant' => 0, 'total' => 0];

        $pagadoSS = $egresosSSDetalle->sum('total');
        // Saldo SS = recaudado este mes (caja) − pagado a planillas este mes
        $saldoSS  = $recaudoSS - $pagadoSS;

        // ── Reconciliación SS: planillas con gap entre cobrado y pagado ──
        // diferencia = gasto - (SS cobrado en facturas regulares + SS de retiros)
        // Si diferencia > 0: se pagó más SS del que se cobró al cliente
        // Si diferencia < 0: se cobró más SS del que se pagó (caso raro)
        $gapSS = $egresosSSDetalle->map(function ($eg) {
            $gasto      = (float)($eg->total ?? 0);
            $cobReg     = (float)($eg->ss_cobrado_facturas ?? 0);   // facturas numero_factura > 0
            $cobRetiro  = (float)($eg->ss_retiro_facturas  ?? 0);   // facturas numero_factura = 0
            $cobTotal   = $cobReg + $cobRetiro;
            $diff       = $gasto - $cobTotal;
            $tieneGap   = abs($diff) > 100;   // tolerancia 100 pesos por redondeo

            // Clasificar la causa principal del gap
            $causa = null;
            if ($tieneGap) {
                if ($cobReg == 0 && $cobRetiro > 0) {
                    $causa = 'retiro_sin_ingreso';   // Solo retiros — el gasto es real pero no hay recaudo
                } elseif ($cobReg == 0 && $cobRetiro == 0) {
                    $causa = 'sin_factura';           // No hay ninguna factura ligada a esta planilla
                } else {
                    $causa = 'diferencia_parcial';    // Hay facturas pero los montos no cuadran (período distinto, etc.)
                }
            }

            return [
                'numero_planilla' => $eg->numero_planilla,
                'descripcion'     => $eg->descripcion ?: $eg->pagado_a,
                'pagado_a'        => $eg->pagado_a,
                'fecha'           => $eg->fecha,
                'gasto'           => $gasto,
                'ss_cobrado_reg'  => $cobReg,
                'ss_cobrado_ret'  => $cobRetiro,
                'ss_cobrado'      => $cobTotal,
                'diferencia'      => $diff,
                'tiene_gap'       => $tieneGap,
                'causa'           => $causa,
                'cant_registros'  => (int)($eg->cantidad ?? 1),
            ];
        })->filter(fn($r) => $r['tiene_gap'])->values();

        // Resumen del gap agrupado por causa
        $gapResumen = [
            'total_gap'         => (float)($pagadoSS - $recaudoSS),
            'planillas_con_gap' => $gapSS->count(),
            'por_retiro'        => $gapSS->where('causa', 'retiro_sin_ingreso')->sum('diferencia'),
            'sin_factura'       => $gapSS->where('causa', 'sin_factura')->sum('diferencia'),
            'diferencia_parcial'=> $gapSS->where('causa', 'diferencia_parcial')->sum('diferencia'),
        ];


        // Comisiones asesor (acumuladas en facturas del mes)
        $comisionesAsesor = (clone $facturasBase)->sum('c_asesor');

        // Gastos operativos (sin planillas SS)
        $gastosOp = DB::table('gastos')->where('aliado_id',$aid)
            ->where('tipo','!=','pago_planilla')
            ->whereMonth('fecha',$mes)->whereYear('fecha',$anio)->sum('valor');

        $egresos = ['comisiones' => $comisionesAsesor, 'operativos' => $gastosOp, 'total' => $comisionesAsesor + $gastosOp];
        $utilidad = $ingresos['total'] - $egresos['total'];

        // Tendencia 6 meses
        $tendencia = $this->tendencia6Meses($aid, $mes, $anio);

        // Mes anterior para comparación
        $mesAnt  = $mes > 1 ? $mes - 1 : 12;
        $anioAnt = $mes > 1 ? $anio : $anio - 1;
        $anterior = $this->resumenMes($aid, $mesAnt, $anioAnt);

        // ── Saldo SS del mes anterior ─────────────────────────────────
        // Recaudo SS del mes anterior (caja, mismo criterio que este mes)
        $recaudoSSPrev = DB::table('facturas')
            ->where('aliado_id', $aid)->whereNull('deleted_at')
            ->whereNotNull('fecha_pago')
            ->whereMonth('fecha_pago', $mesAnt)->whereYear('fecha_pago', $anioAnt)
            ->whereIn('estado', ['pagada','abono'])
            ->where('numero_factura', '>', 0)
            ->sum('total_ss');

        // Pagado SS del mes anterior (gastos pago_planilla)
        $pagadoSSPrev = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->whereMonth('fecha', $mesAnt)->whereYear('fecha', $anioAnt)
            ->sum('valor');

        // Saldo SS disponible del mes anterior (positivo = quedó dinero para este mes)
        // Solo se arrastra saldo POSITIVO del mes anterior.
        // Si el mes anterior tuvo déficit, no se hereda deuda — arranca en 0.
        $saldoSSMesAnterior = max(0.0, (float)$recaudoSSPrev - (float)$pagadoSSPrev);

        // Recalcular saldoSS incluyendo el saldo arrastrado del mes anterior
        $saldoSS = $recaudoSS + $saldoSSMesAnterior - $pagadoSS;

        // Bancos
        $bancos = BancoCuenta::where('aliado_id',$aid)->where('activo',true)->get()->map(function($b) use($aid,$mes,$anio){
            $b->entradas_mes = DB::table('consignaciones')->where('aliado_id',$aid)->where('banco_cuenta_id',$b->id)->whereMonth('fecha',$mes)->whereYear('fecha',$anio)->sum('valor');
            $b->salidas_mes  = DB::table('gastos')->where('aliado_id',$aid)->where('banco_origen_id',$b->id)->whereMonth('fecha',$mes)->whereYear('fecha',$anio)->sum('valor');
            $b->saldo_actual = \App\Models\Consignacion::saldoBanco($aid,$b->id);
            return $b;
        });

        // Desglose diario
        $diario = $this->desgloseDiario($aid, $mes, $anio);

        if ($request->input('excel')) return $this->exportCsv(collect($diario),'estado_financiero',
            ['Día','# Plan','Planillas','# Afil','Afiliaciones','Trámites','SS','Gastos','Utilidad'],
            fn($r)=>[$r['dia'],$r['cant_planillas'],number_format($r['planillas']),$r['cant_afiliaciones'],number_format($r['afiliaciones']),number_format($r['tramites']),number_format($r['ss']),number_format($r['gastos']),number_format($r['utilidad'])]);

        return view('admin.informes.financiero', compact(
            'mes','anio','ingresos','egresos','utilidad',
            'recaudoSS','pagadoSS','saldoSS',
            'saldoSSMesAnterior','recaudoSSPrev','pagadoSSPrev','mesAnt','anioAnt',
            'ingresosSS','egresosSSDetalle',
            'gapSS','gapResumen',
            'comisionesAsesor','gastosOp','tendencia','anterior','bancos','diario',
            'anticipos','cobradosAntes',
            'moraRecogida'
        ));
    }

    // ── JSON: movimientos de un banco ────────────────────────────────
    public function financieroBancos(Request $request)
    {
        $this->checkFinanciero();
        $aid      = $this->aliadoId();
        $bancoId  = (int)$request->input('banco_id');
        $mes      = (int)$request->input('mes', now()->month);
        $anio     = (int)$request->input('anio', now()->year);

        $entradas = DB::table('consignaciones AS cs')
            ->leftJoin('facturas AS f','f.id','=','cs.factura_id')
            ->where('cs.aliado_id',$aid)->where('cs.banco_cuenta_id',$bancoId)
            ->whereMonth('cs.fecha',$mes)->whereYear('cs.fecha',$anio)
            ->select('cs.fecha','cs.valor','cs.tipo','cs.referencia','f.numero_factura')
            ->orderBy('cs.fecha')->get();

        $salidas = DB::table('gastos')
            ->where('aliado_id',$aid)->where('banco_origen_id',$bancoId)
            ->whereMonth('fecha',$mes)->whereYear('fecha',$anio)
            ->select('fecha','valor','tipo','descripcion','pagado_a')
            ->orderBy('fecha')->get();

        return response()->json(['entradas'=>$entradas,'salidas'=>$salidas]);
    }

    // ── JSON: auditoría de un número de planilla ─────────────────────
    public function auditarPlanilla(Request $request)
    {
        $this->checkFinanciero();
        $aid          = $this->aliadoId();
        $numPlanilla  = trim($request->input('numero_planilla', ''));

        if (!$numPlanilla) {
            return response()->json(['error' => 'Número de planilla requerido.'], 422);
        }

        // ── 1. Gastos registrados para este número de planilla ────────
        // Busca TODOS los gastos (detectar pago duplicado si hay más de 1)
        $gastosAll = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->where('numero_planilla', $numPlanilla)
            ->orderBy('fecha')
            ->get();

        $cantGastos  = $gastosAll->count();
        $gasto       = $gastosAll->first();          // registro principal
        $gastoValor  = (float)$gastosAll->sum('valor'); // suma total (detecta dobles)
        $esDuplicado = $cantGastos > 1;

        // ── 2. Planos con ese numero_planilla (un plano = un empleado) ─
        $planos = DB::table('planos AS p')
            ->leftJoin('facturas AS f',         'f.id',  '=', 'p.factura_id')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->where('p.aliado_id', $aid)
            ->whereNull('p.deleted_at')
            ->where('p.numero_planilla', $numPlanilla)
            ->select([
                'p.id',
                'p.no_identifi',
                DB::raw("LTRIM(RTRIM(ISNULL(p.primer_nombre,'')+' '+ISNULL(p.segundo_nombre,'')+' '+ISNULL(p.primer_ape,'')+' '+ISNULL(p.segundo_ape,''))) AS nombre_completo"),
                'p.razon_social_id',
                DB::raw("ISNULL(rs.razon_social, p.razon_social) AS empresa_nombre"),
                'rs.nit AS empresa_nit',
                'p.n_plano',
                'p.mes_plano',
                'p.anio_plano',
                'p.num_dias',
                'p.tipo_reg',
                'f.id AS factura_id',
                'f.numero_factura',
                'f.v_eps', 'f.v_afp', 'f.v_arl', 'f.v_caja', 'f.total_ss',
            ])
            ->orderBy('rs.razon_social')
            ->orderBy('p.primer_ape')
            ->get();

        // ── 3. Totales SS cobrados a clientes (desde facturas) ────────
        $totalSSFacturas = (float)$planos->sum('total_ss');
        $totalEPS        = (float)$planos->sum('v_eps');
        $totalAFP        = (float)$planos->sum('v_afp');
        $totalARL        = (float)$planos->sum('v_arl');
        $totalCaja       = (float)$planos->sum('v_caja');
        $diferencia      = $totalSSFacturas - $gastoValor;

        return response()->json([
            'numero_planilla'   => $numPlanilla,
            'es_duplicado'      => $esDuplicado,
            'cant_gastos'       => $cantGastos,
            'gastos_detalle'    => $gastosAll,   // lista completa (para mostrar duplicados)
            'gasto'             => $gasto,
            'gasto_valor'       => $gastoValor,
            'total_ss_facturas' => $totalSSFacturas,
            'total_eps'         => $totalEPS,
            'total_afp'         => $totalAFP,
            'total_arl'         => $totalARL,
            'total_caja'        => $totalCaja,
            'diferencia'        => $diferencia,
            'cant_empleados'    => $planos->count(),
            'planos'            => $planos,
        ]);
    }

    // ── Todas las facturas ligadas a planillas de gastos de un mes ──────
    public function ssPlanillas(Request $request)
    {
        $this->checkFinanciero();
        $aid  = $this->aliadoId();
        $mes  = (int)$request->input('mes',  now()->month);
        $anio = (int)$request->input('anio', now()->year);

        // 1. Gastos pago_planilla del mes (agrupados por numero_planilla)
        $gastos = DB::table('gastos AS g')
            ->leftJoin('banco_cuentas AS bc', 'bc.id', '=', 'g.banco_origen_id')
            ->where('g.aliado_id', $aid)
            ->where('g.tipo', 'pago_planilla')
            ->whereMonth('g.fecha', $mes)
            ->whereYear('g.fecha', $anio)
            ->selectRaw('
                g.numero_planilla,
                g.descripcion,
                g.pagado_a,
                MAX(g.fecha)   AS fecha_gasto,
                SUM(g.valor)   AS gasto_total,
                COUNT(*)       AS cant_gastos,
                MAX(bc.banco)  AS banco_nombre
            ')
            ->groupBy('g.numero_planilla', 'g.descripcion', 'g.pagado_a')
            ->orderBy('g.numero_planilla')
            ->get();

        $numeros = $gastos->pluck('numero_planilla')->filter()->unique()->values();

        // 2. Todas las facturas ligadas a esos numero_planilla (sin filtrar por período)
        //    Una factura puede ser de cualquier mes/año — eso es lo que queremos ver
        $facturasRaw = DB::table('planos AS p')
            ->join('facturas AS f', 'f.id', '=', 'p.factura_id')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->where('p.aliado_id', $aid)
            ->whereNull('p.deleted_at')
            ->whereNull('f.deleted_at')
            ->whereIn('p.numero_planilla', $numeros)
            ->selectRaw('
                p.numero_planilla,
                f.id             AS factura_id,
                f.numero_factura,
                f.mes            AS f_mes,
                f.anio           AS f_anio,
                f.estado,
                f.fecha_pago,
                SUM(f.total_ss)  AS total_ss,
                SUM(f.v_eps)     AS v_eps,
                SUM(f.v_afp)     AS v_afp,
                SUM(f.v_arl)     AS v_arl,
                SUM(f.v_caja)    AS v_caja,
                COUNT(p.id)      AS cant_empleados,
                MAX(ISNULL(rs.razon_social, p.razon_social)) AS razon_social
            ')
            ->groupBy(
                'p.numero_planilla',
                'f.id', 'f.numero_factura',
                'f.mes', 'f.anio', 'f.estado', 'f.fecha_pago'
            )
            ->orderBy('p.numero_planilla')
            ->orderBy('f.anio')
            ->orderBy('f.mes')
            ->get()
            ->groupBy('numero_planilla');

        // 3. Planillas en gastos que NO tienen ningún plano/factura ligado
        $sinPlanos = DB::table('gastos AS g')
            ->where('g.aliado_id', $aid)
            ->where('g.tipo', 'pago_planilla')
            ->whereMonth('g.fecha', $mes)
            ->whereYear('g.fecha', $anio)
            ->whereIn('g.numero_planilla', $numeros)
            ->whereNotExists(function ($q) use ($aid) {
                $q->select(DB::raw(1))
                  ->from('planos AS p2')
                  ->whereRaw('p2.numero_planilla = g.numero_planilla')
                  ->where('p2.aliado_id', $aid)
                  ->whereNull('p2.deleted_at');
            })
            ->selectRaw('g.numero_planilla, g.descripcion, g.pagado_a, MAX(g.fecha) AS fecha_gasto, SUM(g.valor) AS gasto_total')
            ->groupBy('g.numero_planilla', 'g.descripcion', 'g.pagado_a')
            ->get();

        // 4. Ensamblar: por cada gasto, sus facturas clasificadas (mismo período / otro período)
        $mesesEs = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $resumen = $gastos->map(function ($g) use ($facturasRaw, $mes, $anio, $mesesEs) {
            $facturas = collect($facturasRaw->get($g->numero_planilla, []));

            $ssMismoPeriodo = $facturas->where('f_mes', $mes)->where('f_anio', $anio)->where('numero_factura', '>', 0)->sum('total_ss');
            $ssOtroPeriodo  = $facturas->where(fn($f) => !($f->f_mes == $mes && $f->f_anio == $anio))->where('numero_factura', '>', 0)->sum('total_ss');
            $ssRetiros      = $facturas->where('numero_factura', 0)->sum('total_ss');
            $ssTotalCobrado = $ssMismoPeriodo + $ssOtroPeriodo + $ssRetiros;
            $diferencia     = $g->gasto_total - $ssTotalCobrado;

            return [
                'numero_planilla'  => $g->numero_planilla,
                'descripcion'      => $g->descripcion ?: $g->pagado_a,
                'pagado_a'         => $g->pagado_a,
                'fecha_gasto'      => $g->fecha_gasto,
                'banco'            => $g->banco_nombre,
                'gasto_total'      => (float)$g->gasto_total,
                'cant_gastos'      => (int)$g->cant_gastos,
                'ss_mismo_periodo' => (float)$ssMismoPeriodo,
                'ss_otro_periodo'  => (float)$ssOtroPeriodo,
                'ss_retiros'       => (float)$ssRetiros,
                'ss_total_cobrado' => (float)$ssTotalCobrado,
                'diferencia'       => (float)$diferencia,
                'facturas'         => $facturas->map(fn($f) => [
                    'factura_id'      => $f->factura_id,
                    'numero_factura'  => $f->numero_factura,
                    'periodo'         => ($mesesEs[$f->f_mes] ?? $f->f_mes) . ' ' . $f->f_anio,
                    'f_mes'           => $f->f_mes,
                    'f_anio'          => $f->f_anio,
                    'estado'          => $f->estado,
                    'fecha_pago'      => $f->fecha_pago,
                    'es_retiro'       => $f->numero_factura == 0,
                    'es_otro_periodo' => !($f->f_mes == $mes && $f->f_anio == $anio),
                    'total_ss'        => (float)$f->total_ss,
                    'v_eps'           => (float)$f->v_eps,
                    'v_afp'           => (float)$f->v_afp,
                    'v_arl'           => (float)$f->v_arl,
                    'v_caja'          => (float)$f->v_caja,
                    'cant_empleados'  => (int)$f->cant_empleados,
                    'razon_social'    => $f->razon_social,
                ])->values()->toArray(),
            ];
        });

        // Totales globales
        $totales = [
            'gasto_total'      => (float)$resumen->sum('gasto_total'),
            'ss_mismo_periodo' => (float)$resumen->sum('ss_mismo_periodo'),
            'ss_otro_periodo'  => (float)$resumen->sum('ss_otro_periodo'),
            'ss_retiros'       => (float)$resumen->sum('ss_retiros'),
            'ss_total_cobrado' => (float)$resumen->sum('ss_total_cobrado'),
            'diferencia'       => (float)$resumen->sum('diferencia'),
            'cant_planillas'   => $resumen->count(),
        ];

        return view('admin.informes.ss_planillas', compact(
            'mes', 'anio', 'resumen', 'totales', 'sinPlanos', 'mesesEs'
        ));
    }


    private function desgloseDiario(int $aid, int $mes, int $anio): array
    {
        $factDia = DB::table('facturas')
            ->where('aliado_id',$aid)->whereNull('deleted_at')
            ->whereNotNull('fecha_pago')
            ->whereMonth('fecha_pago',$mes)->whereYear('fecha_pago',$anio)
            ->whereIn('estado',['pagada','abono'])
            ->selectRaw('DAY(fecha_pago) AS dia, tipo,
                COUNT(*) AS cant_filas,
                SUM(admon+seguro+mensajeria+otros+iva+retiro) AS ing_planilla,
                SUM(afiliacion+admon+seguro+iva) AS ing_afil,
                SUM(admon+otros) AS ing_tramite,
                SUM(CASE WHEN numero_factura > 0 THEN total_ss ELSE 0 END) AS ss_dia')
            ->groupByRaw('DAY(fecha_pago), tipo')
            ->get()->groupBy('dia');

        $gastosDia = DB::table('gastos')
            ->where('aliado_id',$aid)
            ->where('tipo','!=','pago_planilla')
            ->whereMonth('fecha',$mes)->whereYear('fecha',$anio)
            ->selectRaw('DAY(fecha) AS dia, SUM(valor) AS total')
            ->groupByRaw('DAY(fecha)')
            ->pluck('total','dia');

        $diasEnMes = now()->setDate($anio,$mes,1)->daysInMonth;
        $resultado = [];
        for($d=1;$d<=$diasEnMes;$d++){
            $filas           = $factDia->get($d, collect());
            $filaPlan        = $filas->where('tipo','planilla')->first();
            $filaAfil        = $filas->where('tipo','afiliacion')->first();
            $filaTramite     = $filas->where('tipo','otro_ingreso')->first();
            $planillas       = (float)($filaPlan->ing_planilla    ?? 0);
            $afil            = (float)($filaAfil->ing_afil        ?? 0);
            $tramites        = (float)($filaTramite->ing_tramite  ?? 0);
            $cantPlanillas   = (int)($filaPlan->cant_filas        ?? 0);
            $cantAfiliaciones= (int)($filaAfil->cant_filas        ?? 0);
            $ssDia           = (float)($filaPlan->ss_dia          ?? 0);
            $gastos          = (int)($gastosDia[$d] ?? 0);
            $resultado[] = [
                'dia'               => $d,
                'cant_planillas'    => $cantPlanillas,
                'planillas'         => $planillas,
                'cant_afiliaciones' => $cantAfiliaciones,
                'afiliaciones'      => $afil,
                'tramites'          => $tramites,
                'ss'                => $ssDia,
                'gastos'            => $gastos,
                'utilidad'          => $planillas + $afil + $tramites - $gastos,
            ];
        }
        return $resultado;
    }

    // ── Helper: resumen de un mes ─────────────────────────────────────
    private function resumenMes(int $aid, int $mes, int $anio): array
    {
        $base = DB::table('facturas')->where('aliado_id',$aid)->whereNull('deleted_at')
            ->whereNotNull('fecha_pago')
            ->whereMonth('fecha_pago',$mes)->whereYear('fecha_pago',$anio)
            ->whereIn('estado',['pagada','abono']);
        $ingresos = (clone $base)->sum(DB::raw('admon+seguro+afiliacion+mensajeria+otros+iva+retiro'));
        $egresos  = DB::table('gastos')->where('aliado_id',$aid)->where('tipo','!=','pago_planilla')
            ->whereMonth('fecha',$mes)->whereYear('fecha',$anio)->sum('valor');
        return ['ingresos'=>$ingresos,'egresos'=>$egresos,'utilidad'=>$ingresos-$egresos];
    }

    // ── Helper: tendencia 6 meses ─────────────────────────────────────
    private function tendencia6Meses(int $aid, int $mes, int $anio): array
    {
        $resultado = [];
        for($i=5;$i>=0;$i--){
            $m = $mes - $i; $a = $anio;
            while($m<1){$m+=12;$a--;}
            $r = $this->resumenMes($aid,$m,$a);
            $meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            $resultado[] = array_merge($r,['label'=>$meses[$m].' '.substr($a,2)]);
        }
        return $resultado;
    }

    // ── Helper: exportar CSV ─────────────────────────────────────────
    private function exportCsv($data, string $nombre, array $headers, callable $mapFn)
    {
        $filename = "{$nombre}_".now()->format('Ymd_His').".csv";
        return response()->streamDownload(function() use($data,$headers,$mapFn){
            $out = fopen('php://output','w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel
            fputcsv($out,$headers,';');
            foreach($data as $row) fputcsv($out,$mapFn($row),';');
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    // ── JSON: facturas y gastos de un día específico ──────────────────
    public function detalleDia(Request $request)
    {
        $this->checkFinanciero();
        $aid  = $this->aliadoId();
        $dia  = (int)$request->input('dia');
        $mes  = (int)$request->input('mes', now()->month);
        $anio = (int)$request->input('anio', now()->year);
        $tipo = $request->input('tipo', 'todos'); // todos|planilla|afiliacion|otro_ingreso|gastos

        // ── Facturas del día ──────────────────────────────────────────
        $qFact = DB::table('facturas AS f')
            ->where('f.aliado_id', $aid)
            ->whereNull('f.deleted_at')
            ->whereNotNull('f.fecha_pago')
            ->whereRaw('DAY(f.fecha_pago) = ?', [$dia])
            ->whereMonth('f.fecha_pago', $mes)
            ->whereYear('f.fecha_pago', $anio)
            ->whereIn('f.estado', ['pagada', 'abono'])
            ->leftJoin('clientes AS cl', function($j) use($aid) {
                $j->on('cl.cedula', '=', 'f.cedula')->where('cl.aliado_id', $aid);
            })
            ->leftJoin('empresas AS em', 'em.id', '=', 'f.empresa_id');

        if ($tipo !== 'todos' && $tipo !== 'gastos') {
            $qFact->where('f.tipo', $tipo);
        }

        $facturas = $qFact->select([
            'f.id', 'f.numero_factura', 'f.tipo', 'f.mes', 'f.anio',
            'f.cedula', 'f.empresa_id', 'f.estado', 'f.fecha_pago',
            DB::raw("ISNULL(LTRIM(RTRIM(cl.primer_nombre+' '+cl.primer_apellido)), '—') AS nombre_cliente"),
            DB::raw("ISNULL(em.empresa, '—') AS nombre_empresa"),
            DB::raw('(f.admon + f.seguro + f.mensajeria + f.otros + f.iva + f.retiro) AS ing_planilla'),
            DB::raw('(f.afiliacion + f.admon + f.seguro + f.iva) AS ing_afil'),
            DB::raw('(f.admon + f.otros) AS ing_tramite'),
            'f.total_ss',
        ])
        ->orderBy('f.tipo')
        ->orderBy('f.numero_factura')
        ->get()
        ->map(function($r) {
            $ingreso = match($r->tipo) {
                'planilla'     => (float)$r->ing_planilla,
                'afiliacion'   => (float)$r->ing_afil,
                'otro_ingreso' => (float)$r->ing_tramite,
                default        => 0,
            };
            $nombre = $r->empresa_id && $r->empresa_id != 1
                ? '🏢 '.$r->nombre_empresa
                : '👤 '.$r->nombre_cliente;

            return [
                'id'             => $r->id,
                'numero_factura' => $r->numero_factura,
                'tipo'           => $r->tipo,
                'nombre'         => $nombre,
                'cedula'         => $r->cedula,
                'ingreso'        => $ingreso,
                'total_ss'       => (float)$r->total_ss,
                'estado'         => $r->estado,
                'fecha_pago'     => $r->fecha_pago,
            ];
        });

        // ── Gastos del día (no pago_planilla) ─────────────────────────
        $gastos = [];
        if ($tipo === 'todos' || $tipo === 'gastos') {
            $gastos = DB::table('gastos AS g')
                ->where('g.aliado_id', $aid)
                ->where('g.tipo', '!=', 'pago_planilla')
                ->whereRaw('DAY(g.fecha) = ?', [$dia])
                ->whereMonth('g.fecha', $mes)
                ->whereYear('g.fecha', $anio)
                ->select(['g.id', 'g.tipo', 'g.descripcion', 'g.pagado_a', 'g.valor', 'g.fecha'])
                ->orderBy('g.tipo')
                ->get()
                ->map(fn($g) => [
                    'id'          => $g->id,
                    'tipo'        => $g->tipo,
                    'descripcion' => $g->descripcion ?: $g->pagado_a,
                    'valor'       => (float)$g->valor,
                ]);
        }

        // ── Totales ───────────────────────────────────────────────────
        $planillas   = $facturas->where('tipo','planilla')->sum('ingreso');
        $afiliaciones= $facturas->where('tipo','afiliacion')->sum('ingreso');
        $tramites    = $facturas->where('tipo','otro_ingreso')->sum('ingreso');
        $totalGastos = collect($gastos)->sum('valor');
        $totalSS     = $facturas->where('tipo','planilla')->sum('total_ss');

        return response()->json([
            'ok'          => true,
            'dia'         => $dia,
            'mes'         => $mes,
            'anio'        => $anio,
            'facturas'    => $facturas->values(),
            'gastos'      => array_values($gastos->toArray()),
            'totales'     => [
                'planillas'    => $planillas,
                'afiliaciones' => $afiliaciones,
                'tramites'     => $tramites,
                'gastos'       => $totalGastos,
                'ss'           => $totalSS,
                'utilidad'     => $planillas + $afiliaciones + $tramites - $totalGastos,
            ],
        ]);
    }

    // ── JSON: resumen préstamos pendientes del mes ────────────────────
    public function prestamesMes(Request $request)
    {
        $this->checkFinanciero();
        $aid  = $this->aliadoId();
        $mes  = (int)$request->input('mes', now()->month);
        $anio = (int)$request->input('anio', now()->year);

        // Préstamos pendientes generados en ese mes/año
        $prestamos = DB::table('facturas AS f')
            ->where('f.aliado_id', $aid)
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'prestamo')
            ->where('f.mes', $mes)
            ->where('f.anio', $anio)
            ->leftJoin('clientes AS cl', function($j) use($aid) {
                $j->on('cl.cedula', '=', 'f.cedula')->where('cl.aliado_id', $aid);
            })
            ->leftJoin('empresas AS em', 'em.id', '=', 'f.empresa_id')
            ->select([
                'f.id', 'f.numero_factura', 'f.cedula', 'f.empresa_id',
                'f.total', 'f.saldo_proximo',
                DB::raw("ISNULL(LTRIM(RTRIM(cl.primer_nombre+' '+cl.primer_apellido)),'—') AS nombre_cliente"),
                DB::raw("ISNULL(em.empresa,'—') AS nombre_empresa"),
            ])
            ->get()
            ->map(function($r) {
                $saldo = abs((float)$r->saldo_proximo);
                $esEmpresa = $r->empresa_id && $r->empresa_id != 1;
                return [
                    'id'             => $r->id,
                    'numero_factura' => $r->numero_factura,
                    'nombre'         => $esEmpresa ? '🏢 '.$r->nombre_empresa : '👤 '.$r->nombre_cliente,
                    'cedula'         => $r->cedula,
                    'total_prestado' => (float)$r->total,
                    'saldo_pendiente'=> $saldo,
                    'es_empresa'     => $esEmpresa,
                    'empresa_id'     => $r->empresa_id,
                ];
            });

        // Agrupar empresas por numero_factura para no duplicar lotes
        $individuales = $prestamos->where('es_empresa', false)->values();
        $empresasLotes = $prestamos->where('es_empresa', true)
            ->groupBy('numero_factura')
            ->map(function($lote) {
                $first = $lote->first();
                return [
                    'numero_factura' => $first['numero_factura'],
                    'nombre'         => $first['nombre'],
                    'empresa_id'     => $first['empresa_id'],
                    'total_prestado' => $lote->sum('total_prestado'),
                    'saldo_pendiente'=> $lote->sum('saldo_pendiente'),
                    'cant_clientes'  => $lote->count(),
                    'factura_id'     => $first['id'],
                ];
            })->values();

        return response()->json([
            'ok'          => true,
            'individuales'=> $individuales,
            'empresas'    => $empresasLotes,
            'totales'     => [
                'total_prestado'  => $prestamos->sum('total_prestado'),
                'saldo_pendiente' => $prestamos->sum('saldo_pendiente'),
                'cant'            => $individuales->count() + $empresasLotes->count(),
            ],
        ]);
    }
}
