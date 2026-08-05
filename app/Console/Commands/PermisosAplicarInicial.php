<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Puesta en marcha del control de permisos por módulo.
 *
 * Hace las dos cosas que el seeder no puede hacer solo, porque dependen de
 * decisiones sobre personas concretas y no del catálogo:
 *
 *  1. **Rol a los usuarios que no tienen ninguno.** Antes daba igual: sin
 *     rutas protegidas, un usuario sin rol entraba a todo por URL. Ahora un
 *     usuario sin rol no entra a nada, así que hay que asignárselo antes de
 *     desplegar o se quedan afuera.
 *
 *  2. **Los permisos restringidos.** No los hereda ningún rol (esa es toda su
 *     gracia), así que al terminar la migración nadie puede ver una contraseña
 *     ni configurar Meta. Se le entregan al dueño, y él los reparte desde
 *     admin/usuarios/{id}/permisos.
 *
 * Corre en seco por defecto. Para aplicar de verdad: --ejecutar
 */
class PermisosAplicarInicial extends Command
{
    protected $signature = 'permisos:aplicar-inicial
                            {--ejecutar : Aplica los cambios (sin esta bandera solo muestra qué haría)}
                            {--duenio=2 : ID del usuario que recibe los permisos restringidos}';

    protected $description = 'Asigna rol a los usuarios que no tienen, y entrega los permisos restringidos al dueño.';

    public function handle(): int
    {
        $ejecutar = $this->option('ejecutar');
        $duenioId = (int) $this->option('duenio');

        if (! $ejecutar) {
            $this->warn('MODO SIMULACIÓN — no se escribe nada. Agrega --ejecutar para aplicar.');
            $this->newLine();
        }

        $this->paso1RolPorDefecto($ejecutar);
        $this->newLine();
        $this->paso2Restringidos($ejecutar, $duenioId);

        if ($ejecutar) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            PermisoService::olvidar();
            $this->newLine();
            $this->info('✅ Listo.');
        }

        return self::SUCCESS;
    }

    /**
     * Solo usuarios ACTIVOS y no borrados: a las cuentas apagadas no se les
     * regala un rol, porque si alguien las reactiva mañana entrarían con
     * permisos que nadie revisó.
     */
    private function paso1RolPorDefecto(bool $ejecutar): void
    {
        $this->line('── 1. Usuarios activos sin rol → rol `usuario` ──────────────');

        $sinRol = User::query()
            ->whereNotIn('id', DB::table('model_has_roles')->pluck('model_id'))
            ->where('activo', true)
            ->with('aliado')
            ->get();

        if ($sinRol->isEmpty()) {
            $this->line('   (ninguno)');

            return;
        }

        foreach ($sinRol as $u) {
            $this->line(sprintf('   #%-4s %-28s %s', $u->id, $u->nombre, $u->aliado->nombre ?? '—'));
            if ($ejecutar) {
                $u->assignRole('usuario');
            }
        }

        $this->line('   Total: '.$sinRol->count());
    }

    private function paso2Restringidos(bool $ejecutar, int $duenioId): void
    {
        $this->line('── 2. Permisos restringidos → usuario #'.$duenioId.' ─────────────');

        $duenio = User::find($duenioId);
        if (! $duenio) {
            $this->error("   No existe el usuario #{$duenioId}. Nada que hacer.");

            return;
        }

        $restringidos = Permission::where('restringido', true)
            ->orWhereIn('modulo_id', DB::table('modulos')->where('restringido', true)->pluck('id'))
            ->get();

        $this->line("   Destinatario: {$duenio->nombre} ({$duenio->cedula})");
        foreach ($restringidos as $p) {
            $ya = $duenio->hasDirectPermission($p->name) ? ' (ya lo tenía)' : '';
            $this->line("   · {$p->name} — {$p->etiqueta}{$ya}");
            if ($ejecutar) {
                $duenio->givePermissionTo($p);
            }
        }

        $this->line('   Total: '.$restringidos->count());
        $this->newLine();
        $this->comment('   Para repartirlos: admin/usuarios/{id}/permisos (solo superadmin).');
    }
}
