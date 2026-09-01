<?php

namespace App\Services\ArlSura;

use App\Models\ArlUsuarioPortal;
use App\Models\ClaveAcceso;

/**
 * Mantiene una sola contraseña por usuario del portal de ARL Sura.
 *
 * La clave es de la persona, no de la empresa: con la misma cédula se entra al
 * portal y desde ahí se administran varias razones sociales. El llavero guarda
 * una fila por razón social, así que sin esto cambiarla en una dejaba las demás
 * con la vieja —y la siguiente que se usara fallaba el login sin motivo
 * aparente—. Fue exactamente lo que pasó con 1006168815: tres filas, dos con la
 * clave vieja.
 *
 * Se propaga sin mirar el aliado a propósito: la misma empresa está registrada
 * en varios, y la clave del portal es una sola para todos.
 */
class ClaveSuraSincronizador
{
    /**
     * Deja esa contraseña como la única del usuario, en el llavero y en lo que
     * usa la automatización.
     *
     * @return array{filas: int} cuántas filas del llavero quedaron al día
     */
    public static function propagar(string $usuario, string $contrasena, string $tipoDocumento = 'C'): array
    {
        $usuario = trim($usuario);

        if ($usuario === '' || $contrasena === '') {
            return ['filas' => 0];
        }

        $filas = ClaveAcceso::where('tipo', 'ARL')
            ->where('entidad', 'like', '%SURA%')
            ->where('usuario', $usuario)
            ->get();

        foreach ($filas as $fila) {
            if (trim((string) $fila->contrasena) !== $contrasena) {
                $fila->update(['contrasena' => $contrasena]);
            }
        }

        // La que lee la automatización para entrar sola al portal.
        ArlUsuarioPortal::registrar($tipoDocumento, $usuario, $contrasena);

        return ['filas' => $filas->count()];
    }

    /** ¿Esta entrada del llavero es una credencial del portal de ARL Sura? */
    public static function esDeSura(?string $tipo, ?string $entidad): bool
    {
        return strcasecmp((string) $tipo, 'ARL') === 0
            && stripos((string) $entidad, 'SURA') !== false;
    }
}
