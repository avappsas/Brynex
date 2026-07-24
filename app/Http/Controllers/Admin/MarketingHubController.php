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
        $pendientes = Publicacion::where('aliado_id', session('aliado_id_activo'))->pendientes()->count();

        return view('admin.marketing.hub', compact('pendientes'));
    }
}
