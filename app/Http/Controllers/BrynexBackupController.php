<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BrynexBackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra el listado de copias de seguridad de Brynex.
     */
    public function backups()
    {
        $user = Auth::user();
        if (!$user->es_brynex || !$user->hasRole('superadmin')) {
            abort(403, 'No tiene permisos para acceder a esta sección.');
        }

        $backupPath = env('BACKUP_PATH', '/var/backups/brynex');
        $backups = [];

        try {
            // Intentar listar los archivos reales del filesystem
            if (File::isDirectory($backupPath)) {
                $files = File::files($backupPath);
                foreach ($files as $file) {
                    $filename = $file->getFilename();
                    $extension = strtolower($file->getExtension());

                    // Solo listar si coincide con extensiones permitidas
                    if (in_array($extension, ['bak', 'zip', 'sql', 'rar', 'gz', 'tar'])) {
                        // Filtrar para mostrar únicamente backups correspondientes a Brynex
                        if (stripos($filename, 'brynex') !== false) {
                            $backups[] = [
                                'nombre' => $filename,
                                'ruta'   => $file->getPathname(),
                                'fecha'  => Carbon::createFromTimestamp($file->getMTime())->format('Y-m-d H:i:s'),
                                'tamano' => $this->formatBytes($file->getSize()),
                                'bytes'  => $file->getSize(),
                            ];
                        }
                    }
                }
                // Ordenar por fecha descendente (más recientes primero)
                usort($backups, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
            }
        } catch (\Exception $e) {
            // Fallback: Si ocurre un problema de permisos en el filesystem,
            // consultamos el historial msdb de SQL Server para el catálogo de base de datos de Brynex.
            try {
                $dbName = DB::connection()->getDatabaseName();
                $rows = DB::select("
                    SELECT
                        bmf.physical_device_name AS ruta,
                        bs.backup_finish_date    AS fecha,
                        bs.backup_size           AS bytes
                    FROM msdb.dbo.backupset bs
                    JOIN msdb.dbo.backupmediafamily bmf
                        ON bs.media_set_id = bmf.media_set_id
                    WHERE bs.database_name = N'{$dbName}'
                      AND bs.type          = 'D'
                    ORDER BY bs.backup_finish_date DESC
                ");

                foreach ($rows as $row) {
                    $nombre = basename($row->ruta);
                    $backups[] = [
                        'nombre' => $nombre,
                        'ruta'   => $row->ruta,
                        'fecha'  => Carbon::parse($row->fecha)->format('Y-m-d H:i:s'),
                        'tamano' => $this->formatBytes($row->bytes ?? 0),
                        'bytes'  => $row->bytes ?? 0,
                    ];
                }
            } catch (\Exception $ex) {
                // Silenciar
            }
        }

        return view('brynex.backups', compact('backups'));
    }

    /**
     * Permite descargar un archivo de backup de forma segura.
     */
    public function descargarBackup(Request $request)
    {
        $user = Auth::user();
        if (!$user->es_brynex || !$user->hasRole('superadmin')) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        $filename = $request->query('file');

        if (empty($filename)) {
            abort(400, 'Nombre de archivo requerido.');
        }

        // Sanitizar el nombre del archivo para prevenir directory traversal
        $filename = basename($filename);

        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(400, 'Nombre de archivo inválido.');
        }

        // Seguridad extra: Solo permitir descargar archivos que correspondan a brynex
        if (stripos($filename, 'brynex') === false) {
            abort(403, 'Acceso denegado a este archivo.');
        }

        $backupPath = env('BACKUP_PATH', '/var/backups/brynex');
        $filePath = $backupPath . DIRECTORY_SEPARATOR . $filename;

        if (!File::exists($filePath)) {
            abort(404, 'El archivo de backup no existe.');
        }

        return response()->download($filePath);
    }

    /**
     * Crea un backup de la base de datos de forma manual.
     */
    public function crearBackupManual()
    {
        $user = Auth::user();
        if (!$user->es_brynex || !$user->hasRole('superadmin')) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        try {
            $timestamp = date('Ymd_His');
            $filename  = "brynex_manual_{$timestamp}.bak";

            // Obtener dinámicamente la base de datos activa
            $dbName = DB::connection()->getDatabaseName();

            // SQL Server escribe en /var/backups/brynex (ruta autorizada en producción).
            // En .env del servidor: BACKUP_PATH=/var/backups/brynex
            $backupPath = env('BACKUP_PATH', '/var/backups/brynex');
            $destPath   = rtrim($backupPath, '/\\') . DIRECTORY_SEPARATOR . $filename;

            // Ejecutar el backup (Express Edition no soporta WITH COMPRESSION)
            DB::statement("BACKUP DATABASE [{$dbName}] TO DISK = N'{$destPath}' WITH FORMAT, INIT");

            return redirect()->route('brynex.backups')
                ->with('success', "Copia de seguridad '{$filename}' generada con éxito en la base de datos '{$dbName}'.");

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if (strpos($errorMessage, 'Operating system error 5') !== false ||
                strpos($errorMessage, 'Access is denied') !== false) {
                return redirect()->route('brynex.backups')
                    ->with('error',
                        'Error de permisos (AppArmor): SQL Server no puede escribir en el directorio configurado. ' .
                        'Verifica que BACKUP_PATH en .env apunte a /var/backups/brynex.'
                    );
            }

            return redirect()->route('brynex.backups')
                ->with('error', 'Error al generar la copia de seguridad: ' . $errorMessage);
        }
    }

    /**
     * Formatea los bytes a una unidad legible.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
