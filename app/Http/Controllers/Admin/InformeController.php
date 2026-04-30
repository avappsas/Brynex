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

        // Ingresos del mes (facturas pagadas/abono)
        $facturasBase = DB::table('facturas')
            ->where('aliado_id',$aid)->whereNull('deleted_at')
            ->where('mes',$mes)->where('anio',$anio)
            ->whereIn('estado',['pagada','abono']);

        $ingresos = [
            'planillas'   => (clone $facturasBase)->where('tipo','planilla')->sum(DB::raw('admon + seguro + mensajeria + otros + iva + retiro')),
            'afiliaciones'=> (clone $facturasBase)->where('tipo','afiliacion')->sum(DB::raw('afiliacion + admon + seguro + iva')),
            'tramites'    => (clone $facturasBase)->where('tipo','otro_ingreso')->sum(DB::raw('admon + otros')),
        ];
        $ingresos['total'] = $ingresos['planillas'] + $ingresos['afiliaciones'] + $ingresos['tramites'];

        // SS de terceros
        $recaudoSS   = (clone $facturasBase)->sum('total_ss');
        $pagadoSS    = DB::table('gastos')->where('aliado_id',$aid)->where('tipo','pago_planilla')
            ->whereMonth('fecha',$mes)->whereYear('fecha',$anio)->sum('valor');
        $saldoSS     = $recaudoSS - $pagadoSS;

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
            ['Día','Planillas','Trámites','Afiliaciones','Gastos','Utilidad'],
            fn($r)=>[$r['dia'],number_format($r['planillas']),number_format($r['tramites']),number_format($r['afiliaciones']),number_format($r['gastos']),number_format($r['utilidad'])]);

        return view('admin.informes.financiero', compact(
            'mes','anio','ingresos','egresos','utilidad',
            'recaudoSS','pagadoSS','saldoSS',
            'comisionesAsesor','gastosOp','tendencia','anterior','bancos','diario'
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

    // ── Helper: desglose diario ──────────────────────────────────────
    private function desgloseDiario(int $aid, int $mes, int $anio): array
    {
        $factDia = DB::table('facturas')
            ->where('aliado_id',$aid)->whereNull('deleted_at')
            ->where('mes',$mes)->where('anio',$anio)
            ->whereIn('estado',['pagada','abono'])
            ->selectRaw('DAY(fecha_pago) AS dia, tipo,
                SUM(admon+seguro+mensajeria+otros+iva+retiro) AS ing_planilla,
                SUM(afiliacion+admon+seguro+iva) AS ing_afil,
                SUM(admon+otros) AS ing_tramite')
            ->groupByRaw('DAY(fecha_pago), tipo')
            ->whereNotNull('fecha_pago')
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
            $filas     = $factDia->get($d, collect());
            $planillas = $filas->where('tipo','planilla')->sum('ing_planilla');
            $afil      = $filas->where('tipo','afiliacion')->sum('ing_afil');
            $tramites  = $filas->where('tipo','otro_ingreso')->sum('ing_tramite');
            $gastos    = (int)($gastosDia[$d] ?? 0);
            $resultado[] = ['dia'=>$d,'planillas'=>$planillas,'afiliaciones'=>$afil,'tramites'=>$tramites,'gastos'=>$gastos,'utilidad'=>$planillas+$afil+$tramites-$gastos];
        }
        return $resultado;
    }

    // ── Helper: resumen de un mes ─────────────────────────────────────
    private function resumenMes(int $aid, int $mes, int $anio): array
    {
        $base = DB::table('facturas')->where('aliado_id',$aid)->whereNull('deleted_at')
            ->where('mes',$mes)->where('anio',$anio)->whereIn('estado',['pagada','abono']);
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
}
