<?php

namespace App\Services;

use App\Models\ArlUsuarioPortal;
use App\Models\ClaveAcceso;

/**
 * Mantiene una sola contraseña por usuario en cada portal.
 *
 * El llavero guarda una fila por razón social, pero la clave es del usuario que
 * entra al portal: la misma persona administra varias empresas con el mismo
 * login. Sin esto, cambiarla en una fila deja las demás con la vieja, y la
 * siguiente que se use falla sin motivo aparente. Pasó con el usuario
 * 1006168815 en ARL Sura: de tres filas, dos seguían con la clave anterior.
 *
 * Sura es un caso aparte: **el mismo usuario entra a ARL y a EPS**, y el NIT lo
 * pregunta después. Por eso sus dos tipos van al mismo grupo. En los demás
 * portales el grupo es por tipo + entidad, porque un mismo número de documento
 * en Coosalud y en Comfandi no tiene por qué compartir contraseña.
 *
 * Se propaga sin mirar el aliado a propósito: la misma empresa está registrada
 * en varios, y la clave del portal es una sola para todos.
 */
class ClavePortalSincronizador
{
    /** En Sura el login es uno solo para ARL y EPS. */
    public static function esSura(?string $entidad): bool
    {
        return stripos((string) $entidad, 'SURA') !== false;
    }

    /**
     * A qué grupo pertenece una entrada del llavero: con quién comparte clave.
     *
     * Devuelve null cuando no hay usuario, que es cuando no hay nada que
     * agrupar.
     */
    public static function grupoDe(?string $tipo, ?string $entidad, ?string $usuario): ?string
    {
        $usuario = trim((string) $usuario);

        if (! self::pareceUsuario($usuario)) {
            return null;
        }

        return self::esSura($entidad)
            ? 'SURA|'.$usuario
            : strtoupper(trim((string) $tipo)).'|'.strtoupper(trim((string) $entidad)).'|'.$usuario;
    }

    /**
     * ¿Esto es un usuario de verdad y no relleno del campo?
     *
     * El llavero tiene entradas donde alguien escribió «CC» o «CREADA» en vez
     * del usuario. Sin este filtro esas filas formarían un grupo falso —siete
     * empresas distintas bajo el mismo «usuario»— y guardar una le habría
     * pisado la contraseña a las otras seis.
     */
    public static function pareceUsuario(?string $usuario): bool
    {
        $usuario = trim((string) $usuario);

        if (mb_strlen($usuario) < 5) {
            return false;
        }

        // Un usuario real es un documento, un NIT o un correo: lleva dígitos o
        // arroba. «CREADA» no es ninguna de las dos cosas.
        return preg_match('/\d/', $usuario) === 1 || str_contains($usuario, '@');
    }

    /** Etiqueta legible del grupo, para la pantalla. */
    public static function nombreGrupo(?string $tipo, ?string $entidad): string
    {
        return self::esSura($entidad) ? 'SURA (ARL y EPS)' : trim((string) $entidad);
    }

    /**
     * Deja esa contraseña como la única del grupo al que pertenece la entrada.
     *
     * Solo se llama al guardar: es el momento en que alguien afirma cuál es la
     * clave buena. Nunca se propaga por su cuenta, porque fuera de ARL Sura no
     * hay forma de comprobar contra el portal cuál de dos claves distintas es
     * la vigente, y elegir mal borraría la buena.
     *
     * @return array{filas: int, grupo: ?string}
     */
    public static function propagar(ClaveAcceso $origen): array
    {
        $grupo = self::grupoDe($origen->tipo, $origen->entidad, $origen->usuario);
        $clave = trim((string) $origen->contrasena);

        if ($grupo === null || $clave === '') {
            return ['filas' => 0, 'grupo' => null];
        }

        $hermanas = ClaveAcceso::where('usuario', trim((string) $origen->usuario))
            ->get()
            ->filter(fn ($c) => self::grupoDe($c->tipo, $c->entidad, $c->usuario) === $grupo);

        foreach ($hermanas as $fila) {
            if ($fila->id !== $origen->id && trim((string) $fila->contrasena) !== $clave) {
                $fila->update(['contrasena' => $clave]);
            }
        }

        // En Sura, además, la copia que usa la afiliación automática para
        // entrar sola al portal.
        if (self::esSura($origen->entidad)) {
            ArlUsuarioPortal::registrar('C', trim((string) $origen->usuario), $clave);
        }

        return ['filas' => $hermanas->count(), 'grupo' => $grupo];
    }

    /**
     * Propaga la clave de un usuario de Sura, venga de donde venga.
     *
     * La usa el modal de Gestión ARL, que guarda la credencial sin pasar por
     * una fila del llavero.
     */
    public static function propagarSura(string $usuario, string $contrasena, string $tipoDocumento = 'C'): array
    {
        $usuario = trim($usuario);

        if ($usuario === '' || $contrasena === '') {
            return ['filas' => 0, 'grupo' => null];
        }

        $filas = ClaveAcceso::where('usuario', $usuario)
            ->where('entidad', 'like', '%SURA%')
            ->get();

        foreach ($filas as $fila) {
            if (trim((string) $fila->contrasena) !== $contrasena) {
                $fila->update(['contrasena' => $contrasena]);
            }
        }

        ArlUsuarioPortal::registrar($tipoDocumento, $usuario, $contrasena);

        return ['filas' => $filas->count(), 'grupo' => 'SURA|'.$usuario];
    }
}
