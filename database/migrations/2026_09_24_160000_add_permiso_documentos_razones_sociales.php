<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `razones_sociales.documentos`: subir y eliminar los documentos de una razón
 * social (cámara de comercio, RUT, certificación bancaria…) sin poder editar
 * sus datos.
 *
 * Antes iban con `razones_sociales.gestionar`, así que para que alguien subiera
 * la cámara de comercio había que dejarlo también cambiar el NIT, la ARL o
 * inactivar la empresa. El catálogo vive en ModulosPermisosSeeder; esta
 * migración solo crea la fila para no tener que re-sembrar producción, que
 * reconstruye la matriz de todos los roles.
 *
 * Lo trae el rol admin (igual que `gestionar`); a un usuario se le da a mano
 * desde Usuarios → Permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('modulos')->where('codigo', 'razones_sociales')->value('id');

        if (DB::table('permissions')->where('name', 'razones_sociales.documentos')->exists()) {
            return;
        }

        $id = DB::table('permissions')->insertGetId([
            'name' => 'razones_sociales.documentos',
            'guard_name' => 'web',
            'modulo_id' => $moduloId,
            'etiqueta' => 'Subir y eliminar documentos',
            'accion' => 'documentos',
            'restringido' => false,
            'asignable' => true,
            'orden' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // El mismo orden que le daría el seeder: ver, gestionar, documentos, eliminar.
        DB::table('permissions')->where('name', 'razones_sociales.eliminar')->update(['orden' => 40]);

        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if ($adminId) {
            DB::table('role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $adminId]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'razones_sociales.documentos')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        DB::table('permissions')->where('name', 'razones_sociales.eliminar')->update(['orden' => 30]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
