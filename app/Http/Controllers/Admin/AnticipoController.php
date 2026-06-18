<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Anticipo, Contrato, Empresa, BancoCuenta, Consignacion, Factura};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class AnticipoController extends Controller
{
    // ── 1. Registrar anticipo ──────────────────────────────────────────
    public function store(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'contrato_id'    => 'nullable|integer|exists:contratos,id',
            'empresa_id'     => 'nullable|integer|exists:empresas,id',
            'fecha_pago'     => 'required|date',
            'valor'          => 'required|integer|min:1',
            'forma_pago'     => 'required|in:efectivo,transferencia',
            'banco_cuenta_id'=> 'nullable|integer|exists:banco_cuentas,id',
            'referencia'     => 'nullable|string|max:100',
            'observacion'    => 'nullable|string|max:300',
            'imagen'         => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:8192',
        ]);

        // Debe venir al menos un vínculo (contrato O empresa)
        if (empty($validated['contrato_id']) && empty($validated['empresa_id'])) {
            return response()->json([
                'ok'     => false,
                'mensaje'=> 'Debe asociar el anticipo a un contrato o una empresa.',
            ], 422);
        }

        // ── Validar referencia duplicada (prevención de doble registro) ──
        if (!empty($validated['referencia'])) {
            $dup = Anticipo::where('aliado_id', $aliadoId)
                ->where('referencia', $validated['referencia'])
                ->where('forma_pago', $validated['forma_pago'])
                ->whereNotIn('estado', [Anticipo::ESTADO_DEVUELTO])
                ->exists();

            if ($dup) {
                return response()->json([
                    'ok'     => false,
                    'alerta' => true,
                    'mensaje'=> "⚠️ Ya existe un anticipo con referencia {$validated['referencia']} ({$validated['forma_pago']}). ¿Estás seguro de que no es el mismo pago?",
                ], 409);
            }
        }

        // ── Obtener cédula del contrato (si aplica) ────────────────────
        $cedula = null;
        if (!empty($validated['contrato_id'])) {
            $contrato = Contrato::where('aliado_id', $aliadoId)
                ->findOrFail($validated['contrato_id']);
            $cedula = $contrato->cedula;
        }

        DB::beginTransaction();
        try {
            $anticipo = Anticipo::create([
                'aliado_id'      => $aliadoId,
                'cedula'         => $cedula,
                'contrato_id'    => $validated['contrato_id'] ?? null,
                'empresa_id'     => $validated['empresa_id']  ?? null,
                'fecha_pago'     => $validated['fecha_pago'],
                'valor'          => $validated['valor'],
                'valor_aplicado' => 0,
                'forma_pago'     => $validated['forma_pago'],
                'banco_cuenta_id'=> $validated['banco_cuenta_id'] ?? null,
                'referencia'     => $validated['referencia'] ?? null,
                'observacion'    => $validated['observacion'] ?? null,
                'estado'         => Anticipo::ESTADO_DISPONIBLE,
                'usuario_id'     => Auth::id(),
            ]);

            // ── Si es transferencia: crear registro bancario ──
            // Así el saldo bancario refleja este dinero desde el día que llegó.
            if ($validated['forma_pago'] === 'transferencia'
                && !empty($validated['banco_cuenta_id']))
            {
                // Guardar imagen si viene en el request
                $imagenPath = null;
                if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
                    $file = $request->file('imagen');
                    $imagenPath = $file->storeAs(
                        'anticipos',
                        'ant_' . $anticipo->id . '_' . time() . '.' . $file->getClientOriginalExtension(),
                        'public'
                    );
                }

                Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'banco_cuenta_id' => $validated['banco_cuenta_id'],
                    'factura_id'      => null,
                    'anticipo_id'     => $anticipo->id,
                    'fecha'           => $validated['fecha_pago'],
                    'valor'           => $validated['valor'],
                    'tipo'            => Consignacion::TIPO_ANTICIPO,
                    'referencia'      => $validated['referencia'] ?? null,
                    'confirmado'      => false,
                    'observacion'     => $validated['observacion'] ?? 'Anticipo',
                    'usuario_id'      => Auth::id(),
                    'imagen_path'     => $imagenPath,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok'     => false,
                'mensaje'=> 'Error al registrar el anticipo: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok'          => true,
            'mensaje'     => 'Anticipo registrado correctamente.',
            'anticipo_id' => $anticipo->id,
            'saldo_total' => Anticipo::saldoDisponible(
                $aliadoId,
                $anticipo->contrato_id,
                $anticipo->empresa_id
            ),
        ]);
    }

    // ── 2. API: Anticipos disponibles de un contrato ───────────────────
    public function porContrato(int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');

        $contrato = Contrato::where('aliado_id', $aliadoId)->findOrFail($contratoId);

        $anticipos = Anticipo::disponiblesParaContrato($aliadoId, $contratoId)
            ->map(fn($a) => [
                'id'               => $a->id,
                'fecha_pago'       => $a->fecha_pago->format('d/m/Y'),
                'forma_pago'       => $a->forma_pago,
                'forma_label'      => Anticipo::FORMAS_PAGO[$a->forma_pago] ?? $a->forma_pago,
                'referencia'       => $a->referencia,
                'valor'            => $a->valor,
                'valor_aplicado'   => $a->valor_aplicado,
                'valor_disponible' => $a->valor_disponible,
                'estado'           => $a->estado,
                'descripcion_pago' => $a->descripcion_pago,
            ]);

        return response()->json([
            'anticipos'       => $anticipos,
            'total_disponible'=> $anticipos->sum('valor_disponible'),
        ]);
    }

    // ── 3. API: Anticipos disponibles de una empresa ───────────────────
    public function porEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');

        $empresa = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        // 1. Anticipos de la empresa (colectivos, sin contrato asignado y que no estén en estado 'distribuido')
        $anticiposEmpresa = Anticipo::aliado($aliadoId)
            ->porEmpresa($empresaId)
            ->whereNull('contrato_id')
            ->where('estado', '!=', Anticipo::ESTADO_DISTRIBUIDO)
            ->conSaldo()
            ->orderBy('fecha_pago')
            ->get();

        // 2. Anticipos individuales de contratos asociados a clientes de la empresa
        $anticiposIndividuales = Anticipo::porContratoDeEmpresa($aliadoId, $empresaId)
            ->conSaldo()
            ->orderBy('fecha_pago')
            ->get();

        $mapAnticipo = fn($a) => [
            'id'               => $a->id,
            'fecha_pago'       => $a->fecha_pago->format('d/m/Y'),
            'forma_pago'       => $a->forma_pago,
            'forma_label'      => Anticipo::FORMAS_PAGO[$a->forma_pago] ?? $a->forma_pago,
            'referencia'       => $a->referencia,
            'valor'            => $a->valor,
            'valor_aplicado'   => $a->valor_aplicado,
            'valor_disponible' => $a->valor_disponible,
            'descripcion_pago' => $a->descripcion_pago,
            'contrato_id'      => $a->contrato_id,
            'cliente_nombre'   => $a->contrato?->cliente?->nombre_completo ?? '—',
        ];

        $empresarialesMapped = $anticiposEmpresa->map($mapAnticipo);
        $individualesMapped  = $anticiposIndividuales->map($mapAnticipo);

        return response()->json([
            'empresariales'    => $empresarialesMapped,
            'individuales'     => $individualesMapped,
            'total_disponible' => $empresarialesMapped->sum('valor_disponible') + $individualesMapped->sum('valor_disponible'),
        ]);
    }

    // ── 4. Marcar como devuelto ────────────────────────────────────────
    public function devolver(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $anticipo = Anticipo::where('aliado_id', $aliadoId)
            ->whereIn('estado', [Anticipo::ESTADO_DISPONIBLE, Anticipo::ESTADO_PARCIAL])
            ->findOrFail($id);

        $anticipo->update([
            'estado'      => Anticipo::ESTADO_DEVUELTO,
            'observacion' => ($anticipo->observacion ? $anticipo->observacion . ' | ' : '')
                           . 'Devuelto el ' . now()->format('d/m/Y')
                           . ($request->motivo ? ': ' . $request->motivo : ''),
        ]);

        return response()->json([
            'ok'     => true,
            'mensaje'=> 'Anticipo marcado como devuelto.',
        ]);
    }

    // ── 5. Anular anticipo (soft delete + observación obligatoria) ─────
    public function anular(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $request->validate([
            'motivo_anulacion' => 'required|string|min:5|max:500',
        ]);

        $anticipo = Anticipo::where('aliado_id', $aliadoId)
            ->where('estado', Anticipo::ESTADO_DISPONIBLE)
            ->where('valor_aplicado', 0)
            ->findOrFail($id);

        // Protección extra: no debe tener factura vinculada
        if ($anticipo->factura_id) {
            return response()->json([
                'ok'     => false,
                'mensaje'=> 'Este anticipo está vinculado a una factura y no puede anularse directamente. Anule primero la factura.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Guardar datos de anulación ANTES del soft delete
            $anticipo->motivo_anulacion = $request->motivo_anulacion;
            $anticipo->anulado_por      = Auth::id();
            $anticipo->save();

            // Soft delete (pone deleted_at = now())
            $anticipo->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok'     => false,
                'mensaje'=> 'Error al anular el anticipo: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok'     => true,
            'mensaje'=> 'Anticipo anulado correctamente.',
        ]);
    }

    // ── 5b. Eliminar físicamente (solo superadmin, legado) ────────────
    public function destroy(int $id)
    {
        $aliadoId = session('aliado_id_activo');

        if (!Auth::user()->hasRole(['superadmin'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso. Use Anular.'], 403);
        }

        $anticipo = Anticipo::withTrashed()
            ->where('aliado_id', $aliadoId)
            ->findOrFail($id);

        // Si tenía consignación bancaria asociada, eliminarla también
        DB::table('consignaciones')
            ->where('anticipo_id', $anticipo->id)
            ->delete();

        $anticipo->forceDelete();

        return response()->json([
            'ok'     => true,
            'mensaje'=> 'Anticipo eliminado físicamente.',
        ]);
    }

    // ── 6. Informe de anticipos ────────────────────────────────────────
    public function informe(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }

        $desde  = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta  = $request->input('hasta', now()->toDateString());
        $estado = $request->input('estado'); // null = todos

        // Si se filtra por 'anulado', usar onlyTrashed
        $incluyeAnulados = $estado === 'anulado';

        $query = Anticipo::where('aliado_id', $aliadoId)
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->with(['contrato.cliente', 'empresa', 'factura', 'usuario'])
            ->orderBy('fecha_pago')
            ->orderBy('id');

        if ($incluyeAnulados) {
            $query->onlyTrashed(); // solo los anulados (soft deleted)
        } elseif ($estado) {
            $query->whereNull('deleted_at')->where('estado', $estado);
        } else {
            $query->whereNull('deleted_at'); // por defecto: excluir anulados
        }

        $anticipos = $query->get();

        $totales = [
            'recibido'  => $anticipos->whereNull('deleted_at')->sum('valor'),
            'aplicado'  => $anticipos->whereNull('deleted_at')->sum('valor_aplicado'),
            'disponible'=> $anticipos->whereNull('deleted_at')->sum('valor_disponible'),
            'devuelto'  => $anticipos->where('estado', Anticipo::ESTADO_DEVUELTO)->whereNull('deleted_at')->sum('valor'),
            'anulado'   => $incluyeAnulados ? $anticipos->sum('valor') : 0,
        ];

        return view('admin.anticipos.informe', compact(
            'anticipos', 'totales', 'desde', 'hasta', 'estado'
        ));
    }

    // ── 7. Registrar anticipo distribuido desde empresa ────────────────
    public function storeDistribuido(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'empresa_id'               => 'required|integer|exists:empresas,id',
            'fecha_pago'               => 'required|date',
            'valor'                    => 'required|integer|min:1',
            'forma_pago'               => 'required|in:efectivo,transferencia',
            'banco_cuenta_id'          => 'nullable|integer|exists:banco_cuentas,id',
            'referencia'               => 'nullable|string|max:100',
            'observacion'              => 'nullable|string|max:300',
            'imagen'                   => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:8192',
            'distribucion'             => 'required|array|min:1',
            'distribucion.*.contrato_id'=> 'required|integer|exists:contratos,id',
            'distribucion.*.valor'     => 'required|integer|min:1',
            'distribucion.*.periodo_mes'=> 'nullable|integer|min:1|max:12',
            'distribucion.*.periodo_anio'=> 'nullable|integer|min:2020|max:2100',
        ]);

        // Validar que forma de pago transferencia venga con banco_cuenta_id
        if ($validated['forma_pago'] === 'transferencia' && empty($validated['banco_cuenta_id'])) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Debe seleccionar una cuenta bancaria para pagos por transferencia.'
            ], 422);
        }

        // Validar que la suma de la distribución sea igual al valor total
        $sumaDistribucion = collect($validated['distribucion'])->sum('valor');
        if ((int)$sumaDistribucion !== (int)$validated['valor']) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'La suma de los valores distribuidos (' . number_format($sumaDistribucion) . ') no coincide con el valor total del anticipo (' . number_format($validated['valor']) . ').'
            ], 422);
        }

        // Validar referencia duplicada si se provee
        if (!empty($validated['referencia'])) {
            $dup = Anticipo::where('aliado_id', $aliadoId)
                ->where('referencia', $validated['referencia'])
                ->where('forma_pago', $validated['forma_pago'])
                ->whereNotIn('estado', [Anticipo::ESTADO_DEVUELTO])
                ->exists();

            if ($dup) {
                return response()->json([
                    'ok'      => false,
                    'alerta'  => true,
                    'mensaje' => "⚠️ Ya existe un anticipo con referencia {$validated['referencia']} ({$validated['forma_pago']}). ¿Estás seguro de que no es el mismo pago?",
                ], 409);
            }
        }

        DB::beginTransaction();
        try {
            // Guardar imagen si viene
            $imagenPath = null;
            if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
                $file = $request->file('imagen');
                $imagenPath = $file->storeAs(
                    'anticipos',
                    'ant_dist_' . time() . '_' . rand(100,999) . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
            }

            // 1. Crear el anticipo maestro
            $maestro = Anticipo::create([
                'aliado_id'       => $aliadoId,
                'empresa_id'      => $validated['empresa_id'],
                'contrato_id'     => null,
                'cedula'          => null,
                'fecha_pago'      => $validated['fecha_pago'],
                'valor'           => $validated['valor'],
                'valor_aplicado'  => 0,
                'forma_pago'      => $validated['forma_pago'],
                'banco_cuenta_id' => $validated['banco_cuenta_id'] ?? null,
                'referencia'      => $validated['referencia'] ?? null,
                'observacion'     => $validated['observacion'] ?? null,
                'estado'          => Anticipo::ESTADO_DISTRIBUIDO, // 'distribuido'
                'origen'          => 'empresa',
                'usuario_id'      => Auth::id(),
            ]);

            // 2. Crear la consignación bancaria única vinculada al maestro si es transferencia
            if ($validated['forma_pago'] === 'transferencia' && !empty($validated['banco_cuenta_id'])) {
                Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'banco_cuenta_id' => $validated['banco_cuenta_id'],
                    'factura_id'      => null,
                    'anticipo_id'     => $maestro->id,
                    'fecha'           => $validated['fecha_pago'],
                    'valor'           => $validated['valor'],
                    'tipo'            => Consignacion::TIPO_ANTICIPO,
                    'referencia'      => $validated['referencia'] ?? null,
                    'confirmado'      => false,
                    'observacion'     => $validated['observacion'] ?? 'Anticipo empresa distribuido',
                    'usuario_id'      => Auth::id(),
                    'imagen_path'     => $imagenPath,
                ]);
            }

            // 3. Crear los anticipos hijos
            foreach ($validated['distribucion'] as $item) {
                $contrato = Contrato::where('aliado_id', $aliadoId)->findOrFail($item['contrato_id']);
                Anticipo::create([
                    'aliado_id'         => $aliadoId,
                    'empresa_id'        => $validated['empresa_id'],
                    'contrato_id'       => $contrato->id,
                    'cedula'            => $contrato->cedula,
                    'fecha_pago'        => $validated['fecha_pago'],
                    'valor'             => $item['valor'],
                    'valor_aplicado'    => 0,
                    'forma_pago'        => $validated['forma_pago'],
                    'banco_cuenta_id'   => $validated['banco_cuenta_id'] ?? null,
                    'referencia'        => $validated['referencia'] ?? null,
                    'observacion'       => $validated['observacion'] ?? null,
                    'estado'            => Anticipo::ESTADO_DISPONIBLE,
                    'origen'            => 'empresa',
                    'anticipo_padre_id' => $maestro->id,
                    'periodo_mes'       => $item['periodo_mes'] ?? null,
                    'periodo_anio'      => $item['periodo_anio'] ?? null,
                    'usuario_id'        => Auth::id(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al registrar el anticipo distribuido: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok'          => true,
            'mensaje'     => 'Anticipo distribuido registrado correctamente.',
            'anticipo_id' => $maestro->id,
        ]);
    }

    // ── 8. API: Listar contratos activos/vigentes de una empresa ──────────
    public function contratosEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');

        $empresa = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $cedulas = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', $empresaId)
            ->pluck('cedula');

        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulas)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente', 'plan'])
            ->get();

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $contratosMapped = $contratos->map(function ($c) use ($meses) {
            $periodo = Anticipo::periodoDestinoInfo($c->id);
            return [
                'id'              => $c->id,
                'cedula'          => $c->cedula,
                'cliente_nombre'  => $c->cliente?->nombre_completo ?? '—',
                'plan_nombre'     => $c->plan?->nombre ?? '—',
                'periodo_mes'     => $periodo['mes'],
                'periodo_anio'    => $periodo['anio'],
                'periodo_label'   => ($meses[$periodo['mes']] ?? '') . ' ' . $periodo['anio'],
            ];
        })->sortBy('cliente_nombre')->values();

        return response()->json([
            'ok'        => true,
            'contratos' => $contratosMapped,
        ]);
    }

    // ── 9. Ver recibo de anticipo ─────────────────────────────────────
    public function reciboAnticipo(int $id)
    {
        $aliadoId = session('aliado_id_activo');
        
        $anticipo = Anticipo::withTrashed() // mostrar recibo aunque esté anulado
            ->where('aliado_id', $aliadoId)
            ->with([
                'hijos.contrato.cliente', 
                'hijos.contrato.plan', 
                'contrato.cliente', 
                'contrato.plan', 
                'contrato.eps', 
                'contrato.pension', 
                'contrato.arl', 
                'contrato.caja', 
                'contrato.tipoModalidad', 
                'empresa', 
                'usuario', 
                'bancoCuenta'
            ])
            ->findOrFail($id);

        return view('admin.facturacion.recibo_anticipo', compact('anticipo'));
    }

    // ── 10. API: Anticipos del cliente (historial) ─────────────────────
    public function porCliente(string $cedula)
    {
        $aliadoId = session('aliado_id_activo');

        $anticipos = Anticipo::withTrashed()
            ->where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            // Excluir maestros distribuidos (solo los hijos/individuales del cliente)
            ->where(function ($q) {
                $q->where('estado', '!=', Anticipo::ESTADO_DISTRIBUIDO)
                  ->orWhereNotNull('deleted_at'); // si fue anulado, mostrarlo igual
            })
            ->with(['contrato.plan', 'empresa', 'factura', 'usuario'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'fecha_pago'       => $a->fecha_pago?->format('d/m/Y'),
                'forma_pago'       => $a->forma_pago,
                'forma_label'      => Anticipo::FORMAS_PAGO[$a->forma_pago] ?? ucfirst($a->forma_pago),
                'valor'            => $a->valor,
                'valor_aplicado'   => $a->valor_aplicado,
                'valor_disponible' => $a->trashed() ? 0 : $a->valor_disponible,
                'estado'           => $a->trashed() ? 'anulado' : $a->estado,
                'estado_label'     => $a->etiqueta_estado,
                'anulado'          => $a->trashed(),
                'motivo_anulacion' => $a->motivo_anulacion,
                'puede_anularse'   => $a->puedeAnularse(),
                'recibo_url'       => route('admin.anticipos.recibo', $a->id),
                'factura_numero'   => $a->factura?->numero_factura,
                'factura_mes'      => $a->factura?->mes,
                'factura_anio'     => $a->factura?->anio,
                'factura_recibo_url' => $a->factura_id ? route('admin.facturacion.recibo', $a->factura_id) : null,
                'empresa_nombre'   => $a->empresa?->empresa,
                'plan_nombre'      => $a->contrato?->plan?->nombre,
                'origen'           => $a->origen ?? 'individual',
            ]);

        return response()->json([
            'ok'       => true,
            'anticipos'=> $anticipos,
            'total'    => $anticipos->count(),
        ]);
    }
}
