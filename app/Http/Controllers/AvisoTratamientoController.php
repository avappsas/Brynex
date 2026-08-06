<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Aviso de tratamiento de datos personales (Ley 1581 de 2012).
 *
 * Se muestra una vez, en el primer ingreso, y no se puede seguir sin aceptarlo.
 * Es la contrapartida del registro de accesos: capturar IP, equipo y huella de
 * navegador de una persona identificada es tratamiento de datos personales, y
 * sin autorización previa e informada nos deja expuestos ante la SIC — y le
 * daría al abogado contrario un argumento para tumbar justo la prueba que
 * quisiéramos presentar.
 */
class AvisoTratamientoController extends Controller
{
    /**
     * Versión vigente del aviso. **Súbela cada vez que cambie el texto**: al no
     * coincidir con la que el usuario aceptó, se le vuelve a pedir. Formato
     * año-mes para que se vea de un vistazo de cuándo es la redacción vigente.
     */
    public const VERSION = '2026-08';

    /** Plazos prometidos. Deben coincidir con RetencionLimpiar. */
    public const ANIOS_ACCESOS = 2;

    public const ANIOS_BITACORA = 5;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function mostrar()
    {
        if (Auth::user()->acepto_tratamiento_version === self::VERSION) {
            return redirect()->route('dashboard');
        }

        return view('auth.aviso-tratamiento', [
            'version' => self::VERSION,
            'aniosAccesos' => self::ANIOS_ACCESOS,
            'aniosBitacora' => self::ANIOS_BITACORA,
        ]);
    }

    public function aceptar(Request $request)
    {
        $request->validate(
            ['acepto' => 'accepted'],
            ['acepto.accepted' => 'Debes marcar la casilla para continuar.']
        );

        $user = Auth::user();

        // forceFill: son campos que escribe el sistema, no un formulario de
        // edición de perfil. Mantenerlos fuera de $fillable evita que un
        // update masivo en otro controlador los pise sin querer.
        $user->forceFill([
            'acepto_tratamiento_at' => now(),
            'acepto_tratamiento_ip' => $request->ip(),
            'acepto_tratamiento_version' => self::VERSION,
        ])->saveQuietly();

        Bitacora::registrar(
            'aviso_tratamiento_aceptado',
            'User',
            $user->id,
            "{$user->nombre} aceptó el aviso de tratamiento de datos (versión ".self::VERSION.').',
            ['version' => self::VERSION],
            (int) $user->aliado_id
        );

        return redirect()->intended(route('dashboard'));
    }
}
