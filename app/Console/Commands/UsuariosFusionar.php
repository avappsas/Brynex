<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fusiona dos cuentas de la misma persona en una sola.
 *
 * Pasa cuando alguien se crea dos veces —normalmente por un dígito de más en
 * la cédula, o porque la migración del legacy trajo su usuario aparte— y
 * termina con la mitad de su trabajo colgando de cada cuenta. Borrar la
 * duplicada a secas dejaría facturas, gastos y contratos apuntando a un
 * usuario que ya no existe.
 *
 * Lo que hace: repunta TODAS las referencias del usuario origen al destino,
 * le pasa los roles y permisos que el destino no tuviera, y deja el origen
 * marcado y borrado lógicamente. La fila del origen no se elimina nunca.
 *
 * Antes de escribir guarda en `storage/app/fusiones/` el id de cada fila
 * tocada, para poder deshacer la fusión si aparece algo que no se previó.
 *
 *   php artisan usuarios:fusionar 9 3              # simulación
 *   php artisan usuarios:fusionar 9 3 --ejecutar   # de verdad
 */
class UsuariosFusionar extends Command
{
    protected $signature = 'usuarios:fusionar
                            {origen : ID del usuario duplicado, el que desaparece}
                            {destino : ID del usuario que se queda}
                            {--ejecutar : Aplica los cambios (sin esta bandera solo muestra qué haría)}
                            {--forzar-aliado : Permite fusionar usuarios de aliados distintos}';

    protected $description = 'Fusiona un usuario duplicado en otro, repuntando todas sus referencias.';

