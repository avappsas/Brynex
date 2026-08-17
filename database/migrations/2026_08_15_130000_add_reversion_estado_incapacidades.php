<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reversión de un cambio de estado de incapacidad.
 *
 * Para deshacer un estado hay que saber DOS cosas que hasta hoy no se guardaban:
 *
 * 1. A qué estado volver → `gestiones_incapacidad.estado_anterior`.
 * 2. Qué movimientos de plata generó esa gestión. Los abonos y las
 *    consignaciones se podían rastrear por `incapacidad_id`, pero los gastos
 *    solo por el "#4547" incrustado en el texto de la descripción — frágil y
 *    ambiguo si la misma incapacidad se paga dos veces. Se agrega el vínculo
 *    real (`incapacidad_id` + `gestion_incapacidad_id`) y se rellena el
 *    histórico parseando ese "#" una única vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            // Estado que tenía la incapacidad ANTES de esta gestión.
            $table->string('estado_anterior', 40)->nullable()->after('estado_nuevo');
            // La gestión que deshace a otra: no se puede reversar (sería un
            // "rehacer" que no reconstruye los gastos borrados).
            $table->boolean('es_reversion')->default(false)->after('estado_anterior');
            // Marcas de la gestión que YA fue reversada.
            $table->dateTime('revertida_at')->nullable()->after('es_reversion');
            $table->unsignedBigInteger('revertida_por')->nullable()->after('revertida_at');
            $table->string('revertida_motivo', 500)->nullable()->after('revertida_por');
        });

        Schema::table('abonos_incapacidades', function (Blueprint $table) {
            $table->unsignedBigInteger('gestion_incapacidad_id')->nullable()->after('incapacidad_id');
            $table->index('gestion_incapacidad_id', 'idx_abonos_incap_gestion');
        });

        Schema::table('consignaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('gestion_incapacidad_id')->nullable()->after('incapacidad_id');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('incapacidad_id')->nullable()->after('numero_planilla');
            $table->unsignedBigInteger('gestion_incapacidad_id')->nullable()->after('incapacidad_id');
            $table->index('incapacidad_id', 'idx_gastos_incapacidad');
        });

        $this->rellenarIncapacidadIdEnGastos();
    }

    /**
     * Rellena `gastos.incapacidad_id` leyendo el "#123" de la descripción.
     *
     * Solo toca los cuatro tipos de gasto que nacen de una incapacidad, así que
     * un "#" en la descripción de un gasto de oficina no se confunde. La
     * `gestion_incapacidad_id` histórica NO se puede deducir (varias gestiones
     * de la misma incapacidad darían el mismo texto), y por eso la reversión
     * tiene un camino de respaldo por tipo + fecha + valor.
     */
    private function rellenarIncapacidadIdEnGastos(): void
    {
        DB::table('gastos')
            ->whereIn('tipo', \App\Models\Gasto::TIPOS_INCAPACIDAD)
            ->whereNull('incapacidad_id')
            ->orderBy('id')
            ->chunkById(500, function ($gastos) {
                foreach ($gastos as $g) {
                    if (! preg_match('/#(\d+)/', (string) $g->descripcion, $m)) {
                        continue;
                    }
                    DB::table('gastos')->where('id', $g->id)->update(['incapacidad_id' => (int) $m[1]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('gestiones_incapacidad', function (Blueprint $table) {
            $table->dropColumn(['estado_anterior', 'es_reversion', 'revertida_at', 'revertida_por', 'revertida_motivo']);
        });

        Schema::table('abonos_incapacidades', function (Blueprint $table) {
            $table->dropIndex('idx_abonos_incap_gestion');
            $table->dropColumn('gestion_incapacidad_id');
        });

        Schema::table('consignaciones', function (Blueprint $table) {
            $table->dropColumn('gestion_incapacidad_id');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->dropIndex('idx_gastos_incapacidad');
            $table->dropColumn(['incapacidad_id', 'gestion_incapacidad_id']);
        });
    }
};
