<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada trabajo de cuenta corriente sigue siendo una fila de `finanzas_prestamos`
     * (para reusar la máquina de liquidación, movimientos y abonos), pero ahora
     * cuelga de un cliente y puede marcarse como exento de interés.
     *
     * El backfill convierte los registros de cuenta corriente que ya existen:
     * crea un cliente por cada `nombre_deudor` distinto y le engancha sus trabajos.
     * `cuenta_corriente_grupo` se conserva intacto por si hay que auditar el antes.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->unsignedBigInteger('cc_cliente_id')->nullable()->after('cuenta_corriente_grupo');
            $table->boolean('sin_interes')->default(false)->after('cc_cliente_id');
            $table->index('cc_cliente_id', 'ix_prestamo_cc_cliente');
        });

        $db = DB::connection('finanzas');

        $huerfanos = $db->table('finanzas_prestamos')
            ->where('es_cuenta_corriente', 1)
            ->whereNull('cc_cliente_id')
            ->get(['id', 'user_id', 'nombre_deudor', 'cedula_deudor', 'telefono_deudor', 'tasa_interes_mensual', 'dias_mora_alerta']);

        foreach ($huerfanos->groupBy(fn ($p) => $p->user_id.'|'.trim($p->nombre_deudor)) as $trabajos) {
            $base = $trabajos->first();

            $clienteId = $db->table('finanzas_cc_clientes')
                ->where('user_id', $base->user_id)
                ->where('nombre', trim($base->nombre_deudor))
                ->value('id');

            if (! $clienteId) {
                $clienteId = $db->table('finanzas_cc_clientes')->insertGetId([
                    'user_id' => $base->user_id,
                    'nombre' => trim($base->nombre_deudor),
                    'cedula' => $base->cedula_deudor,
                    'telefono' => $base->telefono_deudor,
                    'tasa_interes_mensual' => $base->tasa_interes_mensual,
                    'dias_mora_alerta' => $base->dias_mora_alerta ?: 30,
                    'alertas_activas' => 1,
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $db->table('finanzas_prestamos')
                ->whereIn('id', $trabajos->pluck('id'))
                ->update(['cc_cliente_id' => $clienteId]);
        }
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->dropIndex('ix_prestamo_cc_cliente');
            $table->dropColumn(['cc_cliente_id', 'sin_interes']);
        });
    }
};
