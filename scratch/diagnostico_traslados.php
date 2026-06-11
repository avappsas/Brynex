<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$aliadoId = 6;
echo "=== DIAGNÓSTICO TRASLADOS ALIADO $aliadoId ===\n";

// Buscaremos contratos creados o modificados en las últimas 48 horas para el aliado 6
$fechaLimite = now()->subDays(2)->toDateTimeString();

echo "Contratos creados o modificados recientemente (últimas 48 horas):\n";
$contratos = DB::table('contratos as c')
    ->join('clientes as cl', 'cl.cedula', '=', 'c.cedula')
    ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
    ->where('c.aliado_id', $aliadoId)
    ->where(function($q) use ($fechaLimite) {
        $q->where('c.fecha_created', '>=', $fechaLimite)
          ->orWhere('c.updated_at', '>=', $fechaLimite);
    })
    ->select('c.id', 'c.cedula', 'c.estado', 'c.fecha_ingreso', 'c.fecha_retiro', 'c.razon_social_id', 'rs.razon_social', 'c.observacion_afiliacion', 'c.fecha_created', 'c.updated_at',
        DB::raw("cl.primer_nombre + ' ' + cl.primer_apellido as cliente_nombre"))
    ->orderBy('c.cedula')
    ->orderBy('c.id')
    ->get();

if ($contratos->isEmpty()) {
    echo "No se encontraron contratos modificados recientemente.\n";
} else {
    foreach ($contratos as $c) {
        echo "ID: {$c->id} | Cédula: {$c->cedula} | Nombre: {$c->cliente_nombre}\n";
        echo "   RS: [ID {$c->razon_social_id}] {$c->razon_social}\n";
        echo "   Estado: {$c->estado} | Ingreso: {$c->fecha_ingreso} | Retiro: {$c->fecha_retiro}\n";
        echo "   Creado: {$c->fecha_created} | Modificado: {$c->updated_at}\n";
        echo "   Obs: {$c->observacion_afiliacion}\n\n";
    }
}
