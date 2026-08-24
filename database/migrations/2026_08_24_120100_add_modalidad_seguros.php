<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modalidad "Seguros": a esta persona no se le vende seguridad social, solo un seguro.
 *
 * No lleva EPS, ARL, pensión ni caja, no genera planilla y no paga administración:
 * el contrato vale lo que valga el seguro del catálogo del aliado (ver aliado_seguros).
 *
 * Ya se venía haciendo a mano — en SS Faga hay contratos de plan exequial cobrando
 * $30.000 de seguro + $4.900 de administración con la modalidad en blanco y un
 * `total_ss` de $100 de relleno para que la factura no quedara en cero.
 */
return new class extends Migration
{
    private const MODALIDAD_SEGUROS = 17;

    public function up(): void
    {
        // Plan genérico: no incluye ninguna entidad de seguridad social. Cuál seguro
        // es concretamente lo dice `contratos.seguro_id`, que apunta al catálogo del aliado.
        $planId = DB::table('planes_contrato')->where('codigo', 'SOLO_SEGURO')->value('id');

        if (! $planId) {
            $planId = DB::table('planes_contrato')->insertGetId([
                'codigo' => 'SOLO_SEGURO',
                'nombre' => 'Seguro',
                'incluye_eps' => 0,
                'incluye_arl' => 0,
                'incluye_pension' => 0,
                'incluye_caja' => 0,
                'activo' => 1,
            ]);
        }

        if (! DB::table('tipo_modalidad')->where('id', self::MODALIDAD_SEGUROS)->exists()) {
            DB::table('tipo_modalidad')->insert([
                'id' => self::MODALIDAD_SEGUROS,
                'tipo_modalidad' => 'SEG',
                'observacion' => 'Seguros',
                'orden' => 19,          // después de UPC (18)
                'modalidad' => 'Seguros',
                'activo' => 1,
                'es_tiempo_parcial' => 0,
                'descripcion' => 'Solo seguro: no se le afilia a EPS, ARL, pensión ni caja, '
                                     .'y no entra en planilla. El contrato cobra cada mes el valor '
                                     .'del seguro que el aliado tenga configurado.',
            ]);
        }

        DB::table('modalidad_planes')->updateOrInsert(
            ['tipo_modalidad_id' => self::MODALIDAD_SEGUROS, 'plan_id' => $planId],
            ['solo_ia' => 0]
        );
    }

    public function down(): void
    {
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_SEGUROS)->delete();

        // La modalidad no se borra si algún contrato ya la usa: dejaría la FK colgando.
        if (! DB::table('contratos')->where('tipo_modalidad_id', self::MODALIDAD_SEGUROS)->exists()) {
            DB::table('tipo_modalidad')->where('id', self::MODALIDAD_SEGUROS)->delete();
        }
    }
};
