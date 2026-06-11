<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aliadoId = 6;
echo "=== LOGS EN RANGO HORARIO ===\n";

$logs = DB::table('bitacora')
    ->where('aliado_id', $aliadoId)
    ->where('created_at', '>=', '2026-06-09 12:00:00')
    ->where('created_at', '<=', '2026-06-09 12:20:00')
    ->orderBy('created_at')
    ->get();

foreach ($logs as $log) {
    echo "[{$log->created_at}] User: {$log->user_id} | Acción: {$log->accion} | Modelo: {$log->modelo} | Registro ID: {$log->registro_id}\n";
    echo "   Descripción: {$log->descripcion}\n\n";
}
