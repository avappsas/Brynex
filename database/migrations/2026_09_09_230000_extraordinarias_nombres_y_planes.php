<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja las tres modalidades extraordinarias listas para vender: nombre de cara
 * al usuario, familia común y el plan que amarra qué entidad incluyen.
 *
 * Se llaman "Tipo E - …" porque eso son: una planilla tipo E de un dependiente,
 * con un solo subsistema. La columna `modalidad` las agrupa como
 * "Extraordinarias" — hoy no la lee ninguna pantalla, pero es la etiqueta por
 * la que se reconocen en la base el día que haya que retirarlas.
 *
 * Los planes dicen qué entidad incluye cada una: "Solo AFP" ya existía y sirve
 * tal cual para la de pensión; el de caja sola no existía y se crea aquí.
 */
return new class extends Migration
{
    private const CAJA_30 = -5;

    private const CAJA_14 = -10;

    private const PENSION_30 = -11;

    private const FAMILIA = 'Extraordinarias';

    public function up(): void
    {
        $nombres = [
            self::CAJA_30 => ['CCF30', 'Tipo E - Caja 30'],
            self::CAJA_14 => ['CCF14', 'Tipo E - Caja 14'],
            self::PENSION_30 => ['PEN30', 'Tipo E - Pension 30'],
        ];

        foreach ($nombres as $id => [$codigo, $nombre]) {
            DB::table('tipo_modalidad')->where('id', $id)->update([
                'tipo_modalidad' => $codigo,
                'observacion' => $nombre,
                'modalidad' => self::FAMILIA,
            ]);
        }

        // ── Plan de caja sola ────────────────────────────────────────────
        // "Solo AFP" (incluye_pension) ya existe desde antes y le sirve a la
        // de pensión; el equivalente de caja no estaba creado.
        $planCaja = DB::table('planes_contrato')->where('codigo', 'SOLO_CCF')->value('id');

        if (! $planCaja) {
            $planCaja = DB::table('planes_contrato')->insertGetId([
                'codigo' => 'SOLO_CCF',
                'nombre' => 'Solo CCF',
                'incluye_eps' => 0,
                'incluye_arl' => 0,
                'incluye_pension' => 0,
                'incluye_caja' => 1,
                'activo' => 1,
                'descripcion' => 'Solo caja de compensación. Se paga en dos planillas encadenadas '
                    .'(ver PilaCotizanteDosPasos): una planilla E de un día y una corrección que '
                    .'anexa la caja.',
            ]);
        }

        $planPension = DB::table('planes_contrato')->where('codigo', 'SOLO_AFP')->value('id');

        $amarres = [
            self::CAJA_30 => $planCaja,
            self::CAJA_14 => $planCaja,
            self::PENSION_30 => $planPension,
        ];

        foreach ($amarres as $modalidad => $plan) {
            if (! $plan) {
                continue;
            }

            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modalidad)
                ->where('plan_id', $plan)
                ->exists();

            if (! $existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => $modalidad,
                    'plan_id' => $plan,
                    'solo_ia' => 0,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')
            ->whereIn('tipo_modalidad_id', [self::CAJA_30, self::CAJA_14, self::PENSION_30])
            ->delete();

        // El plan queda: borrarlo rompería cualquier contrato que ya lo use.
    }
};
