<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo BryNex `arl_api`: solo la parte de ARL de la automatización de
 * portales — afiliar, anular, renovar y bajar el certificado en ARL Sura, y
 * afiliar/anular/retirar en Colmena, por sus APIs.
 *
 * `automatizacion_portales` lo trae todo junto (ARL + portales de EPS +
 * Conciliar EPS + Buzón), y a Fecop y Luis López se les quiso dar solo el
 * botón de afiliar a la ARL. Lo revisa el Gate `automatizar-arl`, que acepta
 * cualquiera de los dos módulos. Se activa en BryNex → Aliados → Editar →
 * Módulos BryNex. No entra a la facturación: esa solo cuenta códigos explícitos.
 */
return new class extends Migration
{
    private const CODIGO = 'arl_api';

    public function up(): void
    {
        if (DB::table('brynex_modulos')->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        DB::table('brynex_modulos')->insert([
            'codigo'      => self::CODIGO,
            'nombre'      => 'ARL por API',
            'descripcion' => 'Afiliar, anular, renovar y certificado en ARL Sura, y afiliar/anular/retirar en ARL Colmena, sin entrar al portal. Sin los portales de EPS.',
            'activo'      => true,
            'orden'       => (int) DB::table('brynex_modulos')->max('orden') + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
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
