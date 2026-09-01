<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan «Solo ARL Independiente» para Gestión ARL.
 *
 * En ese módulo hay trabajadores de los dos tipos, y hasta ahora el tipo se
 * deducía de la modalidad: todos salían como independientes, cuando las
 * coberturas reales del portal son dependientes. Ahora lo dice el plan.
 *
 * Importa porque Sura los trata distinto: son formularios distintos para
 * afiliar y pantallas distintas para anular, y usar la que no es hace que el
 * portal responda que no encuentra al trabajador.
 *
 * El independiente se afilia igual con la póliza y la credencial de la empresa
 * —no hay usuario del portal de cada persona—; lo único que cambia es el tipo
 * de cotizante que se envía.
 */
return new class extends Migration
{
    private const CODIGO = 'SOLO_ARL_IND';

    private const MODALIDAD_GESTION_ARL = 15;

    public function up(): void
    {
        $planId = DB::table('planes_contrato')->where('codigo', self::CODIGO)->value('id');

        if (! $planId) {
            $planId = DB::table('planes_contrato')->insertGetId([
                'codigo'          => self::CODIGO,
                'nombre'          => 'Solo ARL Independiente',
                'incluye_eps'     => 0,
                'incluye_arl'     => 1,
                'incluye_pension' => 0,
                'incluye_caja'    => 0,
                'activo'          => 1,
                'descripcion'     => 'Solo ARL para quien se afilia como INDEPENDIENTE (cotizante 59), '
                    .'yendo por una razón social real. Existe aparte de «Solo ARL» porque Sura trata '
                    .'distinto a dependientes e independientes: son formularios distintos para afiliar '
                    .'y pantallas distintas para anular, y elegir el que no es hace que el portal no '
                    .'encuentre al trabajador.',
            ]);
        }

        $yaEsta = DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', self::MODALIDAD_GESTION_ARL)
            ->where('plan_id', $planId)
            ->exists();

        if (! $yaEsta) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => self::MODALIDAD_GESTION_ARL,
                'plan_id'           => $planId,
                'solo_ia'           => 0,
            ]);
        }
    }

    public function down(): void
    {
        $planId = DB::table('planes_contrato')->where('codigo', self::CODIGO)->value('id');

        if (! $planId) {
            return;
        }

        // No se borra el plan si algún contrato ya lo está usando: perder ese
        // dato cambiaría cómo se afilia a esa gente en el portal.
        if (DB::table('contratos')->where('plan_id', $planId)->exists()) {
            return;
        }

        DB::table('modalidad_planes')->where('plan_id', $planId)->delete();
        DB::table('planes_contrato')->where('id', $planId)->delete();
    }
};
