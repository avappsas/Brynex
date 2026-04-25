<?php

namespace App\Http\Controllers;

use App\Models\Aliado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BrynexController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Hub principal BryNex ─────────────────────────────────────────────
    public function hub()
    {
        $user = Auth::user();
        if (!$user->es_brynex) {
            abort(403);
        }

        $aliados      = Aliado::orderBy('nombre')->get();
        $totalAliados = $aliados->count();
        $activos      = $aliados->where('activo', true)->count();
        $usuariosBrynex = User::where('es_brynex', true)->count();

        return view('brynex.hub', compact('aliados', 'totalAliados', 'activos', 'usuariosBrynex'));
    }

    // ── Gestión de accesos: qué BryNex puede entrar a qué aliado ────────
    public function accesos()
    {
        $user = Auth::user();
        if (!$user->es_brynex || !$user->hasRole('superadmin')) {
            abort(403);
        }

        // Todos los usuarios BryNex (excepto el superadmin actual)
        $usuariosBrynex = User::where('es_brynex', true)
            ->where('id', '<>', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->with(['aliados' => fn($q) => $q->wherePivot('activo', true)])
            ->get();

        $aliados = Aliado::where('activo', true)->orderBy('nombre')->get();

        return view('brynex.accesos', compact('usuariosBrynex', 'aliados'));
    }

    // ── Toggle acceso usuario ↔ aliado ───────────────────────────────────
    public function toggleAcceso(Request $request)
    {
        $auth = Auth::user();
        if (!$auth->es_brynex || !$auth->hasRole('superadmin')) {
            abort(403);
        }

        $request->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'aliado_id' => 'required|integer|exists:aliados,id',
        ]);

        $userId   = (int) $request->user_id;
        $alidoId  = (int) $request->aliado_id;

        // No puede modificarse a sí mismo ni a otros superadmins
        $targetUser = User::findOrFail($userId);
        if (!$targetUser->es_brynex || $targetUser->hasRole('superadmin')) {
            return response()->json(['ok' => false, 'mensaje' => 'No se puede modificar este usuario.'], 422);
        }

        $existing = DB::table('aliado_user')
            ->where('user_id', $userId)
            ->where('aliado_id', $alidoId)
            ->first();

        if ($existing) {
            // Toggle activo
            $nuevoEstado = !$existing->activo;
            DB::table('aliado_user')
                ->where('user_id', $userId)
                ->where('aliado_id', $alidoId)
                ->update(['activo' => $nuevoEstado, 'updated_at' => now()]);
        } else {
            // Crear nuevo acceso
            DB::table('aliado_user')->insert([
                'user_id'    => $userId,
                'aliado_id'  => $alidoId,
                'rol'        => 'staff',
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nuevoEstado = true;
        }

        return response()->json([
            'ok'     => true,
            'activo' => $nuevoEstado,
            'mensaje' => $nuevoEstado ? 'Acceso habilitado.' : 'Acceso revocado.',
        ]);
    }
}
