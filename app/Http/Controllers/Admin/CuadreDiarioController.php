<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Cuadre, Gasto, CajaMenor, Consignacion, BancoCuenta, Factura, User, Anticipo};
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
        $usuarios  = User::where('aliado_id', $aliadoId)->where('activo', true)->orderBy('nombre')->get(['id','nombre']);

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
            'cuadre', 'cajaMenor', 'bancos', 'usuarios', 'datosPeriodo',
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
        $usuarios        = User::where('aliado_id', $aliadoId)->where('activo', true)->orderBy('nombre')->get(['id','nombre']);
        $cajaMenor       = $cuadre->saldo_apertura;

        return view('admin.cuadre-diario.index', compact(
            'cuadre', 'cajaMenor', 'bancos', 'usuarios', 'datosPeriodo',
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

        set_time_limit(120); // protección ante meses con muchos movimientos

        $aliadoId = session('aliado_id_activo');
        $mes      = $request->input('mes', now()->format('Y-m'));
        [$anio, $mesNum] = explode('-', $mes);
        $inicio = "{$anio}-{$mesNum}-01";
        $fin    = date('Y-m-t', strtotime($inicio));

        $bancos   = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();
        $bancoIds = $bancos->pluck('id')->toArray();

        // ── 1. Cargar TODAS las consignaciones con nombres via JOIN (igual que financiero) ──
        // Un solo query con LEFT JOIN a clientes y empresas — replica exactamente
        // el patrón de InformeController::movimientosBancos() que funciona correctamente.
        $todasConsigRaw = DB::table('consignaciones AS cs')
            ->leftJoin('facturas AS f',  'f.id',  '=', 'cs.factura_id')
            ->leftJoin('clientes AS cl', function ($j) use ($aliadoId) {
                $j->on('cl.cedula', '=', 'f.cedula')
                  ->where('cl.aliado_id', $aliadoId);
            })
            ->leftJoin('empresas AS em', 'em.id', '=', 'f.empresa_id')
            ->leftJoin('users AS u',     'u.id',  '=', 'cs.usuario_id')
            ->where('cs.aliado_id', $aliadoId)
            ->whereIn('cs.banco_cuenta_id', $bancoIds)
            ->whereBetween('cs.fecha', [$inicio, $fin])
            ->selectRaw("
                cs.id,
                cs.banco_cuenta_id,
                cs.factura_id,
                cs.tipo,
                cs.referencia,
                cs.observacion,
                cs.valor,
                cs.confirmado,
                cs.imagen_path,
                CONVERT(VARCHAR(10), cs.fecha, 120) AS fecha,
                f.numero_factura,
                f.empresa_id,
                CASE
                    WHEN f.empresa_id IS NOT NULL AND f.empresa_id > 0
                        THEN UPPER(ISNULL(em.empresa, '—'))
                    ELSE
                        LTRIM(RTRIM(
                            ISNULL(cl.primer_nombre,'') + ' ' +
                            ISNULL(cl.segundo_nombre,'') + ' ' +
                            ISNULL(cl.primer_apellido,'') + ' ' +
                            ISNULL(cl.segundo_apellido,'')
                        ))
                END AS nombre_cliente,
                u.nombre AS usuario_nombre
            ")
            ->orderByDesc('cs.fecha')
            ->orderByDesc('cs.id')
            ->get();

        // ── 2. Cargar anticipos relacionados (batch) ─────────────────────────
        $anticIds = $todasConsigRaw->where('tipo', 'anticipo')->pluck('id');
        $anticipos = collect();
        if ($anticIds->isNotEmpty()) {
            $anticipos = \App\Models\Anticipo::whereIn('id', $anticIds)
                ->with('factura')
                ->get()
                ->keyBy('id');
        }

        // ── 3. Cargar TODOS los gastos del período (batch) ───────────────────
        $todasSalidas = Gasto::where('aliado_id', $aliadoId)
            ->whereIn('banco_origen_id', $bancoIds)
            ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
            ->whereBetween('fecha', [$inicio, $fin])
            ->with(['usuario', 'bancoDestino'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get()
            ->groupBy('banco_origen_id');

        // ── 4. Pre-calcular saldos (uno por banco) ────────────────────────────
        $saldosBanco = [];
        foreach ($bancoIds as $bid) {
            $saldosBanco[$bid] = Consignacion::saldoBanco($aliadoId, $bid);
        }

        // ── 5. Agrupar y transformar por banco ────────────────────────────────
        $consigPorBanco = $todasConsigRaw->groupBy('banco_cuenta_id');

        $saldos = $bancos->map(function ($bc) use ($consigPorBanco, $todasSalidas, $saldosBanco, $anticipos) {

            $movEntradas = ($consigPorBanco[$bc->id] ?? collect())->map(function ($c) use ($anticipos) {
                // nombre_cliente ya viene del CASE WHEN en SQL Server (igual que financiero)
                $pagador   = trim($c->nombre_cliente ?? '');
                $esEmpresa = ($c->empresa_id ?? 0) > 0;

                if ($pagador === '' || $pagador === '— — — —') $pagador = null;

                // Trazabilidad anticipo
                $anticFact = $anticFactNum = null;
                if (($c->tipo ?? '') === 'anticipo') {
                    $ant = $anticipos[$c->id] ?? null;
                    if ($ant) {
                        $anticFact    = $ant->factura_id;
                        $anticFactNum = $ant->factura?->numero_factura;
                    }
                }

                return (object)[
                    'id'                   => $c->id,
                    'cs_id'                => $c->id,
                    'fecha'                => $c->fecha,
                    'tipo'                 => $c->tipo ?? 'cliente',
                    'confirmado'           => (bool)$c->confirmado,
                    'factura_id'           => $c->factura_id,
                    'num_factura'          => $c->numero_factura ?? $c->factura_id,
                    'anticipo_factura_id'  => $anticFact,
                    'anticipo_factura_num' => $anticFactNum,
                    'pagador'              => $pagador ?: null,
                    'es_empresa'           => $esEmpresa,
                    'descripcion'          => match($c->tipo ?? 'cliente') {
                        'traslado_efectivo' => 'Traslado efectivo → banco',
                        'banco_recibido'    => 'Transferencia banco recibida',
                        'anticipo'          => 'Anticipo' . ($c->referencia ? ' · Ref: ' . $c->referencia : ''),
                        default             => $c->observacion,
                    },
                    'usuario'              => $c->usuario_nombre,
                    'valor'                => $c->valor,
                    'imagen_path'          => $c->imagen_path,
                    'imagen_url'           => $c->imagen_path ? Storage::url($c->imagen_path) : null,
                    'es_salida'            => false,
                    'es_gasto'             => false,
                    'referencia'           => $c->referencia,
                ];
            });

            $movSalidas = ($todasSalidas[$bc->id] ?? collect())->map(fn($g) => (object)[
                'id'          => $g->id,
                'fecha'       => $g->fecha,
                'tipo'        => $g->tipo,
                'confirmado'  => true,
                'factura_id'  => null,
                'num_factura' => null,
                'pagador'     => $g->pagado_a,
                'descripcion' => $g->descripcion . ($g->bancoDestino ? ' → ' . $g->bancoDestino->banco : ''),
                'usuario'     => $g->usuario,
                'valor'       => $g->valor,
                'imagen_path' => $g->imagen_path,
                'imagen_url'  => $g->imagen_path ? Storage::url($g->imagen_path) : null,
                'es_salida'   => true,
                'es_gasto'    => true,
                'referencia'  => null,
            ]);

            $movimientos = $movEntradas->merge($movSalidas)->sortByDesc('fecha')->values();

            return [
                'banco'       => $bc,
                'saldo'       => $saldosBanco[$bc->id] ?? 0,
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

        // Ingresos en efectivo del período (facturas normales — excluye préstamos)
        // Los préstamos tienen valor_efectivo=0 → ya quedan excluidos naturalmente,
        // pero filtramos explícitamente para mayor claridad.
        $ingresosEfectivo = (int) Factura::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->where('es_prestamo', false)
            ->whereNotNull('valor_efectivo')
            ->sum('valor_efectivo');

        // ── Cobros de cartera: efectivo de abonos a préstamos del período ──
        // La fecha del abono (cuando entró la plata) puede ser diferente al mes del servicio.
        $cobrosCartera = (int) DB::table('abonos')
            ->join('facturas', 'abonos.factura_id', '=', 'facturas.id')
            ->where('facturas.aliado_id', $aliadoId)
            ->where('facturas.es_prestamo', true)
            ->where('abonos.usuario_id', $usuarioId)
            ->whereBetween('abonos.fecha', [$inicio, $fin])
            ->sum('abonos.valor_efectivo');

        // ── Total prestado en el período (informativo, no es ingreso real) ──
        $totalPrestado = (int) Factura::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->where('es_prestamo', true)
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->sum('total');

        // Gastos en efectivo del período
        $gastosEfectivo = (int) Gasto::where('cuadre_id', $cuadre->id)
            ->where(fn($q) => $q->where('forma_pago', 'efectivo')
                ->orWhere('tipo', 'efectivo_banco'))
            ->sum('valor');

        // ── Anticipos en efectivo/Nequi del período (ingreso real del día) ───────────────────────
        // REGLA CLAVE:
        //   • Anticipo recibido en ABRIL  → aparece como ingreso en el cuadre de ABRIL.
        //   • Cuando se factura en MAYO   → anticipo_aplicado en la factura de mayo.
        //     El cuadre de MAYO NO suma ese dinero (ya fue contado en abril).
        //
        // Solo se suman formas de pago que no pasan por banco:
        //   efectivo          → entra al flujo de efectivo del cuadre.
        //   nequi             → idem (solo registros históricos; nueva UI solo usa efectivo/transferencia).
        //   transferencia     → ya se refleja en Consignacion (saldo banco),
        //                        NO se suma aquí para evitar doble conteo.
        $anticiposEfectivo = (int) Anticipo::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->whereIn('forma_pago', ['efectivo', 'nequi'])
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->whereNotIn('estado', [Anticipo::ESTADO_DEVUELTO])
            ->sum('valor');

        $saldoInicial = $cuadre->saldo_apertura;
        // Saldo = apertura + ingresos facturas + cartera + anticipos efectivo/nequi - gastos
        $saldoFinal   = $saldoInicial + $ingresosEfectivo + $cobrosCartera + $anticiposEfectivo - $gastosEfectivo;

        // Por día
        $dias = $cuadre->diasDelPeriodo();
        $saldoAcum = $saldoInicial;
        $porDia = $dias->map(function($dia) use ($cuadre, $aliadoId, $usuarioId, &$saldoAcum) {
            $fechaDia = $dia->toDateString();

            $ingDia = (int) Factura::where('aliado_id', $aliadoId)
                ->where('usuario_id', $usuarioId)
                ->whereDate('fecha_pago', $fechaDia)
                ->where('es_prestamo', false)
                ->sum('valor_efectivo');

            // Cobros de cartera del día
            $carteraDia = (int) DB::table('abonos')
                ->join('facturas', 'abonos.factura_id', '=', 'facturas.id')
                ->where('facturas.aliado_id', $aliadoId)
                ->where('facturas.es_prestamo', true)
                ->where('abonos.usuario_id', $usuarioId)
                ->whereDate('abonos.fecha', $fechaDia)
                ->sum('abonos.valor_efectivo');

            // Anticipos efectivo/Nequi del día
            $anticipoDia = (int) Anticipo::where('aliado_id', $aliadoId)
                ->where('usuario_id', $usuarioId)
                ->whereIn('forma_pago', ['efectivo', 'nequi'])
                ->whereDate('fecha_pago', $fechaDia)
                ->whereNotIn('estado', [Anticipo::ESTADO_DEVUELTO])
                ->sum('valor');

            $gastoDia = (int) Gasto::where('cuadre_id', $cuadre->id)
                ->whereDate('fecha', $fechaDia)
                ->where(fn($q) => $q->where('forma_pago', 'efectivo')
                    ->orWhere('tipo', 'efectivo_banco'))
                ->sum('valor');

            $saldoAcum += $ingDia + $carteraDia + $anticipoDia - $gastoDia;

            return [
                'fecha'        => $dia,
                'ingresos'     => $ingDia,
                'cartera'      => $carteraDia,
                'anticipos'    => $anticipoDia,
                'gastos'       => $gastoDia,
                'saldo'        => $saldoAcum,
            ];
        });

        return [
            'efectivo_total'    => $ingresosEfectivo,
            'cobros_cartera'    => $cobrosCartera,
            'total_prestado'    => $totalPrestado,
            'anticipos_efectivo'=> $anticiposEfectivo,  // ⇐ anticipos efectivo/nequi del período
            'gastos_efectivo'   => $gastosEfectivo,
            'saldo_inicial'     => $saldoInicial,
            'saldo_final'       => $saldoFinal,
            'por_dia'           => $porDia,
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
