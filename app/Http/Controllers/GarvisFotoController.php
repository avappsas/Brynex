<?php

namespace App\Http\Controllers;

use App\Jobs\GarvisFotoJob;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega a GARVIS una foto que Brayan le mandó por WhatsApp. Sin sesión, porque quien la
 * pide es un runner de GitHub Actions: lo que la protege es la firma del enlace (vence en
 * pocas horas) y un nombre de 32 caracteres al azar. Ver GarvisFotoJob.
 */
class GarvisFotoController extends Controller
{
    public function __invoke(string $archivo)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}\.[a-z0-9]{2,5}$/', $archivo), 404);

        $ruta = GarvisFotoJob::CARPETA.'/'.$archivo;
        abort_unless(Storage::disk('local')->exists($ruta), 404);

        return response()->file(Storage::disk('local')->path($ruta), [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
