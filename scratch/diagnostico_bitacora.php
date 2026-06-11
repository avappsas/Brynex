<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aliadoId = 6;
$fecha = '2026-06-09';
echo "=== BITÁCORA ALIADO $aliadoId EN LA FECHA $fecha ===\n";

$logs = DB::table('bitacora')
    ->where('aliado_id', $aliadoId)
    ->whereDate('created_at', $fecha)
    ->orderBy('created_at')
    ->get();

if ($logs->isEmpty()) {
    echo "No se encontraron logs de bitácora para esa fecha.\n";
} else {
    foreach ($logs as $log) {
        echo "[{$log->created_at}] User: {$log->user_id} | Acción: {$log->accion} | Modelo: {$log->modelo} | Registro ID: {$log->registro_id}\n";
        echo "   Descripción: {$log->descripcion}\n";
        if ($log->detalle) {
            echo "   Detalle: {$log->detalle}\n";
        }
        echo "\n";
    }
}
