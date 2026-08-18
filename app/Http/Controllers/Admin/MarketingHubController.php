<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;

/**
 * Punto de entrada del módulo Marketing (solo accesible desde la tarjeta del /dashboard):
 * de aquí se reparte hacia Campañas WhatsApp y hacia Redes/Publicaciones.
 */
class MarketingHubController extends Controller
{
    public function index()
    {
        $aliadoId = session('aliado_id_activo');
        $pendientes = Publicacion::where('aliado_id', $aliadoId)->pendientes()->count();

        // Cuántos ex-clientes están esperando el mensaje de reactivación. Va en la tarjeta
        // para que el dueño vea que hay gente por contactar sin tener que entrar a buscarla.
        try {
            $porReactivar = \App\Services\Marketing\CandidatosReactivacion::elegibles($aliadoId)['elegibles']->count();
        } catch (\Throwable $e) {
            $porReactivar = null;
        }

        return view('admin.marketing.hub', compact('pendientes', 'porReactivar'));
    }
}
