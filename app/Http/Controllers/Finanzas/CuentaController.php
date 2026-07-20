<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finanzas\Concerns\InvalidaFinanzasCache;
use App\Models\Finanzas\Cuenta;
use App\Models\Finanzas\CuentaTransferencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CuentaController extends Controller
{
    use InvalidaFinanzasCache;
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Lista las cuentas con su saldo actual y las últimas transferencias.
     */
    public function index()
    {
        $userId = Auth::id();

        $cuentas = Cuenta::conSaldos($userId);
        $inactivas = Cuenta::where('user_id', $userId)->where('activo', false)->orderBy('orden')->get();
        $saldoTotal = $cuentas->sum('saldo_actual');

        $transferencias = CuentaTransferencia::with(['origen', 'destino'])
            ->where('user_id', $userId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return view('finanzas.cuentas.index', compact('cuentas', 'inactivas', 'saldoTotal', 'transferencias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'tipo' => 'required|string|in:banco,efectivo,billetera,otro',
            'icono' => 'nullable|string|max:10',
            'saldo_inicial' => 'nullable|numeric',
            'orden' => 'nullable|integer',
        ]);

        Cuenta::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
            'icono' => $request->icono ?: '💳',
            'color' => $request->color ?: '#64748b',
            'saldo_inicial' => $request->saldo_inicial ?: 0,
            'activo' => true,
            'orden' => $request->orden ?: 10,
        ]);

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuentas.index')->with('success', 'Cuenta creada con éxito.');
    }

    public function update(Request $request, $id)
    {
        $cuenta = Cuenta::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:50',
            'tipo' => 'required|string|in:banco,efectivo,billetera,otro',
            'icono' => 'nullable|string|max:10',
            'saldo_inicial' => 'nullable|numeric',
            'orden' => 'nullable|integer',
            'activo' => 'required|boolean',
        ]);

        $cuenta->update([
            'nombre'        => $request->nombre,
            'tipo'          => $request->tipo,
            'icono'         => $request->icono ?: $cuenta->icono,
            'saldo_inicial' => $request->saldo_inicial ?? $cuenta->saldo_inicial,
            'orden'         => $request->orden ?? $cuenta->orden,
            'activo'        => (bool) $request->activo,
        ]);

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuentas.index')->with('success', 'Cuenta actualizada.');
    }

    /**
     * Desactiva la cuenta (no se borra para conservar el histórico).
     */
    public function destroy($id)
    {
        $cuenta = Cuenta::where('user_id', Auth::id())->findOrFail($id);
        $cuenta->update(['activo' => false]);
        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuentas.index')->with('success', 'Cuenta desactivada.');
    }

    /**
     * Registra una transferencia entre dos cuentas del usuario.
     */
    public function transferir(Request $request)
    {
        $request->validate([
            'cuenta_origen_id' => 'required|integer|different:cuenta_destino_id',
            'cuenta_destino_id' => 'required|integer',
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
        ]);

        $userId = Auth::id();

        // Validar que ambas cuentas pertenezcan al usuario
        Cuenta::where('user_id', $userId)->findOrFail($request->cuenta_origen_id);
        Cuenta::where('user_id', $userId)->findOrFail($request->cuenta_destino_id);

        CuentaTransferencia::create([
            'user_id'          => $userId,
            'cuenta_origen_id' => $request->cuenta_origen_id,
            'cuenta_destino_id'=> $request->cuenta_destino_id,
            'fecha'            => $request->fecha,
            'monto'            => $request->monto,
            'observacion'      => $request->observacion,
        ]);

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuentas.index')->with('success', 'Transferencia registrada.');
    }

    /**
     * Elimina una transferencia registrada por error.
     */
    public function eliminarTransferencia($id)
    {
        $transferencia = CuentaTransferencia::where('user_id', Auth::id())->findOrFail($id);
        $transferencia->delete();
        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuentas.index')->with('success', 'Transferencia eliminada.');
    }
}
