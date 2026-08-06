<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\User;
use App\Services\TrazaArchivoService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Dice de qué cuenta salió un archivo exportado de BryNex.
 *
 * Se le pasa la ruta de un .xlsx (lee la propiedad del documento) o el token
 * suelto, si ya se sacó a mano de las propiedades del archivo o de los
 * metadatos de un PDF.
 *
 *   php artisan traza:verificar /ruta/al/archivo.xlsx
 *   php artisan traza:verificar "u2-a2-1754500000.a1b2c3d4e5f60718"
 */
class TrazaVerificar extends Command
{
    protected $signature = 'traza:verificar {origen : Ruta de un .xlsx o el token de traza}';

    protected $description = 'Identifica qué usuario generó un archivo exportado de BryNex';

    public function handle(TrazaArchivoService $trazas): int
    {
        $origen = $this->argument('origen');
        $token = $origen;

        if (is_file($origen)) {
            $token = $this->tokenDelArchivo($origen);

            if (! $token) {
                $this->error('El archivo no tiene traza de BryNex. O no salió de aquí, o se la quitaron.');

                return self::FAILURE;
            }

            $this->line('Token encontrado: '.$token);
        }

        $datos = $trazas->verificar($token);

        if (! $datos) {
            $this->error('Firma inválida: ese token no lo generó esta instalación, o fue alterado.');

            return self::FAILURE;
        }

        $usuario = User::withTrashed()->find($datos['user_id']);
        $aliado = Aliado::find($datos['aliado_id']);

        $this->newLine();
        $this->info('Traza válida.');
        $this->line('  Usuario : '.($usuario?->nombre ?? 'desconocido').' (id '.$datos['user_id'].', CC '.($usuario?->cedula ?? '?').')');
        $this->line('  Aliado  : '.($aliado?->nombre ?? 'desconocido').' (id '.$datos['aliado_id'].')');
        $this->line('  Exportado: '.$datos['fecha']->format('d/m/Y H:i:s'));
        $this->newLine();

        return self::SUCCESS;
    }

    private function tokenDelArchivo(string $ruta): ?string
    {
        try {
            $props = IOFactory::load($ruta)->getProperties();

            if (! $props->isCustomPropertySet(TrazaArchivoService::PROPIEDAD)) {
                return null;
            }

            return (string) $props->getCustomPropertyValue(TrazaArchivoService::PROPIEDAD);
        } catch (\Throwable $e) {
            $this->error('No pude leer el archivo: '.$e->getMessage());

            return null;
        }
    }
}
