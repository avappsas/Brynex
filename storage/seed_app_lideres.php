<?php
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Finanzas\FuenteIngreso;
use App\Models\Finanzas\AppLiderAliado;
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();

    // 1. Asegurar Fuente de Ingreso
    $fuente = FuenteIngreso::where('user_id', 2)
        ->where('nombre', 'APP LIDERES')
        ->first();
    if ($fuente) {
        $fuente->update(['nombre' => 'OTRAS APP']);
        echo "Fuente renombrada a 'OTRAS APP'.\n";
    }

    // 2. Eliminar aliados antiguos que no sean ARROYAVE
    DB::connection('finanzas')->table('finanzas_app_lideres_aliados')->where('user_id', 2)->delete();
    echo "Aliados antiguos de Otras App eliminados.\n";

    // 3. Crear el único aliado ARROYAVE
    $arroyave = AppLiderAliado::create([
        'user_id' => 2,
        'nombre' => 'ARROYAVE',
        'valor_mensual' => 250000,
        'fecha_inicio' => '2020-01-01', // Desde el inicio de su historial
        'activo' => true,
    ]);
    echo "Aliado ARROYAVE creado.\n";

    // 4. Limpiar todos los pagos antiguos
    DB::connection('finanzas')->table('finanzas_app_lideres_pagos')->where('user_id', 2)->delete();

    // 5. Seedar pagos del historial
    $pagos = [];

    // Año 2026: Ene-May: $300.000, Jun-Dic: $0
    for ($m = 1; $m <= 12; $m++) {
        $monto = ($m <= 5) ? 300000 : 0;
        $pagos[] = [
            'user_id' => 2,
            'app_lider_aliado_id' => $arroyave->id,
            'recibo_id' => null,
            'anio' => 2026,
            'mes' => $m,
            'monto' => $monto,
            'estado' => 'completo',
            'saldo_pendiente' => 0,
            'observacion' => 'Historial importado de Excel.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // Años 2021 a 2025: todos los meses $250.000
    for ($y = 2021; $y <= 2025; $y++) {
        for ($m = 1; $m <= 12; $m++) {
            $pagos[] = [
                'user_id' => 2,
                'app_lider_aliado_id' => $arroyave->id,
                'recibo_id' => null,
                'anio' => $y,
                'mes' => $m,
                'monto' => 250000,
                'estado' => 'completo',
                'saldo_pendiente' => 0,
                'observacion' => 'Historial importado de Excel.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    // Año 2020: Ene-Abr: $300.000, May-Sep: $0, Oct-Dic: $250.000
    for ($m = 1; $m <= 12; $m++) {
        if ($m <= 4) {
            $monto = 300000;
        } elseif ($m <= 9) {
            $monto = 0;
        } else {
            $monto = 250000;
        }
        $pagos[] = [
            'user_id' => 2,
            'app_lider_aliado_id' => $arroyave->id,
            'recibo_id' => null,
            'anio' => 2020,
            'mes' => $m,
            'monto' => $monto,
            'estado' => 'completo',
            'saldo_pendiente' => 0,
            'observacion' => 'Historial importado de Excel.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    DB::connection('finanzas')->table('finanzas_app_lideres_pagos')->insert($pagos);
    echo "Historial de pagos de ARROYAVE de 2020 a 2026 insertado con éxito.\n";

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
