<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Cuadre, Gasto, CajaMenor, Consignacion, BancoCuenta, Factura, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CuadreDiarioController extends Controller
{
    // ── Index: cuadre propio del usuario ─────────────────────────────
    public function index(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();
        $fecha     = $request->input('fecha', today()->toDateString());

        // Cuadre abierto actual del usuario
        $cuadre = Cuadre::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'abierto')
            ->latest('fecha_inicio')
            ->first();

        $cajaMenor = CajaMenor::montoActivo($aliadoId, $usuarioId);
        $bancos    = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();

        // Datos para el cuadre activo
        $datosPeriodo = $cuadre ? $this->calcularPeriodo($cuadre, $aliadoId, $usuarioId) : null;

        // Gastos del cuadre actual
        $gastos = $cuadre
            ? Gasto::where('cuadre_id', $cuadre->id)
                ->with(['bancoOrigen', 'bancoDestino', 'usuario'])
                ->orderBy('fecha')->orderBy('id')
                ->get()
            : collect();

        // Facturas del período (si hay cuadre abierto)
        $facturasPeriodo = $cuadre ? $this->facturasPeriodo($cuadre, $aliadoId, $usuarioId) : collect();

        // Cuadres anteriores (máx 15 días atrás)
        $cuadresAnteriores = Cuadre::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'cerrado')
            ->where('fecha_inicio', '>=', now()->subDays(15)->toDateString())
            ->orderByDesc('fecha_inicio')
            ->with(['cerradoPor'])
            ->get();

        return view('admin.cuadre-diario.index', compact(
            'cuadre', 'cajaMenor', 'bancos', 'datosPeriodo',
            'gastos', 'facturasPeriodo', 'cuadresAnteriores'
        ));
    }

    // ── Abrir cuadre ─────────────────────────────────────────────────
    public function abrir(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        // Validar que no haya cuadre abierto
        $existente = Cuadre::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'abierto')
            ->exists();

        if ($existente) {
            return back()->with('error', 'Ya tienes un cuadre abierto.');
        }

        $cajaMenor = CajaMenor::montoActivo($aliadoId, $usuarioId);

        Cuadre::create([
            'aliado_id'      => $aliadoId,
            'usuario_id'     => $usuarioId,
            'fecha_inicio'   => today(),
            'estado'         => 'abierto',
            'saldo_apertura' => $cajaMenor,
        ]);

        return redirect()->route('admin.cuadre-diario.index')
            ->with('success', 'Cuadre abierto correctamente.');
    }

    // ── Ver cuadre específico ─────────────────────────────────────────
    public function ver(int $id)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();
        $esAdmin   = Auth::user()->hasRole(['admin', 'superadmin']);

        $cuadre = Cuadre::where('aliado_id', $aliadoId)
            ->when(!$esAdmin, fn($q) => $q->where('usuario_id', $usuarioId))
            ->with(['usuario', 'cerradoPor'])
            ->findOrFail($id);

        $gastos = Gasto::where('cuadre_id', $cuadre->id)
            ->with(['bancoOrigen', 'bancoDestino', 'usuario'])
            ->orderBy('fecha')->get();

        $facturasPeriodo = $this->facturasPeriodo($cuadre, $aliadoId, $cuadre->usuario_id);
        $datosPeriodo    = $this->calcularPeriodo($cuadre, $aliadoId, $cuadre->usuario_id);
        $bancos          = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();
        $cajaMenor       = $cuadre->saldo_apertura;

        return view('admin.cuadre-diario.index', compact(
            'cuadre', 'cajaMenor', 'bancos', 'datosPeriodo',
            'gastos', 'facturasPeriodo'
        ));
    }

    // ── Consolidado Admin ────────────────────────────────────────────
    public function consolidado(Request $request)
    {
        $this->authorize('viewAny', Cuadre::class);
        $aliadoId = session('aliado_id_activo');
        $fecha    = $request->input('fecha', today()->toDateString());
        $usuarioFiltro = $request->input('usuario_id');

        $usuarios = User::where('aliado_id', $aliadoId)->orderBy('nombre')->get();

        $cuadresQuery = Cuadre::where('aliado_id', $aliadoId)
            ->with(['usuario', 'cerradoPor'])
            ->where(function($q) use ($fecha) {
                $q->where('fecha_inicio', '<=', $fecha)
                  ->where(function($q2) use ($fecha) {
                      $q2->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $fecha);
                  });
            });

        if ($usuarioFiltro) {
            $cuadresQuery->where('usuario_id', $usuarioFiltro);
        }

        $cuadres = $cuadresQuery->orderBy('usuario_id')->get();

        // Calcular resumen por cuadre
        $resumen = $cuadres->map(function($c) use ($aliadoId) {
            $datos = $this->calcularPeriodo($c, $aliadoId, $c->usuario_id);
            return (object)[
                'cuadre'          => $c,
                'efectivo_total'  => $datos['efectivo_total'],
                'gastos_efectivo' => $datos['gastos_efectivo'],
                'saldo_esperado'  => $datos['saldo_final'],
            ];
        });

        // Saldos bancarios actuales (calculados desde consignaciones + gastos)
        $saldosBanco = BancoCuenta::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->get()
            ->map(fn($bc) => [
                'banco' => $bc,
                'saldo' => Consignacion::saldoBanco($aliadoId, $bc->id),
            ]);

        return view('admin.cuadre-diario.consolidado', compact(
            'cuadres', 'resumen', 'usuarios', 'fecha', 'saldosBanco'
        ));
    }

    // ── Registrar gasto ──────────────────────────────────────────────
    public function registrarGasto(Request $request, int $cuadreId)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();
        $esAdmin   = Auth::user()->hasRole(['admin', 'superadmin']);

        $cuadre = Cuadre::where('aliado_id', $aliadoId)
            ->where('estado', 'abierto')
            ->findOrFail($cuadreId);

        $validated = $request->validate([
            'fecha'             => 'required|date',
            'tipo'              => 'required|string',
            'descripcion'       => 'required|string|max:500',
            'pagado_a'          => 'nullable|string|max:255',
            'forma_pago'        => 'required|in:efectivo,transferencia_bancaria,banco_banco',
            'banco_origen_id'   => 'nullable|integer',
            'banco_destino_id'  => 'nullable|integer',
            'valor'             => 'required|integer|min:1',
            'recibo_caja'       => 'nullable|string|max:100',
            'observacion'       => 'nullable|string',
        ]);

        // Validar tipos de admin
        if (in_array($validated['tipo'], Gasto::TIPOS_ADMIN) && !$esAdmin) {
            return back()->with('error', 'No tienes permiso para este tipo de gasto.');
        }

        DB::beginTransaction();
        try {
            $gasto = Gasto::create(array_merge($validated, [
                'aliado_id'  => $aliadoId,
                'usuario_id' => $usuarioId,
                'cuadre_id'  => $cuadreId,
            ]));

            // ── Traslado efectivo → banco ────────────────────────────────
            // Registra como consignación interna (tipo traslado_efectivo)
            // para que el saldo bancario lo cuente como entrada.
            if ($validated['tipo'] === 'efectivo_banco' && !empty($validated['banco_origen_id'])) {
                Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'banco_cuenta_id' => $validated['banco_origen_id'],
                    'factura_id'      => null,
                    'fecha'           => $validated['fecha'],
                    'valor'           => $validated['valor'],
                    'tipo'            => Consignacion::TIPO_TRASLADO_EFECTIVO,
                    'referencia'      => 'Cuadre #' . $cuadreId,
                    'confirmado'      => true,
                    'observacion'     => $validated['descripcion'],
                    'usuario_id'      => $usuarioId,
                ]);
            }

            // ── Banco → Banco ────────────────────────────────────────────
            // El gasto ya registra la salida del origen (banco_origen_id).
            // Aquí creamos la consignación de entrada en el banco destino.
            if ($validated['forma_pago'] === 'banco_banco' && !empty($validated['banco_destino_id'])) {
                Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'banco_cuenta_id' => $validated['banco_destino_id'],
                    'factura_id'      => null,
                    'fecha'           => $validated['fecha'],
                    'valor'           => $validated['valor'],
                    'tipo'            => Consignacion::TIPO_BANCO_RECIBIDO,
                    'referencia'      => 'Transferencia desde banco origen',
                    'confirmado'      => true,
                    'observacion'     => $validated['descripcion'],
                    'usuario_id'      => $usuarioId,
                ]);
            }
            // Nota: gastos con forma_pago='transferencia_bancaria' (pago de gasto)
            // y banco_banco (débito del origen) quedan como salidas en la fórmula
            // Consignacion::saldoBanco() que descuenta gastos.banco_origen_id.

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar el gasto: ' . $e->getMessage());
        }

        return back()->with('success', 'Gasto registrado correctamente.');
    }

    // ── Eliminar gasto ───────────────────────────────────────────────
    public function eliminarGasto(int $gastoId)
    {
        $aliadoId = session('aliado_id_activo');
        $gasto = Gasto::where('aliado_id', $aliadoId)->findOrFail($gastoId);

        // Solo puede eliminar el propio o admin
        if ($gasto->usuario_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'superadmin'])) {
            return back()->with('error', 'Sin permiso.');
        }

        $gasto->delete();
        return back()->with('success', 'Gasto eliminado.');
    }

    // ── Cerrar cuadre (solo superadmin) ──────────────────────────────
    public function cerrar(Request $request, int $cuadreId)
    {
        if (!Auth::user()->hasRole('superadmin')) {
            abort(403, 'Solo el Superadmin puede cerrar un cuadre.');
        }

        $aliadoId = session('aliado_id_activo');
        $cuadre   = Cuadre::where('aliado_id', $aliadoId)
            ->where('estado', 'abierto')
            ->findOrFail($cuadreId);

        $datos = $this->calcularPeriodo($cuadre, $aliadoId, $cuadre->usuario_id);

        $cuadre->update([
            'estado'       => 'cerrado',
            'fecha_fin'    => today(),
            'saldo_cierre' => $datos['saldo_final'],
            'cerrado_por'  => Auth::id(),
            'observacion'  => $request->input('observacion'),
        ]);

        return back()->with('success', 'Cuadre cerrado. Saldo: $' . number_format($datos['saldo_final'], 0, ',', '.'));
    }

    // ── Saldos bancarios (solo admin/superadmin) ─────────────────────
    public function bancos(Request $request)
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }

        $aliadoId = session('aliado_id_activo');
        // Filtro por mes (default: mes actual)
        $mes = $request->input('mes', now()->format('Y-m'));
        [$anio, $mesNum] = explode('-', $mes);
        $inicio = "{$anio}-{$mesNum}-01";
        $fin    = date('Y-m-t', strtotime($inicio));

        $bancos = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();

        $saldos = $bancos->map(function ($bc) use ($aliadoId, $inicio, $fin) {
            // Entradas: consignaciones del mes filtrado
            $movEntradas = Consignacion::where('aliado_id', $aliadoId)
                ->where('banco_cuenta_id', $bc->id)
                ->whereBetween('fecha', [$inicio, $fin])
                ->with(['usuario', 'factura.empresa'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->get()
                ->map(function ($c) {
                    // Nombre del pagador:
                    // 1. Si es factura de empresa → razon_social de empresa
                    // 2. Si es factura individual → nombre del cliente por cédula
                    $pagador = null;
                    if ($c->factura) {
                        if ($c->factura->empresa) {
                            $pagador = $c->factura->empresa->empresa; // campo 'empresa' en tabla empresas
                        } elseif ($c->factura->cedula) {
                            $cli = DB::table('clientes')
                                ->where('cedula', $c->factura->cedula)
                                ->select('nombre')
                                ->first();
                            $pagador = $cli?->nombre;
                        }
                    }
                    return (object)[
                        'id'          => $c->id,
                        'cs_id'       => $c->id,
                        'fecha'       => $c->fecha,
                        'tipo'        => $c->tipo ?? 'cliente',
                        'confirmado'  => (bool)$c->confirmado,
                        'factura_id'  => $c->factura_id,
                        'num_factura' => $c->factura?->numero_factura ?? $c->factura_id,
                        'pagador'     => $pagador,
                        'descripcion' => match($c->tipo ?? 'cliente') {
                            'traslado_efectivo' => 'Traslado efectivo → banco',
                            'banco_recibido'    => 'Transferencia banco recibida',
                            default             => $c->observacion,
                        },
                        'usuario'     => $c->usuario,
                        'valor'       => $c->valor,
                        'imagen_path' => $c->imagen_path,
                        'imagen_url'  => $c->imagen_path ? Storage::url($c->imagen_path) : null,
                        'es_salida'   => false,
                        'es_gasto'    => false,
                        'referencia'  => $c->referencia,
                    ];
                });

            // Salidas: gastos del mes filtrado pagados desde este banco
            $movSalidas = Gasto::where('aliado_id', $aliadoId)
                ->where('banco_origen_id', $bc->id)
                ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
                ->whereBetween('fecha', [$inicio, $fin])
                ->with(['usuario', 'bancoDestino'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->get()
                ->map(fn($g) => (object)[
                    'id'          => $g->id,
                    'fecha'       => $g->fecha,
                    'tipo'        => $g->tipo,
                    'confirmado'  => true,
                    'factura_id'  => null,
                    'num_factura' => null,
                    'pagador'     => $g->pagado_a,
                    'descripcion' => $g->descripcion
                                     . ($g->bancoDestino ? ' → ' . $g->bancoDestino->banco : ''),
                    'usuario'     => $g->usuario,
                    'valor'       => $g->valor,
                    'imagen_path' => $g->imagen_path,
                    'imagen_url'  => $g->imagen_path ? Storage::url($g->imagen_path) : null,
                    'es_salida'   => true,
                    'es_gasto'    => true,
                    'referencia'  => null,
                ]);

            $movimientos = $movEntradas->merge($movSalidas)
                ->sortByDesc('fecha')->values();

            return [
                'banco'       => $bc,
                'saldo'       => Consignacion::saldoBanco($aliadoId, $bc->id),
                'movimientos' => $movimientos,
            ];
        });

        return view('admin.cuadre-diario.bancos', compact('saldos', 'bancos', 'mes'));
    }

    // ── Confirmar consignación ────────────────────────────────────────
    public function confirmarConsignacion(Request $request, int $csId)
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }
        $aliadoId = session('aliado_id_activo');
        $cs = Consignacion::where('aliado_id', $aliadoId)->findOrFail($csId);
        $cs->update(['confirmado' => true]);
        return back()->with('success', 'Consignación verificada ✅');
    }

    // ── Subir imagen de gasto ─────────────────────────────────────────
    public function subirImagenGasto(Request $request, int $gastoId)
    {
        $aliadoId = session('aliado_id_activo');
        $gasto = Gasto::where('aliado_id', $aliadoId)->findOrFail($gastoId);

        if (!Auth::user()->hasRole(['admin', 'superadmin']) && $gasto->usuario_id !== Auth::id()) {
            abort(403);
        }

        $request->validate(['imagen' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('imagen')->store('gastos', 'public');
        $gasto->update(['imagen_path' => $path]);

        return back()->with('success', 'Imagen del gasto guardada.');
    }

    // ── Subir imagen de consignación ──────────────────────────────────
    public function subirImagenConsignacion(Request $request, int $csId)
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }
        $aliadoId = session('aliado_id_activo');
        $cs = Consignacion::where('aliado_id', $aliadoId)->findOrFail($csId);

        $request->validate(['imagen' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('imagen')->store('consignaciones', 'public');
        $cs->update(['imagen_path' => $path]);

        return back()->with('success', 'Comprobante de consignación guardado.');
    }

    // ── Reversar consignación a pendiente ──────────────────────────
    public function reversarConsignacion(Request $request, int $csId)
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }
        $aliadoId = session('aliado_id_activo');
        $cs = Consignacion::where('aliado_id', $aliadoId)->findOrFail($csId);
        $cs->update(['confirmado' => false]);
        return back()->with('success', 'Consignación marcada como pendiente.');
    }

    // ── Calcular datos del período ────────────────────────────────────
    private function calcularPeriodo(Cuadre $cuadre, int $aliadoId, int $usuarioId): array
    {
        $inicio = $cuadre->fecha_inicio->toDateString();
        $fin    = ($cuadre->fecha_fin ?? today())->toDateString();

        // Ingresos en efectivo del período (facturas)
        $ingresosEfectivo = (int) Factura::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->whereNotNull('valor_efectivo')
            ->sum('valor_efectivo');

        // Gastos en efectivo del período
        $gastosEfectivo = (int) Gasto::where('cuadre_id', $cuadre->id)
            ->where(fn($q) => $q->where('forma_pago', 'efectivo')
                ->orWhere('tipo', 'efectivo_banco'))
            ->sum('valor');

        $saldoInicial = $cuadre->saldo_apertura;
        $saldoFinal   = $saldoInicial + $ingresosEfectivo - $gastosEfectivo;

        // Por día
        $dias = $cuadre->diasDelPeriodo();
        $saldoAcum = $saldoInicial;
        $porDia = $dias->map(function($dia) use ($cuadre, $aliadoId, $usuarioId, &$saldoAcum) {
            $fechaDia = $dia->toDateString();

            $ingDia = (int) Factura::where('aliado_id', $aliadoId)
                ->where('usuario_id', $usuarioId)
                ->whereDate('fecha_pago', $fechaDia)
                ->sum('valor_efectivo');

            $gastoDia = (int) Gasto::where('cuadre_id', $cuadre->id)
                ->whereDate('fecha', $fechaDia)
                ->where(fn($q) => $q->where('forma_pago', 'efectivo')
                    ->orWhere('tipo', 'efectivo_banco'))
                ->sum('valor');

            $saldoAcum += $ingDia - $gastoDia;

            return [
                'fecha'        => $dia,
                'ingresos'     => $ingDia,
                'gastos'       => $gastoDia,
                'saldo'        => $saldoAcum,
            ];
        });

        return [
            'efectivo_total'  => $ingresosEfectivo,
            'gastos_efectivo' => $gastosEfectivo,
            'saldo_inicial'   => $saldoInicial,
            'saldo_final'     => $saldoFinal,
            'por_dia'         => $porDia,
        ];
    }

    /** Facturas del período del cuadre */
    private function facturasPeriodo(Cuadre $cuadre, int $aliadoId, int $usuarioId): \Illuminate\Support\Collection
    {
        $inicio = $cuadre->fecha_inicio->toDateString();
        $fin    = ($cuadre->fecha_fin ?? today())->toDateString();

        return Factura::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->with(['empresa', 'contrato', 'consignaciones.bancoCuenta'])
            ->orderBy('fecha_pago')
            ->get();
    }
}
