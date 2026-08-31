<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros de trabajo que cada razón social tiene creados en ARL Sura.
 *
 * Cada una los nombró a su manera —BRYGAR usa `000RIESGO1`, `000RIESGO3` y
 * `0000000001`, otra puede tener "SEDE PRINCIPAL"— así que no hay convención que
 * deducir: el afiliar exige el `cdSucursal` exacto de esa póliza. La tabla se
 * llena con `arl:sincronizar-centros`, que los trae de
 * POST /sel-services/portal/sucursalAfiliaciones/centrosDeTrabajo; no se digitan.
 *
 * `nivel_riesgo` es lo que permite ir de `contratos.n_arl` al centro correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arl_centros_trabajo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('razon_social_id'); // razones_sociales.id es int (legacy)

            $table->string('codigo_centro', 20);           // cdSucursal
            $table->string('nombre_centro', 200)->nullable(); // dsSucursal
            $table->unsignedTinyInteger('nivel_riesgo');   // cdClase (1 a 5)
            $table->decimal('tasa', 8, 3)->nullable();     // poCotizacion

            $table->string('cd_actividad', 15)->nullable();
            $table->string('municipio_sura', 10)->nullable();  // cdMunicipio interno de Sura
            $table->string('departamento', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales');

            $table->unique(['razon_social_id', 'codigo_centro'], 'uq_arl_centro_rs_codigo');
            $table->index(['razon_social_id', 'nivel_riesgo', 'activo'], 'ix_arl_centro_rs_riesgo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arl_centros_trabajo');
    }
};
