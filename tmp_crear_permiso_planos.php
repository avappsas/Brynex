<?php
// Script para crear permiso ver-planos y asignarlo a todos los roles
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Limpiar caché de permisos primero
app()['cache']->forget('spatie.permission.cache');

$permiso = Permission::firstOrCreate([
    'name'       => 'ver-planos',
    'guard_name' => 'web',
]);
echo "Permiso '{$permiso->name}' (id={$permiso->id})\n";

// Asignar a todos los roles existentes (todos los roles pueden ver planos)
$roles = Role::all();
foreach ($roles as $role) {
    if (!$role->hasPermissionTo($permiso)) {
        $role->givePermissionTo($permiso);
        echo "  → {$role->name}: permiso asignado\n";
    } else {
        echo "  → {$role->name}: ya tenía el permiso\n";
    }
}

echo "\nListo. Permisos configurados correctamente.\n";
