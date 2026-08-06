<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una liquidación se identificaba por (aliado, razón social, operador, año,
 * mes, n_plano). Faltaba el filtro de modalidades, y sin él dos planillas
 * legítimamente distintas de la misma tanda colisionan en la misma fila.
 *
 * El caso real: la planilla K no puede ir en el mismo pago que la de los
 * dependientes E. El usuario filtra por K en /admin/planos y liquida; el
 * archivo que se envía a Enlace sí sale bien (una sola persona), pero el
 * `updateOrCreate` pisa el registro del lote de los E y se pierde el número de
 * la planilla anterior, que podía estar liquidada y sin pagar. De paso, el
 * modal mostraba el número y el valor de la planilla equivocada.
 *
 * Se guarda el filtro normalizado: ids únicos, ordenados y unidos por coma;
 * cadena vacía cuando no hay filtro (todas las modalidades). Ver
 * PlanillaApiController::filtroModalidades().
 *
 * Las filas anteriores quedan en NULL y se leen como '' (sin filtro), que es
 * lo más cercano a la verdad para ellas: las lecturas usan
 * ISNULL(tipos_modalidad, '').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->string('tipos_modalidad', 120)->nullable()->after('n_plano');
        });

        // El índice acompaña la búsqueda de estado(), que es por tanda + filtro.
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->index(
                ['aliado_id', 'razon_social_id', 'anio', 'mes', 'n_plano'],
                'idx_opa_tanda'
            );
        });
    }

    public function down(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->dropIndex('idx_opa_tanda');
            $table->dropColumn('tipos_modalidad');
        });
    }
};
