<?php

namespace App\Http\Controllers\Finanzas\Concerns;

use Illuminate\Http\Request;

/**
 * Detección centralizada de dispositivos móviles por User-Agent
 * para todo el módulo de Finanzas Personales.
 */
trait DetectaDispositivoMovil
{
    protected function isMobileDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
    }
}
