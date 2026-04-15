<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Aliado;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $this->call(RolesSeeder::class);

        // 2. Aliado BryNex (empresa base del sistema)
        $brynex = Aliado::firstOrCreate(
            ['nit' => '900000001'],
            [
                'nombre'       => 'BryNex',
                'razon_social' => 'BryNex S.A.S.',
                'correo'       => 'admin@brynex.co',
                'activo'       => true,
            ]
        );

        // 3. Usuario superadmin BryNex
        $admin = User::firstOrCreate(
            ['email' => 'admin@brynex.co'],
            [
                'aliado_id' => $brynex->id,
                'nombre'    => 'Administrador BryNex',
                'password'  => Hash::make('BryNex2024*'),
                'cedula'    => '000000001',
                'es_brynex' => true,
                'activo'    => true,
            ]
        );

        $admin->assignRole('superadmin');

        $this->command->info("✅ Aliado BryNex ID: {$brynex->id}");
        $this->command->info("✅ Usuario admin: admin@brynex.co / BryNex2024*");
        $this->command->info("⚠️  Recuerde cambiar la contraseña en producción.");
    }
}
