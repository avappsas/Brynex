<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$cedulas = ['1118290659', '1143943543'];

foreach ($cedulas as $cedula) {
    echo "=========================================\n";
    echo "CLIENTE CÉDULA: $cedula\n";
    echo "=========================================\n";

    $contratos = DB::table('contratos as c')
        ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
        ->where('c.cedula', $cedula)
        ->select('c.id', 'c.estado', 'c.fecha_ingreso', 'c.fecha_retiro', 'c.razon_social_id', 'rs.razon_social', 'c.observacion_afiliacion', 'c.fecha_created', 'c.updated_at')
        ->orderBy('c.id')
        ->get();

    echo "CONTRATOS:\n";
    foreach ($contratos as $c) {
        echo "ID: {$c->id} | RS: [ID {$c->razon_social_id}] {$c->razon_social}\n";
        echo "   Estado: {$c->estado} | Ingreso: {$c->fecha_ingreso} | Retiro: {$c->fecha_retiro}\n";
        echo "   Creado: {$c->fecha_created} | Modificado: {$c->updated_at}\n";
        echo "   Obs: {$c->observacion_afiliacion}\n\n";
    }

    $planos = DB::table('planos')
        ->where('no_identifi', $cedula)
        ->select('id', 'contrato_id', 'tipo_reg', 'fecha_ing', 'fecha_ret', 'mes_plano', 'anio_plano', 'n_plano', 'razon_social', 'deleted_at')
        ->orderBy('id')
        ->get();

    echo "PLANOS PILA:\n";
    foreach ($planos as $p) {
        $del = $p->deleted_at ? " [ELIMINADO: {$p->deleted_at}]" : "";
        echo "ID: {$p->id} | Contrato: {$p->contrato_id} | Tipo: {$p->tipo_reg}{$del}\n";
        echo "   Ingreso: {$p->fecha_ing} | Retiro: {$p->fecha_ret} | Periodo: {$p->mes_plano}/{$p->anio_plano} | Plano #: {$p->n_plano} | RS: {$p->razon_social}\n\n";
    }
}
