<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los valores del campo `estado` de la tabla incapacidades
 * para alinearlos con el nuevo catálogo definido en Incapacidad::ESTADOS.
 *
 * La migración legacy (step13 + fix-incapacidades-pago) dejó los valores
 * con los nombres del sistema antiguo. Este paso los mapea a los nuevos.
 *
 * Mapa de conversión:
 *   pagado_afiliado → pagada
 *   cerrado         → pagada
 *   autorizado      → liquidacion
 *   liquidado       → liquidacion
 *   radicado        → radicada
 *   en_tramite      → radicada  (aproximación: el usuario puede ajustar luego)
 *
 * El campo estado_pago también se normaliza:
 *   pagado_afiliado → pagado_afiliado  (se mantiene, es el correcto)
 *   autorizado      → pendiente        (transitorio, no final)
 *   liquidado       → pendiente        (pendiente de pago al afiliado)
 *   rechazado       → rechazado        (se mantiene)
 *   pendiente       → pendiente        (se mantiene)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Normalizar campo `estado` ────────────────────────────────────────
        $map = [
            'pagado_afiliado' => 'pagada',
            'cerrado'         => 'pagada',
            'autorizado'      => 'liquidacion',
            'liquidado'       => 'liquidacion',
            'radicado'        => 'radicada',
            'en_tramite'      => 'radicada',
        ];

        foreach ($map as $viejo => $nuevo) {
            $n = DB::table('incapacidades')
                ->whereNull('deleted_at')
                ->where('estado', $viejo)
                ->update(['estado' => $nuevo]);

            if ($n > 0) {
                echo "   estado '$viejo' → '$nuevo': $n registros\n";
            }
        }

        // ── Normalizar campo `estado_pago` ───────────────────────────────────
        // Los valores autorizado/liquidado no son estados finales de pago
        DB::table('incapacidades')
            ->whereNull('deleted_at')
            ->whereIn('estado_pago', ['autorizado', 'liquidado'])
            ->update(['estado_pago' => 'pendiente']);

        // Si el estado es pagada, asegurar que estado_pago sea pagado_afiliado
        DB::table('incapacidades')
            ->whereNull('deleted_at')
            ->where('estado', 'pagada')
            ->where('estado_pago', '!=', 'pagado_afiliado')
            ->update(['estado_pago' => 'pagado_afiliado']);

        // ── Normalizar campo `estado_resultado` en gestiones_incapacidad ──────
        // Las gestiones también guardan el estado como resultado
        $gestMap = [
            'pagado_afiliado' => 'pagada',
            'cerrado'         => 'pagada',
            'autorizado'      => 'liquidacion',
            'liquidado'       => 'liquidacion',
            'radicado'        => 'radicada',
            'en_tramite'      => 'radicada',
        ];

        foreach ($gestMap as $viejo => $nuevo) {
            DB::table('gestiones_incapacidad')
                ->where('estado_resultado', $viejo)
                ->update(['estado_resultado' => $nuevo]);
        }
    }

    public function down(): void
    {
        // Reversión (referencial, no es crítica)
        $reverso = [
            'pagada'      => 'pagado_afiliado',
            'liquidacion' => 'liquidado',
            'radicada'    => 'radicado',
        ];

        foreach ($reverso as $nuevo => $viejo) {
            DB::table('incapacidades')
                ->whereNull('deleted_at')
                ->where('estado', $nuevo)
                ->update(['estado' => $viejo]);
        }
    }
};
