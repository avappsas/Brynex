<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
use App\Models\WhatsappConfig;
use Illuminate\Http\RedirectResponse;

/**
 * Redirige un link corto (/wa/{publicacion}) al wa.me real con el mensaje precargado y el
 * código de referencia ("ref: P{id}") — así el comentario en Facebook/Instagram muestra una
 * URL corta y legible en vez del wa.me largo con el texto codificado, sin perder ni el mensaje
 * precargado ni la atribución (ver Publicacion::mensajeWhatsappRastreado).
 */
class WhatsappRedirectController extends Controller
{
    public function redirigir(Publicacion $publicacion): RedirectResponse
    {
        $waConfig = WhatsappConfig::where('aliado_id', $publicacion->aliado_id)->where('activo', true)->first();
        $numero = preg_replace('/\D/', '', $waConfig->numero_telefono ?? '');
        abort_if(!$numero, 404);

        if (!str_starts_with($numero, '57')) {
            $numero = '57' . $numero;
        }

        return redirect()->away('https://wa.me/' . $numero . '?text=' . rawurlencode($publicacion->mensajeWhatsappRastreado()));
    }
}
