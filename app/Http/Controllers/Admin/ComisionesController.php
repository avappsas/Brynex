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
    // Corte histórico: solo facturas desde mayo 2025 en adelante
    private const CORTE_MES  = 5;
    private const CORTE_ANIO = 2025;

    private function aliadoId(): int
    {
        return (int) session('aliado_id_activo');
    }

    private function checkSuperadmin(): void
    {
        if (!Auth::user()->hasRole('superadmin')) {
            abort(403, 'Acceso restringido a superadmin.');
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
        $this->checkSuperadmin();
        $aid     = $this->aliadoId();
        $mes     = (int) $request->input('mes',     now()->month);
        $anio    = (int) $request->input('anio',    now()->year);
        $asesorId = (int) $request->input('asesor_id', 0);

        // Lista de asesores del aliado (con comisiones > 0 en algún período desde el corte)
        $asesores = Asesor::where('aliado_id', $aid)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // Si se seleccionó un asesor, cargar detalle
        $asesor       = null;
        $facturas     = collect();
        $consolidado  = [];
        $pagos        = collect();
        $saldoTotal   = 0;

        if ($asesorId && $asesores->contains('id', $asesorId)) {
            $asesor = $asesores->firstWhere('id', $asesorId);
            $corte  = $this->condicionCorte('f');

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
                ->where('c.asesor_id', $asesorId)
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
                ->where('c.asesor_id', $asesorId)
                ->where('f.mes', $mes)
                ->where('f.anio', $anio)
                ->selectRaw("
                    ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN f.dist_asesor ELSE 0 END), 0) AS afiliaciones,
                    ISNULL(SUM(CASE WHEN f.tipo = 'planilla'   THEN f.admin_asesor ELSE 0 END), 0) AS planillas
                ")
                ->first();

            // Pagos del período
            $pagosPeriodo = PagoAsesor::where('aliado_id', $aid)
                ->where('asesor_id', $asesorId)
                ->where('periodo_mes', $mes)
                ->where('periodo_anio', $anio)
                ->sum('valor');

            $consolidado = [
                'afiliaciones' => (int) ($resumenPeriodo->afiliaciones ?? 0),
                'planillas'    => (int) ($resumenPeriodo->planillas    ?? 0),
                'total'        => (int) ($resumenPeriodo->afiliaciones ?? 0) + (int) ($resumenPeriodo->planillas ?? 0),
                'pagado'       => (int) $pagosPeriodo,
                'saldo'        => ((int) ($resumenPeriodo->afiliaciones ?? 0) + (int) ($resumenPeriodo->planillas ?? 0)) - (int) $pagosPeriodo,
            ];

            // ── Saldo acumulado total (desde mayo 2025) ──────────────────
            $totalGanado = DB::table('facturas AS f')
                ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
                ->where('f.aliado_id', $aid)
                ->whereNull('f.deleted_at')
                ->where('c.asesor_id', $asesorId)
                ->whereRaw($this->condicionCorte('f'))
                ->selectRaw("
                    ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN f.dist_asesor ELSE 0 END), 0) +
                    ISNULL(SUM(CASE WHEN f.tipo = 'planilla'   THEN f.admin_asesor ELSE 0 END), 0) AS total
                ")
                ->value('total');

            $totalPagado = PagoAsesor::where('aliado_id', $aid)
                ->where('asesor_id', $asesorId)
                ->sum('valor');

            $saldoTotal = max(0, (int) $totalGanado - (int) $totalPagado);

            // ── Historial de pagos del período ───────────────────────────
            $pagos = PagoAsesor::with('bancoCuenta', 'usuario')
                ->where('aliado_id', $aid)
                ->where('asesor_id', $asesorId)
                ->where('periodo_mes', $mes)
                ->where('periodo_anio', $anio)
                ->orderByDesc('fecha')
                ->get();
        }

        $bancos = BancoCuenta::where('aliado_id', $aid)->where('activo', true)->get();

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
        $this->checkSuperadmin();
        $aid      = $this->aliadoId();
        $mes      = (int) $request->input('mes',  now()->month);
        $anio     = (int) $request->input('anio', now()->year);
        $asesorId = (int) $request->input('asesor_id', 0);
        $filtro   = $request->input('filtro', 'todas'); // 'todas' | 'sin_distribuir'

        $query = DB::table('facturas AS f')
            ->join('contratos AS c', 'c.id', '=', 'f.contrato_id')
            ->leftJoin('asesores AS a', 'a.id', '=', 'c.asesor_id')
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

        if ($asesorId) {
            $query->where('c.asesor_id', $asesorId);
        }

        if ($filtro === 'sin_distribuir') {
            $query->where(function ($q) {
                $q->whereNull('f.dist_asesor')
                  ->orWhere('f.dist_asesor', 0)
                  ->where(function ($q2) {
                      $q2->whereNull('f.dist_retiro')->orWhere('f.dist_retiro', 0);
                  })
                  ->where(function ($q2) {
                      $q2->whereNull('f.dist_admon')->orWhere('f.dist_admon', 0);
                  });
            });
        }

        $facturas = $query->selectRaw("
                f.id,
                f.numero_factura,
                f.afiliacion,
                f.dist_asesor,
                f.dist_retiro,
                f.dist_admon,
                f.dist_utilidad,
                f.estado,
                f.cedula,
                CONVERT(VARCHAR(10), f.fecha_pago, 120) AS fecha_pago,
                ISNULL(a.nombre, '—') AS asesor_nombre,
                c.asesor_id,
                LTRIM(RTRIM(
                    ISNULL(cl.primer_nombre,'') + ' ' +
                    ISNULL(cl.segundo_nombre,'') + ' ' +
                    ISNULL(cl.primer_apellido,'') + ' ' +
                    ISNULL(cl.segundo_apellido,'')
                )) AS nombre_cliente,
                ISNULL(em.empresa, '—') AS empresa_nombre
            ")
            ->orderBy('f.numero_factura')
            ->get()
            ->map(function ($f) {
                $f->distribuida = (
                    ((int)$f->dist_asesor  + (int)$f->dist_retiro +
                     (int)$f->dist_admon   + (int)$f->dist_utilidad) > 0
                );
                return $f;
            });

        $asesores = Asesor::where('aliado_id', $aid)->where('activo', true)->orderBy('nombre')->get();

        $totalSinDistribuir = $facturas->where('distribuida', false)->count();

        return view('admin.informes.comisiones.afiliaciones', compact(
            'facturas', 'asesores', 'mes', 'anio', 'asesorId',
            'filtro', 'totalSinDistribuir'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/informes/comisiones/afiliaciones/{id}
    // Guardar distribución de una factura de afiliación
    // ─────────────────────────────────────────────────────────────────
    public function distribuir(Request $request, int $id)
    {
        $this->checkSuperadmin();
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
        ]);

        $suma = $validated['dist_asesor'] + $validated['dist_retiro']
              + $validated['dist_admon']  + $validated['dist_utilidad'];

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
    public function pagar(Request $request, int $asesorId)
    {
        $this->checkSuperadmin();
        $aid = $this->aliadoId();

        $asesor = Asesor::where('id', $asesorId)
            ->where('aliado_id', $aid)
            ->firstOrFail();

        $validated = $request->validate([
            'valor'           => 'required|integer|min:1',
            'fecha'           => 'required|date',
            'tipo'            => 'required|in:efectivo,banco',
            'banco_cuenta_id' => 'nullable|required_if:tipo,banco|integer',
            'periodo_mes'     => 'required|integer|between:1,12',
            'periodo_anio'    => 'required|integer|min:2025',
            'observacion'     => 'nullable|string|max:500',
        ]);

        $pago = PagoAsesor::create([
            'aliado_id'      => $aid,
            'asesor_id'      => $asesorId,
            'valor'          => $validated['valor'],
            'fecha'          => $validated['fecha'],
            'tipo'           => $validated['tipo'],
            'banco_cuenta_id'=> $validated['banco_cuenta_id'] ?? null,
            'periodo_mes'    => $validated['periodo_mes'],
            'periodo_anio'   => $validated['periodo_anio'],
            'observacion'    => $validated['observacion'] ?? null,
            'usuario_id'     => Auth::id(),
        ]);

        \App\Models\Bitacora::registrar(
            'created', 'PagoAsesor', $pago->id,
            "Pago registrado a asesor {$asesor->nombre}: \${$pago->valor} ({$pago->tipo}).",
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
            ->whereNotNull('c.asesor_id')
            ->whereRaw("(f.anio > {$corte_anio} OR (f.anio = {$corte_anio} AND f.mes >= {$corte_mes}))")
            ->selectRaw("
                ISNULL(SUM(CASE WHEN f.tipo = 'afiliacion' THEN ISNULL(f.dist_asesor, 0) ELSE 0 END), 0) +
                ISNULL(SUM(CASE WHEN f.tipo = 'planilla'   THEN ISNULL(f.admin_asesor, 0) ELSE 0 END), 0) AS total
            ")
            ->value('total');

        $pagado = DB::table('pagos_asesores')
            ->where('aliado_id', $aliadoId)
            ->sum('valor');

        return max(0, (int) $ganado - (int) $pagado);
    }
}
