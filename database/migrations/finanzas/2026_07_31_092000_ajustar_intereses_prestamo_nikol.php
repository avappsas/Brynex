<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection('finanzas')->transaction(function () {
            // Actualizar saldo del préstamo
            DB::connection('finanzas')->table('finanzas_prestamos')
                ->where('id', 20305)
                ->update([
                    'saldo_actual' => 2548672.00,
                    'ultimo_corte' => '2026-08-31',
                ]);

            // Actualizar cada movimiento
            $movimientos = [
                24810 => ['monto' => 67581.00, 'saldo_antes' => 1351615.00, 'saldo_despues' => 1419196.00, 'observacion' => 'Interés compuesto Agosto 2025 (5%).'],
                24811 => ['monto' => 70960.00, 'saldo_antes' => 1419196.00, 'saldo_despues' => 1490156.00, 'observacion' => 'Interés compuesto Septiembre 2025 (5%).'],
                24812 => ['monto' => 74507.00, 'saldo_antes' => 1490156.00, 'saldo_despues' => 1564663.00, 'observacion' => 'Interés compuesto Octubre 2025 (5%).'],
                24813 => ['monto' => 78233.00, 'saldo_antes' => 1564663.00, 'saldo_despues' => 1642896.00, 'observacion' => 'Interés compuesto Noviembre 2025 (5%).'],
                24814 => ['monto' => 82145.00, 'saldo_antes' => 1642896.00, 'saldo_despues' => 1725041.00, 'observacion' => 'Interés compuesto Diciembre 2025 (5%).'],
                24815 => ['monto' => 86252.00, 'saldo_antes' => 1725041.00, 'saldo_despues' => 1811293.00, 'observacion' => 'Interés compuesto Enero 2026 (5%).'],
                24816 => ['monto' => 90565.00, 'saldo_antes' => 1811293.00, 'saldo_despues' => 1901858.00, 'observacion' => 'Interés compuesto Febrero 2026 (5%).'],
                24817 => ['monto' => 95093.00, 'saldo_antes' => 1901858.00, 'saldo_despues' => 1996951.00, 'observacion' => 'Interés compuesto Marzo 2026 (5%).'],
                24818 => ['monto' => 99847.00, 'saldo_antes' => 1996951.00, 'saldo_despues' => 2096798.00, 'observacion' => 'Interés compuesto Abril 2026 (5%).'],
                24819 => ['monto' => 104840.00, 'saldo_antes' => 2096798.00, 'saldo_despues' => 2201638.00, 'observacion' => 'Interés compuesto Mayo 2026 (5%).'],
                24820 => ['monto' => 110082.00, 'saldo_antes' => 2201638.00, 'saldo_despues' => 2311720.00, 'observacion' => 'Interés compuesto Junio 2026 (5%).'],
                24821 => ['monto' => 115586.00, 'saldo_antes' => 2311720.00, 'saldo_despues' => 2427306.00, 'observacion' => 'Interés compuesto Julio 2026 (5%).'],
                24822 => ['monto' => 121366.00, 'saldo_antes' => 2427306.00, 'saldo_despues' => 2548672.00, 'observacion' => 'Interés compuesto Agosto 2026 (5%).'],
            ];

            foreach ($movimientos as $id => $data) {
                DB::connection('finanzas')->table('finanzas_prestamo_movimientos')
                    ->where('id', $id)
                    ->update($data);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('finanzas')->transaction(function () {
            // Revertir saldo del préstamo
            DB::connection('finanzas')->table('finanzas_prestamos')
                ->where('id', 20305)
                ->update([
                    'saldo_actual' => 4666144.00,
                    'ultimo_corte' => '2026-08-31',
                ]);

            // Revertir cada movimiento
            $movimientos = [
                24810 => ['monto' => 135162.00, 'saldo_antes' => 1351615.00, 'saldo_despues' => 1486777.00, 'observacion' => 'Interés compuesto Agosto 2025 (10%).'],
                24811 => ['monto' => 148678.00, 'saldo_antes' => 1486777.00, 'saldo_despues' => 1635455.00, 'observacion' => 'Interés compuesto Septiembre 2025 (10%).'],
                24812 => ['monto' => 163545.00, 'saldo_antes' => 1635455.00, 'saldo_despues' => 1799000.00, 'observacion' => 'Interés compuesto Octubre 2025 (10%).'],
                24813 => ['monto' => 179900.00, 'saldo_antes' => 1799000.00, 'saldo_despues' => 1978900.00, 'observacion' => 'Interés compuesto Noviembre 2025 (10%).'],
                24814 => ['monto' => 197890.00, 'saldo_antes' => 1978900.00, 'saldo_despues' => 2176790.00, 'observacion' => 'Interés compuesto Diciembre 2025 (10%).'],
                24815 => ['monto' => 217679.00, 'saldo_antes' => 2176790.00, 'saldo_despues' => 2394469.00, 'observacion' => 'Interés compuesto Enero 2026 (10%).'],
                24816 => ['monto' => 239447.00, 'saldo_antes' => 2394469.00, 'saldo_despues' => 2633916.00, 'observacion' => 'Interés compuesto Febrero 2026 (10%).'],
                24817 => ['monto' => 263392.00, 'saldo_antes' => 2633916.00, 'saldo_despues' => 2897308.00, 'observacion' => 'Interés compuesto Marzo 2026 (10%).'],
                24818 => ['monto' => 289731.00, 'saldo_antes' => 2897308.00, 'saldo_despues' => 3187039.00, 'observacion' => 'Interés compuesto Abril 2026 (10%).'],
                24819 => ['monto' => 318704.00, 'saldo_antes' => 3187039.00, 'saldo_despues' => 3505743.00, 'observacion' => 'Interés compuesto Mayo 2026 (10%).'],
                24820 => ['monto' => 350574.00, 'saldo_antes' => 3505743.00, 'saldo_despues' => 3856317.00, 'observacion' => 'Interés compuesto Junio 2026 (10%).'],
                24821 => ['monto' => 385632.00, 'saldo_antes' => 3856317.00, 'saldo_despues' => 4241949.00, 'observacion' => 'Interés compuesto Julio 2026 (10%).'],
                24822 => ['monto' => 424195.00, 'saldo_antes' => 4241949.00, 'saldo_despues' => 4666144.00, 'observacion' => 'Interés compuesto Agosto 2026 (10%).'],
            ];

            foreach ($movimientos as $id => $data) {
                DB::connection('finanzas')->table('finanzas_prestamo_movimientos')
                    ->where('id', $id)
                    ->update($data);
            }
        });
    }
};
