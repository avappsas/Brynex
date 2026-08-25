<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo por línea del desglose: cada ítem guarda lo que costó además de lo
     * que se cobra ("disco duro 1TB: gasté 200.000, cobro 250.000"), para poder
     * ver la utilidad real del trabajo.
     *
     * El egreso se materializa como un gasto normal (`tipo_movimiento = 'gasto'`)
     * enlazado al trabajo por `cc_trabajo_id`. Se hace así a propósito y no con
     * un `tipo_movimiento` propio: un tipo nuevo obliga a darlo de alta en las
     * seis listas blancas que suman caja y gasto del mes, y olvidar una sola
     * descuadra las cuentas en silencio. Con la columna de enlace el dinero sale
     * por el camino de siempre y los costos siguen siendo filtrables.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_cc_trabajo_items', function (Blueprint $table) {
            $table->decimal('costo_unitario', 18, 2)->default(0)->after('valor_unitario');
        });

        Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('cc_trabajo_id')->nullable()->after('patrimonio_id');
            $table->index('cc_trabajo_id', 'ix_gasto_cc_trabajo');
        });

        $this->convertirCostosDeMaterialesViejos();
    }

    /**
     * El formulario anterior tenía un campo suelto "¿Cuánto te costaron los
     * materiales?" que creaba un gasto sin vínculo con su trabajo. Se enlaza al
     * trabajo y su valor se pasa al primer ítem del desglose, para que el gasto
     * siga cuadrando con la suma de costos que ahora lo gobierna.
     */
    private function convertirCostosDeMaterialesViejos(): void
    {
        $db = DB::connection('finanzas');

        $gastos = $db->table('finanzas_gastos')
            ->whereNull('cc_trabajo_id')
            ->where('descripcion', 'like', 'Materiales/costo del trabajo: %')
            ->get(['id', 'user_id', 'monto', 'descripcion']);

        foreach ($gastos as $gasto) {
            // "Materiales/costo del trabajo: {descripcion} ({nombre_deudor})"
            if (! preg_match('/^Materiales\/costo del trabajo: (.+) \(([^)]*)\)$/u', $gasto->descripcion, $m)) {
                continue;
            }

            $trabajo = $db->table('finanzas_prestamos')
                ->where('user_id', $gasto->user_id)
                ->where('es_cuenta_corriente', 1)
                ->where('descripcion', trim($m[1]))
                ->where('nombre_deudor', trim($m[2]))
                ->orderByDesc('id')
                ->first(['id']);

            if (! $trabajo) {
                continue;
            }

            $primerItem = $db->table('finanzas_cc_trabajo_items')
                ->where('prestamo_id', $trabajo->id)
                ->orderBy('orden')
                ->orderBy('id')
                ->first(['id', 'cantidad']);

            if ($primerItem && $primerItem->cantidad > 0) {
                $db->table('finanzas_cc_trabajo_items')
                    ->where('id', $primerItem->id)
                    ->update(['costo_unitario' => round($gasto->monto / $primerItem->cantidad, 2)]);
            }

            $db->table('finanzas_gastos')->where('id', $gasto->id)->update([
                'cc_trabajo_id' => $trabajo->id,
                'descripcion' => 'Costos del trabajo: '.trim($m[1]).' ('.trim($m[2]).')',
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
            $table->dropIndex('ix_gasto_cc_trabajo');
            $table->dropColumn('cc_trabajo_id');
        });

        Schema::connection('finanzas')->table('finanzas_cc_trabajo_items', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
