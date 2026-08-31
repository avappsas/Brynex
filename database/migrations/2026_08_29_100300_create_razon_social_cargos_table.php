<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de cargos por razón social, cada uno con su nivel de riesgo.
 *
 * Es el punto de entrada del flujo: al elegir el cargo en el contrato queda
 * resuelto también el `n_arl` y, por él, el centro de trabajo en
 * [[arl_centros_trabajo]]. Los cargos dependen de la actividad económica de la
 * razón social, por eso cuelgan de ella y no del aliado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razon_social_cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('razon_social_id'); // razones_sociales.id es int (legacy)

            $table->string('cargo', 150);
            $table->unsignedTinyInteger('nivel_riesgo');
            $table->boolean('por_defecto')->default(false); // el que se sugiere para ese riesgo
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales');

            $table->index(['razon_social_id', 'activo'], 'ix_rs_cargos_rs_activo');
            $table->index(['razon_social_id', 'nivel_riesgo'], 'ix_rs_cargos_rs_riesgo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razon_social_cargos');
    }
};
