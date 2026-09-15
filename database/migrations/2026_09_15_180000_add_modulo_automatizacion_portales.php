<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo BryNex que autoriza a un aliado a usar la automatización de portales
 * (ARL Sura por API, Nueva EPS, Salud Total, S.O.S., Sanitas, 🩺 Conciliar EPS y
 * 📬 Buzón). Los usuarios BryNex la usan en cualquier aliado; los del aliado solo
 * cuando BryNex la activa en /brynex/consumo/{aliado}/modulos. Brygar (2) ya la
 * tiene autorizada. No entra a la facturación: esa solo cuenta códigos explícitos.
 */
return new class extends Migration
{
    private const CODIGO = 'automatizacion_portales';

    private const BRYGAR = 2;

    public function up(): void
    {
        $id = DB::table('brynex_modulos')->where('codigo', self::CODIGO)->value('id');

        if (! $id) {
            DB::table('brynex_modulos')->insert([
                'codigo'      => self::CODIGO,
                'nombre'      => 'Automatización de portales EPS/ARL',
                'descripcion' => 'Afiliar y anular ARL por API, novedades en portales de EPS (Nueva EPS, Salud Total, S.O.S., Sanitas), correo al asesor, Conciliar EPS y Buzón.',
                'activo'      => true,
                'orden'       => (int) DB::table('brynex_modulos')->max('orden') + 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $id = DB::table('brynex_modulos')->where('codigo', self::CODIGO)->value('id');
        }

        if (DB::table('aliados')->where('id', self::BRYGAR)->exists()
            && ! DB::table('brynex_modulos_aliado')->where('aliado_id', self::BRYGAR)->where('modulo_id', $id)->exists()) {
            DB::table('brynex_modulos_aliado')->insert([
                'aliado_id'         => self::BRYGAR,
                'modulo_id'         => $id,
                'activo'            => true,
                'notas_negociacion' => 'Aliado piloto de la automatización de portales.',
                'fecha_inicio'      => '2026-09-01',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('brynex_modulos')->where('codigo', self::CODIGO)->value('id');
        if ($id) {
            DB::table('brynex_modulos_aliado')->where('modulo_id', $id)->delete();
            DB::table('brynex_tramos_tarifa')->where('modulo_id', $id)->delete();
            DB::table('brynex_modulos')->where('id', $id)->delete();
        }
    }
};
