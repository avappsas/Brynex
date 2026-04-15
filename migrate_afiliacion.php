<?php
// Script para ejecutar las 3 migraciones de afiliación en BryNex
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "=== Migraciones Módulo Afiliación ===\n\n";

// 1. configuracion_aliado
if (!Schema::hasColumn('configuracion_aliado', 'dist_admon_pct')) {
    Schema::table('configuracion_aliado', function (Blueprint $table) {
        $table->decimal('dist_admon_pct',  5, 2)->default(0)->after('costo_afiliacion');
        $table->decimal('dist_retiro_pct', 5, 2)->default(0)->after('dist_admon_pct');
    });
    echo "✅ configuracion_aliado: dist_admon_pct, dist_retiro_pct agregados\n";
} else {
    echo "⏭ configuracion_aliado: ya tiene los campos\n";
}

// 2. contratos
if (!Schema::hasColumn('contratos', 'cobra_planilla_primer_mes')) {
    Schema::table('contratos', function (Blueprint $table) {
        $table->boolean('cobra_planilla_primer_mes')->default(false)->after('observacion_afiliacion');
    });
    echo "✅ contratos: cobra_planilla_primer_mes agregado\n";
} else {
    echo "⏭ contratos: ya tiene el campo\n";
}

// 3. facturas
if (!Schema::hasColumn('facturas', 'dist_admon')) {
    Schema::table('facturas', function (Blueprint $table) {
        $table->integer('dist_admon')->default(0)->after('valor_prestamo');
        $table->integer('dist_asesor')->default(0)->after('dist_admon');
        $table->integer('dist_retiro')->default(0)->after('dist_asesor');
        $table->integer('dist_utilidad')->default(0)->after('dist_retiro');
    });
    echo "✅ facturas: dist_admon, dist_asesor, dist_retiro, dist_utilidad agregados\n";
} else {
    echo "⏭ facturas: ya tiene los campos\n";
}

echo "\n=== Migraciones completadas ===\n";
