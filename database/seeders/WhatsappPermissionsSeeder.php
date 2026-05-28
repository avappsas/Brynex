<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WhatsappPermissionsSeeder extends Seeder
{
    /**
     * Crea los permisos del módulo WhatsApp y los asigna a los roles correspondientes.
     *
     * Permisos:
     *  - whatsapp.ver       → Ver inbox de conversaciones (todos los usuarios del aliado)
     *  - whatsapp.responder → Enviar mensajes en el chat
     *  - whatsapp.asignar   → Asignar/reasignar conversaciones
     *  - whatsapp.plantillas → Gestionar plantillas (admin)
     *  - whatsapp.masivo    → Lanzar envíos masivos (admin)
     *  - whatsapp.configurar → Configurar credenciales de Meta (solo Brynex superadmin)
     */
    public function run(): void
    {
        // Resetear caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'whatsapp.ver',
            'whatsapp.responder',
            'whatsapp.asignar',
            'whatsapp.plantillas',
            'whatsapp.masivo',
            'whatsapp.configurar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // ── Asignar permisos a roles ──────────────────────────────────────────

        // superadmin: todos los permisos
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin->givePermissionTo($permisos);

        // admin: todo excepto configurar (eso es solo Brynex)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            'whatsapp.ver',
            'whatsapp.responder',
            'whatsapp.asignar',
            'whatsapp.plantillas',
            'whatsapp.masivo',
        ]);

        // usuario: ver, responder y asignar
        $usuario = Role::firstOrCreate(['name' => 'usuario', 'guard_name' => 'web']);
        $usuario->givePermissionTo([
            'whatsapp.ver',
            'whatsapp.responder',
            'whatsapp.asignar',
        ]);

        // contador: solo ver
        $contador = Role::firstOrCreate(['name' => 'contador', 'guard_name' => 'web']);
        $contador->givePermissionTo(['whatsapp.ver']);

        $this->command->info('✅ Permisos de WhatsApp creados y asignados correctamente.');
    }
}
