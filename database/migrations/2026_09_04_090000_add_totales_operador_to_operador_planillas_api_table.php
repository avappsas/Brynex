<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda lo que el operador dice de la planilla, para dejar de deducirlo.
 *
 * El soporte que se le entrega al cliente calcula hoy por su cuenta el número
 * de afiliados, los dos períodos y el desglose por entidad. El operador los
 * tiene, y son la verdad: es lo que quedó radicado. Los períodos sobre todo,
 * que se deducen sumando un mes al del plano y ya se han equivocado antes.
 *
 * Se guardan en vez de consultarse al generar cada PDF porque abrir sesión con
 * el operador tarda más de un minuto, y tras varias seguidas deja de responder.
 * Una consulta por planilla, y de ahí en adelante sale del disco.
 *
 * Lo que el API NO trae —comprobado contra su documentación oficial, donde el
 * `TotalPlanillaDTO` tiene quince campos y ninguno es este— es la fecha real de
 * pago y la referencia PIN. Esos dos solo viven en la pantalla del portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->string('nombre_aportante', 200)->nullable()->after('valor_total');
            $table->integer('numero_afiliados')->nullable()->after('nombre_aportante');
            // Vienen como YYYYMM; se guardan tal cual para no reinterpretarlos.
            $table->string('periodo_cotizacion', 6)->nullable()->after('numero_afiliados');
            $table->string('periodo_servicio', 6)->nullable()->after('periodo_cotizacion');
            $table->date('fecha_limite')->nullable()->after('periodo_servicio');
            // Desglose por EPS, AFP, ARL y caja con los códigos del operador.
            $table->text('total_administradoras')->nullable()->after('fecha_limite');
            // Cuándo se preguntó. Nulo = nunca, que es lo que busca el comando.
            $table->timestamp('totales_at')->nullable()->after('total_administradoras');
        });
    }

    public function down(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_aportante', 'numero_afiliados', 'periodo_cotizacion',
                'periodo_servicio', 'fecha_limite', 'total_administradoras', 'totales_at',
            ]);
        });
    }
};
