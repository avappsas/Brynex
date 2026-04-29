<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Factura, Abono, BitacoraCobro, Contrato, Empresa, BancoCuenta};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class PrestamosController extends Controller
{
    // ─── Index: lista de préstamos pendientes ────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $buscar   = $request->get('buscar');
        $tab      = $request->get('tab', 'individuales'); // individuales | empresas
        $sort     = $request->get('sort', 'antiguedad');

        // ── Préstamos individuales (empresa_id NULL o empresa_id=1) ──
        $qInd = Factura::where('aliado_id', $aliadoId)
            ->prestamoPendiente()
            ->whereNull('empresa_id')
            ->with(['contrato.cliente', 'contrato.asesor', 'abonos'])
            ->get()
            ->filter(fn($f) => $f->saldo_pendiente_prestamo > 0);

        // ── Préstamos de empresas (empresa_id NOT NULL, excluyendo empresa=1) ──
        $qEmp = Factura::where('aliado_id', $aliadoId)
            ->prestamoPendiente()
            ->whereNotNull('empresa_id')
            ->where('empresa_id', '!=', 1)
            ->with(['empresa', 'abonos'])
            ->get()
            ->filter(fn($f) => $f->saldo_pendiente_prestamo > 0);

        // ── Búsqueda ──────────────────────────────────────────────────
        if ($buscar) {
            $b = strtolower($buscar);
            $qInd = $qInd->filter(function ($f) use ($b) {
                $nombre = strtolower(
                    ($f->contrato?->cliente?->primer_nombre ?? '') . ' ' .
                    ($f->contrato?->cliente?->primer_apellido ?? '')
                );
                return str_contains((string)$f->cedula, $b) || str_contains($nombre, $b);
            });
            $qEmp = $qEmp->filter(function ($f) use ($b) {
                return str_contains(strtolower($f->empresa?->empresa ?? ''), $b);
            });
        }

        // ── Agrupar empresas por empresa_id ───────────────────────────
        $empresasAgrupadas = $qEmp->groupBy('empresa_id')->map(function ($facturas) {
            $empresa     = $facturas->first()->empresa;
            $totalDeuda  = $facturas->sum('saldo_pendiente_prestamo');
            $totalOrig   = $facturas->sum('total');
            $totalAbonado= $facturas->sum(fn($f) => (int)$f->abonos->sum('valor'));

            // Última gestión de cobro de esta empresa
            $ultimaGestion = BitacoraCobro::where('empresa_id', $empresa?->id)
                ->where('tipo', BitacoraCobro::TIPO_PRESTAMO)
                ->latest('fecha_llamada')
                ->first();

            $diasSinGestion = $ultimaGestion
                ? (int)$ultimaGestion->fecha_llamada->diffInDays(now())
                : null;

            return (object)[
                'empresa'         => $empresa,
                'facturas'        => $facturas,
                'total_deuda'     => $totalDeuda,
                'total_original'  => $totalOrig,
                'total_abonado'   => $totalAbonado,
                'ultima_gestion'  => $ultimaGestion,
                'dias_sin_gestion'=> $diasSinGestion,
                'semaforo'        => $this->calcularSemaforo($diasSinGestion),
                'cant_facturas'   => $facturas->count(),
            ];
        })->sortByDesc('total_deuda')->values();

        // ── Enriquecer individuales con última gestión y semáforo ─────
        $individuales = $qInd->map(function ($f) {
            $ultimaGestion = BitacoraCobro::where('factura_id', $f->id)
                ->where('tipo', BitacoraCobro::TIPO_PRESTAMO)
                ->latest('fecha_llamada')
                ->first();
            $dias = $ultimaGestion
                ? (int)$ultimaGestion->fecha_llamada->diffInDays(now())
                : null;

            $f->ultima_gestion   = $ultimaGestion;
            $f->dias_sin_gestion = $dias;
            $f->semaforo         = $this->calcularSemaforo($dias);
            return $f;
        })->sortByDesc('total')->values();

        // ── Cards resumen ─────────────────────────────────────────────
        $totalDeudaInd  = $individuales->sum('saldo_pendiente_prestamo');
        $totalDeudaEmp  = $empresasAgrupadas->sum('total_deuda');
        $totalPrestamos = $individuales->count() + $empresasAgrupadas->sum('cant_facturas');
        $sinGestion     = $individuales->whereIn('semaforo', ['gris', 'rojo'])->count()
                        + $empresasAgrupadas->whereIn('semaforo', ['gris', 'rojo'])->count();

        return view('admin.prestamos.index', compact(
            'individuales', 'empresasAgrupadas',
            'tab', 'buscar', 'sort',
            'totalDeudaInd', 'totalDeudaEmp', 'totalPrestamos', 'sinGestion'
        ));
    }

    // ─── Detalle de un préstamo ──────────────────────────────────────
    public function show(int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');

        $factura = Factura::where('aliado_id', $aliadoId)
            ->with([
                'contrato.cliente',
                'contrato.asesor',
                'empresa',
                'abonos.usuario',
                'usuario',
            ])
            ->findOrFail($facturaId);

        $gestiones = BitacoraCobro::where('factura_id', $facturaId)
            ->where('tipo', BitacoraCobro::TIPO_PRESTAMO)
            ->with('usuario')
            ->orderByDesc('fecha_llamada')
            ->get();

        $bancos = BancoCuenta::activas($aliadoId);

        return view('admin.prestamos.show', compact('factura', 'gestiones', 'bancos'));
    }

    // ─── Registrar abono al préstamo ─────────────────────────────────
    public function abonar(Request $request, int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->where('estado', Factura::ESTADO_PRESTAMO)
            ->whereNull('deleted_at')
            ->findOrFail($facturaId);

        $validated = $request->validate([
            'valor'            => 'required|numeric|min:1',
            'forma_pago'       => 'required|in:efectivo,consignacion,mixto',
            'valor_efectivo'   => 'nullable|numeric|min:0',
            'valor_consignado' => 'nullable|numeric|min:0',
            'banco_cuenta_id'  => 'nullable|integer',
            'observacion'      => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($factura, $validated) {
            $abono = Abono::create([
                'factura_id'       => $factura->id,
                'valor'            => (int)$validated['valor'],
                'forma_pago'       => $validated['forma_pago'],
                'valor_efectivo'   => (int)($validated['valor_efectivo']   ?? 0),
                'valor_consignado' => (int)($validated['valor_consignado'] ?? 0),
                'banco_cuenta_id'  => $validated['banco_cuenta_id'] ?? null,
                'observacion'      => $validated['observacion'] ?? null,
                'fecha'            => today()->toDateString(),
                'usuario_id'       => Auth::id(),
            ]);

            // Refrescar para recalcular total_abonado
            $factura->refresh();

            if ($factura->estaCompletamentePagada()) {
                // El préstamo quedó saldado: marcar como pagada y actualizar saldo_proximo
                $factura->update([
                    'estado'        => Factura::ESTADO_PAGADA,
                    'saldo_proximo' => 0, // la deuda ya se cobró, neutralizar
                ]);
            } else {
                // Ajustar saldo_proximo: era -(total), ahora es -(saldo restante)
                $factura->update([
                    'saldo_proximo' => -$factura->saldo_pendiente_prestamo,
                ]);
            }
        });

        $factura->refresh();

        return response()->json([
            'ok'             => true,
            'mensaje'        => $factura->estaCompletamentePagada()
                ? '✅ Préstamo saldado completamente.'
                : '💰 Abono registrado. Saldo pendiente: $' . number_format($factura->saldo_pendiente_prestamo, 0, ',', '.'),
            'saldo_restante' => $factura->saldo_pendiente_prestamo,
            'pagado'         => $factura->estado === Factura::ESTADO_PAGADA,
            'estado'         => $factura->estado,
        ]);
    }

    // ─── Condonar préstamo (solo superadmin) ─────────────────────────
    public function condonar(Request $request, int $facturaId)
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->where('estado', Factura::ESTADO_PRESTAMO)
            ->findOrFail($facturaId);

        $motivo = trim($request->input('motivo', ''));
        if (!$motivo) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe indicar el motivo de la condonación.'], 422);
        }

        $factura->update([
            'estado'        => Factura::ESTADO_PAGADA,
            'saldo_proximo' => 0,
            'observacion'   => ($factura->observacion ? $factura->observacion . ' | ' : '') .
                               'CONDONADO: ' . $motivo . ' — ' . Auth::user()->nombre . ' ' . now()->format('d/m/Y'),
        ]);

        return response()->json([
            'ok'     => true,
            'mensaje'=> 'Préstamo condonado correctamente.',
        ]);
    }

    // ─── Registrar gestión de cobro ──────────────────────────────────
    public function registrarGestion(Request $request, int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');

        $factura = Factura::where('aliado_id', $aliadoId)->findOrFail($facturaId);

        $validated = $request->validate([
            'resultado'   => 'required|in:no_contesta,promesa_pago,pagado,numero_errado,otro',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $gestion = BitacoraCobro::create([
            'aliado_id'    => $aliadoId,
            'contrato_id'  => $factura->contrato_id ?? 0,
            'empresa_id'   => $factura->empresa_id ?? null,
            'factura_id'   => $factura->id,
            'usuario_id'   => Auth::id(),
            'fecha_llamada'=> now(),
            'resultado'    => $validated['resultado'],
            'observacion'  => $validated['observacion'] ?? null,
            'tipo'         => BitacoraCobro::TIPO_PRESTAMO,
        ]);

        return response()->json([
            'ok'         => true,
            'gestion_id' => $gestion->id,
            'resultado'  => $gestion->resultado,
            'etiqueta'   => $gestion->etiqueta_resultado,
            'fecha'      => $gestion->fecha_llamada->format('d/m/Y H:i'),
            'usuario'    => Auth::user()->nombre ?? Auth::user()->name,
            'semaforo'   => 'verde',
            'dias'       => 0,
        ]);
    }

    // ─── Historial de gestiones ──────────────────────────────────────
    public function historialGestiones(int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        Factura::where('aliado_id', $aliadoId)->findOrFail($facturaId);

        $gestiones = BitacoraCobro::where('factura_id', $facturaId)
            ->where('tipo', BitacoraCobro::TIPO_PRESTAMO)
            ->with('usuario')
            ->orderByDesc('fecha_llamada')
            ->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'fecha'       => $g->fecha_llamada->format('d/m/Y H:i'),
                'resultado'   => $g->resultado,
                'etiqueta'    => $g->etiqueta_resultado,
                'observacion' => $g->observacion,
                'usuario'     => $g->usuario?->nombre ?? $g->usuario?->name ?? '—',
                'dias'        => $g->dias,
            ]);

        return response()->json(['ok' => true, 'gestiones' => $gestiones]);
    }

    // ─── API ligera: cédulas con préstamo pendiente ──────────────────
    // Usada por CobrosController para badges (1 query, sin N+1)
    public function apiPendientes()
    {
        $aliadoId = session('aliado_id_activo');

        $pendientes = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->where('estado', 'prestamo')
            ->whereNull('deleted_at')
            ->select('cedula', 'empresa_id', DB::raw('SUM(total) as total_prestado'))
            ->groupBy('cedula', 'empresa_id')
            ->get()
            ->mapWithKeys(fn($r) => [$r->cedula => (int)$r->total_prestado]);

        return response()->json(['ok' => true, 'pendientes' => $pendientes]);
    }

    // ─── Helper: calcular semáforo por días sin gestión ──────────────
    private function calcularSemaforo(?int $dias): string
    {
        return match(true) {
            $dias === null  => 'gris',
            $dias < 3       => 'verde',
            $dias <= 7      => 'amarillo',
            default         => 'rojo',
        };
    }
}
