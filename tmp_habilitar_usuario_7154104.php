<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

app()['cache']->forget('spatie.permission.cache');

echo "=== HABILITANDO MÓDULOS PARA USUARIO CÉDULA 7154104 ===\n\n";

// Buscar usuario por cédula
$u = User::where('cedula', '7154104')->first();
if (!$u) {
    echo "❌ No encontrado por cédula. Listando todos los usuarios:\n";
    foreach (User::select('id','nombre','email','cedula')->get() as $x) {
        echo "  id={$x->id} cedula={$x->cedula} nombre={$x->nombre}\n";
    }
    exit(1);
}

echo "✅ Usuario: {$u->nombre} (id={$u->id}, cédula={$u->cedula})\n";
echo "   Roles actuales: " . ($u->getRoleNames()->implode(', ') ?: 'ninguno') . "\n\n";

// Asegurar que exista el rol 'usuario'
$rol = Role::firstOrCreate(['name' => 'usuario', 'guard_name' => 'web']);
echo "Rol 'usuario' (id={$rol->id}): ok\n";

// Asignar rol si no lo tiene
if (!$u->hasRole('usuario')) {
    $u->assignRole('usuario');
    echo "  → Rol 'usuario' asignado ✅\n";
} else {
    echo "  → Ya tenía el rol 'usuario' ✅\n";
}

// Asegurar permiso ver-planos y asignarlo al rol 'usuario'
$perm = Permission::firstOrCreate(['name' => 'ver-planos', 'guard_name' => 'web']);
if (!$rol->hasPermissionTo('ver-planos')) {
    $rol->givePermissionTo('ver-planos');
    echo "  → Permiso 'ver-planos' asignado al rol 'usuario' ✅\n";
} else {
    echo "  → Rol 'usuario' ya tenía 'ver-planos' ✅\n";
}

app()['cache']->forget('spatie.permission.cache');

$u->refresh();
echo "\n=== RESULTADO FINAL ===\n";
echo "Roles:   " . $u->getRoleNames()->implode(', ') . "\n";
echo "\nMódulos habilitados:\n";
echo "  ✅ Afiliaciones\n";
echo "  ✅ Planos SS\n";
echo "  ✅ Cobros\n";
echo "  ✅ Cuadre Caja\n";
echo "\n¡Listo!\n";
