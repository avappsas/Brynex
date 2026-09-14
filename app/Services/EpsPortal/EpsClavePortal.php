<?php

namespace App\Services\EpsPortal;

use App\Models\EpsPortalEmpresa;
use Illuminate\Support\Facades\DB;

/**
 * La clave del portal de una EPS para una empresa, sacada del módulo de claves.
 *
 * Igual que en ARL Sura y Nueva EPS, la fuente es `clave_accesos` por el NIT de
 * la razón social: cambiarla allí basta. `eps_portal_empresas` recuerda la
 * huella de una clave que el portal rechazó, para no volver a probarla hasta
 * que alguien la cambie — los portales bloquean al usuario tras varios intentos.
 */
class EpsClavePortal
{
    /**
     * @param  string  $patronEntidad  LIKE contra clave_accesos.entidad, p. ej. '%SALUD%TOTAL%'
     * @return array{usuario:string, contrasena:string, empresa:EpsPortalEmpresa}|array{error:string}
     */
    public static function para(string $entidad, string $patronEntidad, string $nombre, string $nit): array
    {
        $nit     = preg_replace('/\D/', '', $nit);
        $empresa = EpsPortalEmpresa::de($entidad, $nit);

        if ($portal = $empresa->usuarioPortal) {
            $usuario = trim($portal->tipo_documento.' '.$portal->usuario);
            $clave   = (string) $portal->contrasena;
        } else {
            $fila = DB::table('clave_accesos as c')
                ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
                ->where('rs.nit', $nit)
                ->where('c.tipo', 'EPS')
                ->where('c.entidad', 'like', $patronEntidad)
                ->where('c.activo', true)
                ->whereNotNull('c.usuario')->where('c.usuario', '<>', '')
                ->whereNotNull('c.contrasena')->where('c.contrasena', '<>', '')
                ->orderByDesc('c.updated_at')
                ->first(['c.usuario', 'c.contrasena']);

            if (! $fila) {
                return ['error' => "La empresa no tiene la clave de {$nombre} en el módulo de claves."];
            }

            $usuario = trim($fila->usuario);
            $clave   = (string) $fila->contrasena;
        }

        if ($empresa->clave_fallida_hash && hash_equals($empresa->clave_fallida_hash, self::huella($usuario, $clave))) {
            return ['error' => "{$nombre} ya rechazó esta clave (".($empresa->ultimo_error ?: 'sin detalle')
                .'). Actualízala en el módulo de claves para volver a intentar.'];
        }

        return ['usuario' => $usuario, 'contrasena' => $clave, 'empresa' => $empresa];
    }

    public static function exito(EpsPortalEmpresa $empresa): void
    {
        $empresa->update(['ultima_sesion_at' => now(), 'ultimo_error' => null, 'clave_fallida_hash' => null]);
    }

    public static function rechazada(EpsPortalEmpresa $empresa, string $usuario, string $clave, string $error): void
    {
        $empresa->update([
            'clave_fallida_hash' => self::huella($usuario, $clave),
            'ultimo_error'       => mb_substr($error, 0, 300),
        ]);
    }

    /**
     * "CC 1005878149" → ['CC', '1005878149']; un número suelto es cédula.
     *
     * @return array{0:string, 1:string}
     */
    public static function separarUsuario(string $usuario): array
    {
        return preg_match('/^([A-Za-z]{1,3})\s+(\d+)$/', trim($usuario), $m)
            ? [strtoupper($m[1]), $m[2]]
            : ['CC', preg_replace('/\D/', '', $usuario)];
    }

    private static function huella(string $usuario, string $clave): string
    {
        return hash('sha256', $usuario.'|'.$clave);
    }
}
