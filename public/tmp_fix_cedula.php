<?php
/**
 * Script temporal para ejecutar la migración add_cedula_to_users_table en producción.
 * ELIMINAR después de usar.
 *
 * Acceder desde: https://brynex.co/tmp_fix_cedula.php?token=BryNex2026Fix
 */

$token = $_GET['token'] ?? '';
if ($token !== 'BryNex2026Fix') {
    http_response_code(403);
    die('Acceso denegado.');
}

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain; charset=UTF-8');

echo "=== BryNex - Fix columna cedula en users ===\n\n";

try {
    // 1. Verificar estado actual
    $tieneCedula   = Schema::hasColumn('users', 'cedula');
    $tieneTelefono = Schema::hasColumn('users', 'telefono');

    echo "Estado actual:\n";
    echo "  columna 'cedula'   -> " . ($tieneCedula   ? "EXISTE ✓" : "NO EXISTE ✗") . "\n";
    echo "  columna 'telefono' -> " . ($tieneTelefono ? "EXISTE ✓" : "NO EXISTE ✗") . "\n\n";

    // 2. Agregar columnas faltantes
    Schema::table('users', function (Blueprint $table) use ($tieneCedula, $tieneTelefono) {
        if (!$tieneCedula) {
            $table->string('cedula', 20)->nullable()->after('password');
            echo "  [OK] Columna 'cedula' agregada.\n";
        } else {
            echo "  [SKIP] Columna 'cedula' ya existía.\n";
        }
        if (!$tieneTelefono) {
            $table->string('telefono', 30)->nullable()->after('cedula');
            echo "  [OK] Columna 'telefono' agregada.\n";
        } else {
            echo "  [SKIP] Columna 'telefono' ya existía.\n";
        }
    });

    // 3. Verificar que la migración esté registrada en la tabla migrations
    $migrationName = '2026_04_21_163030_add_cedula_to_users_table';
    $existe = DB::table('migrations')->where('migration', $migrationName)->exists();
    if (!$existe) {
        $batch = DB::table('migrations')->max('batch') + 1;
        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch'     => $batch,
        ]);
        echo "  [OK] Migración registrada en tabla migrations (batch $batch).\n";
    } else {
        echo "  [SKIP] Migración ya estaba registrada.\n";
    }

    echo "\n=== LISTO. Intenta iniciar sesión nuevamente. ===\n";
    echo "\nRECUERDA: eliminar este archivo después de usarlo.\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
