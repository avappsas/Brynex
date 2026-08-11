<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriz PROPIA del asesor: la copia editable de la plantilla del nivel.
     * Ver docs/plan-tarifario-asesores.md.
     *
     * Al asignarle un nivel a un asesor se copian aquí las filas de asesor_nivel_tarifas.
     * Desde ese momento son libres: editar una celda del asesor NO toca el nivel, y editar el
     * nivel NO re-aplica sobre los asesores ya configurados (se re-copia solo si se reasigna
     * el nivel a mano).
     *
     * Un asesor sin fila para una combinación cae a la plantilla de su nivel, y si tampoco,
     * a su comisión plana de siempre (asesores.comision_afil_*). Por eso los ~70 asesores
     * actuales siguen funcionando igual mientras no se les asigne nivel.
     *
     * aliado_id está denormalizado a propósito: todas las consultas del panel filtran por
     * aliado activo y así se evita un join contra asesores en cada lectura de la matriz.
     */
    public function up(): void
    {
        Schema::create('asesor_tarifas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('asesor_id');
            $table->unsignedTinyInteger('plan_id');
            $table->integer('tipo_modalidad_id');
            $table->unsignedTinyInteger('nivel_arl');

            $table->decimal('afil_asesor', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('asesor_id')->references('id')->on('asesores')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('planes_contrato');

            $table->unique(
                ['asesor_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl'],
                'asesor_tarifa_unica'
            );
        });

        Schema::table('asesores', function (Blueprint $table) {
            $table->unsignedBigInteger('nivel_id')->nullable();
            $table->foreign('nivel_id')->references('id')->on('asesor_niveles');
        });
    }

    public function down(): void
    {
        Schema::table('asesores', function (Blueprint $table) {
            $table->dropForeign(['nivel_id']);
            $table->dropColumn('nivel_id');
        });

        Schema::dropIfExists('asesor_tarifas');
    }
};
