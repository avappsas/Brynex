<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comprueba que el login configurado en una conexión alcanza para todo lo que la
 * aplicación hace de verdad.
 *
 * Existe para poder salir de `sa` sin adivinar: se apunta la conexión al login
 * nuevo en un entorno de prueba (o se corre con --usuario/--password sin tocar
 * el .env) y este comando dice si falta algún permiso ANTES del corte. Un login
 * al que le falta un permiso no falla al conectar: falla semanas después, en la
 * migración o el informe que nadie había vuelto a correr.
 *
 * Ver docs/login-app-sin-sa.md para el procedimiento completo.
 */
class VerificarPermisosBd extends Command
{
    protected $signature = 'db:verificar-permisos
        {--conexion=sqlsrv : Conexión de config/database.php a revisar}
        {--usuario= : Probar con otro login en vez del configurado}
        {--password= : Contraseña de ese login. Mejor no usarla: queda en `ps` y en el historial. Si se omite se toma de DB_VERIFY_PASSWORD, y si tampoco está se pide por consola}
        {--ddl : Además de los permisos declarados, probar DDL de verdad (crea y borra una tabla _zz_)}';

    protected $description = 'Verifica que el login de una conexión alcanza para lo que hace la app (para migrar fuera de sa)';

    /** Nombre inequívoco y efímero: si queda, se ve que es basura de una prueba. */
    private const TABLA_PRUEBA = '_zz_verificacion_permisos';

    public function handle(): int
    {
        $conexion = $this->option('conexion');

        if ($this->option('usuario')) {
            // Orden a propósito: la variable de entorno antes que el prompt, para
            // que esto se pueda automatizar sin que la contraseña acabe en la
            // línea de comandos (donde cualquier usuario local la ve en `ps`).
            $password = $this->option('password')
                ?: (getenv('DB_VERIFY_PASSWORD') ?: null)
                ?: $this->secret('Contraseña de '.$this->option('usuario'));
            config([
                "database.connections.$conexion.username" => $this->option('usuario'),
                "database.connections.$conexion.password" => $password,
            ]);
            DB::purge($conexion);
        }

        $db = DB::connection($conexion);

        try {
            $quien = $db->selectOne('SELECT SUSER_SNAME() AS login, DB_NAME() AS bd, IS_SRVROLEMEMBER(?) AS sysadmin', ['sysadmin']);
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  conexión: <options=bold>$conexion</>");
        $this->line("  login:    <options=bold>{$quien->login}</>");
        $this->line("  base:     <options=bold>{$quien->bd}</>");
        $this->line('  sysadmin: '.($quien->sysadmin
            ? '<fg=yellow>SÍ — es el privilegio que se quiere dejar de usar</>'
            : '<fg=green>no</>'));
        $this->newLine();

        $fallos = 0;
        $fallos += $this->revisarRoles($db);
        $fallos += $this->revisarOperaciones($db);

        if ($this->option('ddl')) {
            $fallos += $this->probarDdlReal($db);
        } else {
            $this->newLine();
            $this->line('  <fg=gray>Sin --ddl no se probó DDL de verdad, solo los permisos declarados.</>');
        }

        $this->newLine();

        if ($fallos > 0) {
            $this->error("  Faltan $fallos permisos. NO cambies el .env todavía.");

            return self::FAILURE;
        }

        $this->info('  Todo en orden: este login alcanza para lo que hace la app.');

        return self::SUCCESS;
    }

    /** db_owner es lo que necesita la app: migraciones, DDL en runtime y BACKUP. */
    private function revisarRoles($db): int
    {
        $this->line('  <options=bold>Roles en la base</>');
        $esOwner = (bool) $db->selectOne('SELECT IS_ROLEMEMBER(?) AS r', ['db_owner'])->r;

        $this->resultado('db_owner', $esOwner, 'lo exigen las migraciones y el DDL en runtime');

        return $esOwner ? 0 : 1;
    }

    /**
     * Permisos concretos que el código ejercita. Se consultan con
     * HAS_PERMS_BY_NAME, que responde por el usuario actual sin ejecutar nada.
     *
     * Los cuatro son permisos de BASE DE DATOS, así que el securable es
     * DB_NAME() con clase 'DATABASE'. Con (NULL, NULL) se preguntaría por el
     * SERVIDOR, donde ninguno de estos existe: devuelve 0 incluso para sa y el
     * comando reporta cuatro "FALTA" que no son ciertos.
     */
    private function revisarOperaciones($db): int
    {
        $this->newLine();
        $this->line('  <options=bold>Permisos que el código usa</>');

        $checks = [
            // BrynexBackupController::crearBackupManual()
            ['BACKUP DATABASE', 'copia manual desde /admin (BrynexBackupController)'],
            // 243 migraciones + SELECT INTO de los comandos de corrección
            ['CREATE TABLE', 'migraciones y SELECT ... INTO'],
            ['ALTER ANY SCHEMA', 'ALTER TABLE ... NOCHECK CONSTRAINT de MigrateLegacyAliado'],
            ['VIEW DEFINITION', 'lectura de INFORMATION_SCHEMA / sys.tables'],
        ];

        $fallos = 0;
        foreach ($checks as [$permiso, $porque]) {
            $ok = (bool) $db->selectOne(
                'SELECT HAS_PERMS_BY_NAME(DB_NAME(), ?, ?) AS p',
                ['DATABASE', $permiso]
            )->p;
            $this->resultado($permiso, $ok, $porque);
            $fallos += $ok ? 0 : 1;
        }

        return $fallos;
    }

    /**
     * La prueba que de verdad convence: crear, alterar, escribir y borrar. Se
     * hace sobre una tabla propia con nombre `_zz_`, nunca sobre datos reales,
     * y se limpia siempre — incluso si algo falla a mitad.
     */
    private function probarDdlReal($db): int
    {
        $this->newLine();
        $this->line('  <options=bold>DDL de verdad</>');

        $t = self::TABLA_PRUEBA;
        $fallos = 0;

        try {
            $db->statement("CREATE TABLE [$t] (id INT IDENTITY(1,1) PRIMARY KEY, valor NVARCHAR(20))");
            $this->resultado('CREATE TABLE', true, "tabla [$t]");

            $db->table($t)->insert(['valor' => 'prueba']);
            $this->resultado('INSERT', true, '1 fila');

            $db->statement("ALTER TABLE [$t] ADD extra INT NULL");
            $this->resultado('ALTER TABLE', true, 'columna agregada');

            $leidas = $db->table($t)->count();
            $this->resultado('SELECT', $leidas === 1, "$leidas fila(s)");

            $db->table($t)->where('valor', 'prueba')->delete();
            $this->resultado('DELETE', true, '');
        } catch (\Throwable $e) {
            $this->resultado('DDL', false, $e->getMessage());
            $fallos++;
        } finally {
            // Pase lo que pase, no dejar la tabla de prueba en la base.
            try {
                if (Schema::connection($db->getName())->hasTable($t)) {
                    $db->statement("DROP TABLE [$t]");
                    $this->resultado('DROP TABLE', true, 'limpieza hecha');
                }
            } catch (\Throwable $e) {
                $this->warn("  ¡OJO! Quedó la tabla [$t] sin borrar: ".$e->getMessage());
                $fallos++;
            }
        }

        return $fallos;
    }

    private function resultado(string $que, bool $ok, string $nota): void
    {
        $this->line(sprintf(
            '   %s %-20s %s',
            $ok ? '<fg=green>ok</>  ' : '<fg=red>FALTA</>',
            $que,
            $nota ? "<fg=gray>$nota</>" : ''
        ));
    }
}
