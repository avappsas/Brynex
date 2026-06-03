<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrynexModulosSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        $modulos = [
            [1, 'administracion',   'Administración Mensual',           'Cobro mensual por cada contrato activo del aliado en el período.', 1, 1],
            [2, 'afiliaciones',     'Gestión de Afiliaciones',          'Cobro por cada afiliación creada en el mes (requiere afiliaciones_brynex=true).', 1, 2],
            [3, 'wa_plantillas',    'WhatsApp — Mensajes de Plantilla', 'Cobro por cada mensaje de plantilla enviado en el mes.', 1, 3],
            [4, 'wa_conversaciones','WhatsApp — Conversaciones',        'Cobro por cada conversación con actividad en el mes.', 1, 4],
            [5, 'portal_clientes',  'Portal de Clientes',               'Módulo futuro: cobro por acceso al portal web de clientes.', 0, 5],
            [6, 'incapacidades',    'Gestión de Incapacidades',         'Módulo futuro: cobro por gestión de incapacidades ante EPS/ARL/AFP.', 0, 6],
            [7, 'envio_planillas',  'Envío de Planillas SS',            'Módulo futuro: cobro por planillas de seguridad social.', 0, 7],
        ];

        foreach ($modulos as [$id, $codigo, $nombre, $descripcion, $activo, $orden]) {
            // Si ya existe, actualizar; si no, insertar con IDENTITY
            $existe = DB::table('brynex_modulos')->where('id', $id)->exists();
            if ($existe) {
                DB::table('brynex_modulos')->where('id', $id)->update([
                    'codigo'      => $codigo,
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion,
                    'activo'      => $activo,
                    'orden'       => $orden,
                    'updated_at'  => $now,
                ]);
            } else {
                // Para SQL Server: SET IDENTITY_INSERT dentro de la misma query via unprepared
                DB::unprepared("
                    SET IDENTITY_INSERT brynex_modulos ON;
                    INSERT INTO brynex_modulos (id, codigo, nombre, descripcion, activo, orden, created_at, updated_at)
                    VALUES ({$id}, '{$codigo}', " . DB::getPdo()->quote($nombre) . ", " . DB::getPdo()->quote($descripcion) . ", {$activo}, {$orden}, '{$now}', '{$now}');
                    SET IDENTITY_INSERT brynex_modulos OFF;
                ");
            }
        }
    }
}
