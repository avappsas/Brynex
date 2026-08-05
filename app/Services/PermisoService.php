<?php

namespace App\Services;

use App\Models\Modulo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Metadatos del catálogo de permisos: qué permiso es restringido, a qué módulo
 * pertenece y cómo se llama en español.
 *
 * Se lee una sola vez por request (memo estático). Son ~107 filas; cachearlas
 * más agresivamente traería el problema clásico de "le di el permiso y no le
 * apareció hasta mañana".
 */
class PermisoService
{
    private static ?array $meta = null;

    /**
     * @return array<string, array{modulo:string, modulo_nombre:string, etiqueta:string, restringido:bool, solo_brynex:bool}>
     */
    public static function meta(): array
    {
        if (self::$meta !== null) {
            return self::$meta;
        }

        $filas = DB::table('permissions')
            ->leftJoin('modulos', 'modulos.id', '=', 'permissions.modulo_id')
            ->select(
                'permissions.name',
                'permissions.etiqueta',
                'permissions.restringido',
                'modulos.codigo as modulo',
                'modulos.nombre as modulo_nombre',
                'modulos.restringido as modulo_restringido',
                'modulos.solo_brynex'
            )
            ->get();

        self::$meta = [];
        foreach ($filas as $f) {
            self::$meta[$f->name] = [
                'modulo' => $f->modulo ?? '',
                'modulo_nombre' => $f->modulo_nombre ?? '',
                'etiqueta' => $f->etiqueta ?? $f->name,
                'restringido' => (bool) $f->restringido || (bool) $f->modulo_restringido,
                'solo_brynex' => (bool) $f->solo_brynex,
            ];
        }

        return self::$meta;
    }

    public static function esRestringido(string $permiso): bool
    {
        return self::meta()[$permiso]['restringido'] ?? false;
    }

    public static function esSoloBrynex(string $permiso): bool
    {
        return self::meta()[$permiso]['solo_brynex'] ?? false;
    }

    /** Texto legible para el mensaje de error: "Anular facturas (Facturación)" */
    public static function describir(string $permiso): string
    {
        $m = self::meta()[$permiso] ?? null;
        if (! $m) {
            return $permiso;
        }

        return $m['modulo_nombre']
            ? "{$m['etiqueta']} ({$m['modulo_nombre']})"
            : $m['etiqueta'];
    }

    /** Limpia el memo — para tests y para el comando de siembra. */
    public static function olvidar(): void
    {
        self::$meta = null;
    }

    /**
     * Módulos visibles para un usuario, agrupados, para pintar el sidebar y la
     * pantalla de permisos sin repetir la lógica en cada Blade.
     */
    public static function modulosVisibles(User $user): array
    {
        return Modulo::activos()->get()
            ->filter(fn (Modulo $m) => $m->solo_brynex ? $user->es_brynex : true)
            ->filter(fn (Modulo $m) => $user->can("{$m->codigo}.ver") || $user->can("{$m->codigo}.usar"))
            ->groupBy('grupo')
            ->all();
    }
}
