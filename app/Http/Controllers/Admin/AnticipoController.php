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
            'forma_pago'     => 'required|in:efectivo,nequi,consignacion,transferencia',
            'banco_cuenta_id'=> 'nullable|integer|exists:banco_cuentas,id',
            'referencia'     => 'nullable|string|max:100',
            'observacion'    => 'nullable|string|max:300',
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

            // ── Si es consignación/transferencia: crear registro bancario ──
            // Así el saldo bancario refleja este dinero desde el día que llegó.
            if (in_array($validated['forma_pago'], ['consignacion', 'transferencia'])
                && !empty($validated['banco_cuenta_id']))
            {
                Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'banco_cuenta_id' => $validated['banco_cuenta_id'],
                    'factura_id'      => null,  // aún no hay factura
                    'anticipo_id'     => $anticipo->id,
                    'fecha'           => $validated['fecha_pago'],
                    'valor'           => $validated['valor'],
                    'tipo'            => 'anticipo',
                    'referencia'      => $validated['referencia'] ?? null,
                    'confirmado'      => false,
                    'observacion'     => $validated['observacion'] ?? 'Anticipo',
                    'usuario_id'      => Auth::id(),
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

        $anticipos = Anticipo::disponiblesParaEmpresa($aliadoId, $empresaId)
            ->map(fn($a) => [
                'id'               => $a->id,
                'fecha_pago'       => $a->fecha_pago->format('d/m/Y'),
                'forma_pago'       => $a->forma_pago,
                'forma_label'      => Anticipo::FORMAS_PAGO[$a->forma_pago] ?? $a->forma_pago,
                'referencia'       => $a->referencia,
                'valor'            => $a->valor,
                'valor_disponible' => $a->valor_disponible,
                'descripcion_pago' => $a->descripcion_pago,
            ]);

        return response()->json([
            'anticipos'       => $anticipos,
            'total_disponible'=> $anticipos->sum('valor_disponible'),
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

    // ── 5. Anular anticipo (solo si disponible y sin aplicar) ─────────
    public function destroy(int $id)
    {
        $aliadoId = session('aliado_id_activo');

        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $anticipo = Anticipo::where('aliado_id', $aliadoId)
            ->where('estado', Anticipo::ESTADO_DISPONIBLE)
            ->where('valor_aplicado', 0)
            ->findOrFail($id);

        // Si tenía consignación bancaria asociada, eliminarla también
        DB::table('consignaciones')
            ->where('anticipo_id', $anticipo->id)
            ->delete();

        $anticipo->delete();

        return response()->json([
            'ok'     => true,
            'mensaje'=> 'Anticipo eliminado.',
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

        $query = Anticipo::where('aliado_id', $aliadoId)
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->with(['contrato.cliente', 'empresa', 'factura', 'usuario'])
            ->orderBy('fecha_pago')
            ->orderBy('id');

        if ($estado) {
            $query->where('estado', $estado);
        }

        $anticipos = $query->get();

        $totales = [
            'recibido'  => $anticipos->sum('valor'),
            'aplicado'  => $anticipos->sum('valor_aplicado'),
            'disponible'=> $anticipos->sum('valor_disponible'),
            'devuelto'  => $anticipos->where('estado', Anticipo::ESTADO_DEVUELTO)->sum('valor'),
        ];

        return view('admin.anticipos.informe', compact(
            'anticipos', 'totales', 'desde', 'hasta', 'estado'
        ));
    }
}
