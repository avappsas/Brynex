<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Niega a un usuario un permiso que su rol sí trae, o se lo devuelve.
 *
 * Nació para un superadmin de BryNex que lo ve todo menos el estado
 * financiero de los aliados: el rol no se puede recortar (el Gate::before le
 * da todo lo no restringido), así que la negación va por usuario en
 * `users.permisos_negados` y gana sobre cualquier otra regla.
 *
 *   php artisan permisos:negar 45 informes.financiero informes.financiero_editar
 *   php artisan permisos:negar 45 informes.financiero --devolver
 *   php artisan permisos:negar 45            ← solo muestra lo negado
 *
 * Corre en seco por defecto. Para aplicar de verdad: --ejecutar
 */
class PermisosNegar extends Command
{
    protected $signature = 'permisos:negar
                            {usuario : ID del usuario}
                            {permisos?* : Nombres de permiso, ej. informes.financiero}
                            {--devolver : Quita la negación en vez de ponerla}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo muestra qué haría)}
                            {--autor=2 : ID de quien queda como autor en la bitácora}';

    protected $description = 'Niega a un usuario permisos que su rol trae (aunque sea superadmin), o se los devuelve.';

    public function handle(): int
    {
        $usuario = User::find((int) $this->argument('usuario'));

        if (! $usuario) {
            $this->error('No existe ese usuario (o está borrado).');

            return self::FAILURE;
        }

        $antes = $usuario->permisos_negados ?? [];
        $this->line("{$usuario->nombre} (id {$usuario->id}) — negados hoy: ".($antes ? implode(', ', $antes) : '(ninguno)'));

        $permisos = $this->argument('permisos');
        if (! $permisos) {
            return self::SUCCESS;
        }

        $desconocidos = array_diff($permisos, array_keys(PermisoService::meta()));
        if ($desconocidos) {
            $this->error('Permisos que no están en el catálogo: '.implode(', ', $desconocidos));

            return self::FAILURE;
        }

        $despues = $this->option('devolver')
            ? array_values(array_diff($antes, $permisos))
            : array_values(array_unique(array_merge($antes, $permisos)));

        $this->line('Quedaría en: '.($despues ? implode(', ', $despues) : '(ninguno)'));

        if (! $this->option('ejecutar')) {
            $this->warn('MODO SIMULACIÓN — no se escribió nada. Agrega --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        // Sin sesión en consola, la bitácora quedaría sin autor.
        Auth::onceUsingId((int) $this->option('autor'));

        $usuario->permisos_negados = $despues ?: null;
        $usuario->save();

        Bitacora::registrar(
            'permisos_negados',
            'User',
            $usuario->id,
            "Permisos negados de {$usuario->nombre}: ".($despues ? implode(', ', $despues) : 'ninguno'),
            ['antes' => $antes, 'despues' => $despues],
            (int) $usuario->aliado_id
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info('✅ Listo.');

        return self::SUCCESS;
    }
}