    /**
     * Dónde se guardan ids de usuario. Se descubre solo con el esquema en vez
     * de mantener una lista a mano, porque la lista se queda vieja: cada tabla
     * nueva con un `usuario_id` sería una referencia huérfana silenciosa.
     *
     * Las tablas `*_bkp_*` quedan fuera a propósito: son copias de seguridad
     * congeladas, y reescribirlas destruiría justo lo que se guardó.
     */
    private function referencias(): array
    {
        $filas = DB::select("
            SELECT TABLE_NAME tabla, COLUMN_NAME columna
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE (COLUMN_NAME LIKE '%user_id%'
                OR COLUMN_NAME LIKE '%usuario_id%'
                OR COLUMN_NAME LIKE '%encargado%'
                OR COLUMN_NAME LIKE '%asignado%'
                OR COLUMN_NAME LIKE '%creado_por%'
                OR COLUMN_NAME LIKE '%aprobado_por%'
                OR COLUMN_NAME LIKE '%registrado_por%'
                OR COLUMN_NAME LIKE '%atendido%')
              AND TABLE_NAME NOT LIKE '%_bkp_%'
              AND TABLE_NAME NOT IN ('users', 'sessions', 'model_has_roles',
                                     'model_has_permissions', 'aliado_user')
              AND DATA_TYPE IN ('int', 'bigint', 'smallint')
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");

        return array_map(fn ($f) => [$f->tabla, $f->columna], $filas);
    }

    public function handle(): int
    {
        $origenId = (int) $this->argument('origen');
        $destinoId = (int) $this->argument('destino');
        $ejecutar = $this->option('ejecutar');

        if ($origenId === $destinoId) {
            $this->error('El origen y el destino son el mismo usuario.');

            return self::FAILURE;
        }

        $origen = User::withTrashed()->find($origenId);
        $destino = User::withTrashed()->find($destinoId);

        if (! $origen || ! $destino) {
            $this->error('No existe alguno de los dos usuarios.');

            return self::FAILURE;
        }

        if ($origen->aliado_id !== $destino->aliado_id && ! $this->option('forzar-aliado')) {
            $this->error("Son de aliados distintos ({$origen->aliado_id} vs {$destino->aliado_id}).");
            $this->line('En un sistema multi-aliado eso normalmente NO es un duplicado: la misma');
            $this->line('persona puede tener una cuenta por cada aliado en el que trabaja.');
            $this->line('Si de verdad quieres fusionarlos, agrega --forzar-aliado.');

            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('MODO SIMULACIÓN — no se escribe nada. Agrega --ejecutar para aplicar.');
        }
        $this->newLine();
        $this->line("Origen  #{$origen->id}  {$origen->nombre}  ced {$origen->cedula}  <{$origen->email}>");
        $this->line("Destino #{$destino->id}  {$destino->nombre}  ced {$destino->cedula}  <{$destino->email}>");
        $this->newLine();

        // ── Inventario de lo que hay que mover ─────────────────────────────
        $aMover = [];
        $total = 0;

        foreach ($this->referencias() as [$tabla, $columna]) {
            try {
                $ids = DB::table($tabla)->where($columna, $origenId)->pluck('id')->all();
            } catch (\Throwable $e) {
                // Tabla sin columna `id` (pivotes, tablas legacy): se mueve
                // igual, pero sin poder registrar los ids para el deshacer.
                try {
                    $n = DB::table($tabla)->where($columna, $origenId)->count();
                } catch (\Throwable) {
                    continue;
                }
                if ($n > 0) {
                    $aMover[] = [$tabla, $columna, null, $n];
                    $total += $n;
                    $this->line(sprintf('   %-46s %5d  (sin id: no se podrá deshacer)', "$tabla.$columna", $n));
                }

                continue;
            }

            if ($ids) {
                $aMover[] = [$tabla, $columna, $ids, count($ids)];
                $total += count($ids);
                $this->line(sprintf('   %-46s %5d', "$tabla.$columna", count($ids)));
            }
        }

        $this->newLine();
        $this->info("Total de filas a repuntar: {$total}");

        $rolesOrigen = $origen->getRoleNames()->all();
        $permisosOrigen = $origen->permissions->pluck('name')->all();
        $rolesFaltantes = array_values(array_diff($rolesOrigen, $destino->getRoleNames()->all()));
        $permisosFaltantes = array_values(array_diff($permisosOrigen, $destino->permissions->pluck('name')->all()));

        $this->line('Roles del origen: '.(implode(', ', $rolesOrigen) ?: 'ninguno')
            .' → se le añaden al destino: '.(implode(', ', $rolesFaltantes) ?: 'ninguno'));
        $this->line('Permisos directos del origen: '.(implode(', ', $permisosOrigen) ?: 'ninguno')
            .' → se le añaden al destino: '.(implode(', ', $permisosFaltantes) ?: 'ninguno'));

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada de esto se ha escrito. Repite con --ejecutar.');

            return self::SUCCESS;
        }

        // ── Registro para deshacer, ANTES de tocar nada ────────────────────
        $sello = now()->format('Ymd_His');
        $ruta = "fusiones/usuarios-{$origenId}-a-{$destinoId}-{$sello}.json";
        Storage::disk('local')->put($ruta, json_encode([
            'origen' => $origenId,
            'destino' => $destinoId,
            'fecha' => now()->toDateTimeString(),
            'ejecutado_por' => auth()->id(),
            'nombre_original' => $origen->nombre,
            'roles_anadidos' => $rolesFaltantes,
            'permisos_anadidos' => $permisosFaltantes,
            'filas' => array_map(fn ($m) => [
                'tabla' => $m[0], 'columna' => $m[1], 'ids' => $m[2], 'cantidad' => $m[3],
            ], $aMover),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->line("Registro para deshacer: storage/app/{$ruta}");

        DB::transaction(function () use ($aMover, $origen, $destino, $origenId, $destinoId, $rolesFaltantes, $permisosFaltantes, $total) {
            foreach ($aMover as [$tabla, $columna, $ids, $n]) {
                DB::table($tabla)->where($columna, $origenId)->update([$columna => $destinoId]);
                $this->line(sprintf('   ✓ %-46s %5d', "$tabla.$columna", $n));
            }

            foreach ($rolesFaltantes as $rol) {
                $destino->assignRole($rol);
            }
            foreach ($permisosFaltantes as $permiso) {
                $destino->givePermissionTo($permiso);
            }

            // El origen se queda en la tabla, marcado y borrado lógicamente:
            // así el listado no lo muestra dos veces pero la fila sigue ahí si
            // hubiera que rastrear algo.
            DB::table('model_has_roles')->where('model_id', $origenId)
                ->where('model_type', User::class)->delete();
            DB::table('model_has_permissions')->where('model_id', $origenId)
                ->where('model_type', User::class)->delete();
            DB::table('sessions')->where('user_id', $origenId)->delete();

            $origen->forceFill([
                'nombre' => "[fusionado en #{$destinoId}] {$origen->nombre}",
                'activo' => false,
            ])->save();
            $origen->delete();

            Bitacora::registrar(
                'usuarios_fusionados',
                'User',
                $destinoId,
                "Usuario #{$origenId} fusionado en #{$destinoId}: {$total} filas repuntadas",
                ['origen' => $origenId, 'destino' => $destinoId, 'filas' => $total]
            );
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->newLine();
        $this->info("✅ Fusión hecha. {$total} filas ahora apuntan a #{$destinoId}.");

        return self::SUCCESS;
    }
}
