<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Http\Controllers\Admin\ContratoController;

$contrato = Contrato::find(10901);
if (!$contrato) {
    echo "Contrato no encontrado\n";
    exit;
}

// Simular sesión de aliado activo
session(['aliado_id_activo' => $contrato->aliado_id]);

$controller = new ContratoController();
$contratoId = 10901;

echo "Buscando qué combinación de mes y días da mora = 7493:\n";

for ($mes = 1; $mes <= 12; $mes++) {
    for ($dias = 1; $dias <= 30; $dias++) {
        $req = new \Illuminate\Http\Request([
            'dias' => $dias,
            'mes_plano' => $mes,
            'anio_plano' => 2026,
            'tipo_retiro' => 'real'
        ]);
        
        $resp = $controller->apiCalcularRetiro($req, $contratoId);
        $data = json_decode($resp->getContent(), true);
        
        if ($data['mora'] >= 7400 && $data['mora'] <= 7600) {
            echo "Mes Plano: $mes, Dias: $dias -> Costo SS: {$data['costo_ss']}, Mora: {$data['mora']}\n";
        }
    }
}
