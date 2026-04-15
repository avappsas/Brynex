<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    /**
     * Crea los 6 roles del sistema BryNex.
     *
     * brynex     → Flag interno (es_brynex=true en users), no es un rol estándar
     * superadmin → Todo el sistema de SU empresa aliada
     * admin      → Todo excepto módulos contables restringidos
     * contable   → Solo módulo financiero/contable
     * usuario    → Empleado interno: clientes, facturación, afiliaciones
     * asesor     → Solo sus propios clientes
     * cliente    → Solo su información y pagos
     */
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'superadmin',
            'admin',
            'contable',
            'usuario',
            'asesor',
            'cliente',
        ];

        foreach ($roles as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->command->info('✅ 6 roles creados: ' . implode(', ', $roles));
    }
}
