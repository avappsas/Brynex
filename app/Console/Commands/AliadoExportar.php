<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\ExportacionAliado;
use App\Models\User;
use App\Services\Exportacion\ExportAliadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega de datos de un aliado, desde la consola.
 *
 * Es la salida de emergencia del botón del panel: si WhatsApp no responde, o si
 * el aliado es tan grande que la generación por HTTP se topa con el timeout de
 * Apache, se corre esto. Corriéndolo en local el ZIP nunca toca el servidor de
 * producción, aunque la base de datos sí sea la misma.
 *
 *   php artisan aliado:exportar 4
 *   php artisan aliado:exportar 4 --forzar
 */
class AliadoExportar extends Command
{
    protected $signature = 'aliado:exportar
                            {aliado? : Id o nombre del aliado}
                            {--usuario= : Correo del responsable (por defecto, el primero de config/exportacion)}
                            {--forzar : No preguntar}';

    protected $description = 'Genera el ZIP con los datos de un aliado que se va de la plataforma';

    public function handle(ExportAliadoService $exportador): int
    {
        $aliado = $this->resolverAliado();

        if (! $aliado) {
            return self::FAILURE;
        }

        $usuario = $this->resolverUsuario();

        if (! $usuario) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  Aliado      : '.$aliado->nombre.' (id '.$aliado->id.')');
        $this->line('  Responsable : '.$usuario->nombre.' <'.$usuario->email.'>');
        $this->line('  Destino     : storage/app/'.config('exportacion.carpeta').'/');
        $this->newLine();
        $this->warn('  Esto entrega datos personales y de salud de todas las personas de este aliado.');
        $this->newLine();

        if (! $this->option('forzar') && ! $this->confirm('¿Generar la entrega?', false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $registro = ExportacionAliado::create([
            'aliado_id' => $aliado->id,
            'solicitado_por' => $usuario->id,
            'estado' => 'confirmado',
            'confirmado_at' => now(),
            'ip' => 'consola',
        ]);

        $this->newLine();

        try {
            $registro = $exportador->generar($registro, function (string $titulo, int $filas) {
                // Relleno por caracteres y no por bytes: con sprintf, "Facturación"
                // cuenta 12 y desalinea la columna.
                $relleno = str_repeat('.', max(1, 34 - mb_strlen($titulo)));
                $this->line('  '.$titulo.' '.$relleno.' '.number_format($filas, 0, ',', '.'));
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('  Entrega #'.$registro->id.' lista.');
        $this->line('  Archivo     : '.Storage::disk('local')->path($registro->archivo));
        $this->line('  Registros   : '.number_format((int) $registro->filas_total, 0, ',', '.'));
        $this->line('  Tamaño      : '.$registro->tamanoLegible());
        $this->line('  SHA-256     : '.$registro->archivo_hash);
        $this->line('  Referencia  : '.$registro->traza_token);

        if ($password = $registro->passwordPlano()) {
            $this->newLine();
            $this->info('  Contraseña del ZIP: '.$password);
            $this->line('  (queda guardada cifrada; el panel la vuelve a mostrar)');
        } else {
            $this->newLine();
            $this->warn('  El ZIP quedó SIN contraseña: '.($registro->error ?: 'este PHP no soporta cifrado AES en ZIP'));
        }

        $this->newLine();
        $this->line('  Verificar la referencia: php artisan traza:verificar "'.$registro->traza_token.'"');
        $this->newLine();

        return self::SUCCESS;
    }

    private function resolverAliado(): ?Aliado
    {
        $arg = $this->argument('aliado');

        if (! $arg) {
            $this->table(['Id', 'Aliado', 'Activo'], Aliado::orderBy('nombre')->get()
                ->map(fn ($a) => [$a->id, $a->nombre, $a->activo ? 'Sí' : 'No'])->all());

            $arg = $this->ask('Id del aliado');
        }

        $aliado = is_numeric($arg)
            ? Aliado::find((int) $arg)
            : Aliado::where('nombre', 'like', '%'.$arg.'%')->first();

        if (! $aliado) {
            $this->error('No encontré ese aliado.');

            return null;
        }

        return $aliado;
    }

    private function resolverUsuario(): ?User
    {
        $autorizados = (array) config('exportacion.correos_autorizados');
        $correo = $this->option('usuario') ?: ($autorizados[0] ?? null);

        if (! $correo) {
            $this->error('No hay correos autorizados en config/exportacion.php.');

            return null;
        }

        // Misma lista blanca que la web: la consola no es una puerta más ancha.
        if (! in_array(strtolower($correo), array_map('strtolower', $autorizados), true)) {
            $this->error($correo.' no está en la lista de correos autorizados.');

            return null;
        }

        $usuario = User::where('email', $correo)->first();

        if (! $usuario) {
            $this->error('No existe un usuario con el correo '.$correo.'.');

            return null;
        }

        return $usuario;
    }
}
