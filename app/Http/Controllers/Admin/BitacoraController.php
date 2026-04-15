<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $alidoId = session('aliado_id_activo');

        $query = Bitacora::with('user:id,nombre')
            ->where('aliado_id', $alidoId)
            ->orderByDesc('created_at');

        if ($request->filled('modelo')) {
            $query->where('modelo', $request->modelo);
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->filled('usuario')) {
            $query->where('user_id', $request->usuario);
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $registros = $query->paginate(50)->withQueryString();

        return view('admin.bitacora.index', compact('registros'));
    }
}
