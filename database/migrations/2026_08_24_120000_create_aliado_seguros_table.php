<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de seguros por aliado.
 *
 * Hasta ahora el seguro era UN valor por aliado (`configuracion_aliado.seguro_valor`)
 * que el contrato copiaba en su campo `seguro`. Eso no alcanza cuando el aliado vende
 * varios: "Plan exequial 1" de $20.000, "Plan exequial 2" de $30.000, mascotas, vida…
 *
 * Cada aliado arma su propia lista y en el contrato se escoge cuál. El valor se copia
 * a `contratos.seguro` al guardar — igual que la administración: el catálogo es la
 * tarifa de hoy, el contrato guarda lo que se le cobra a esa persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliado_seguros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('nombre', 120);                  // "Plan exequial 2", "Seguro mascotas"
            $table->decimal('valor', 18, 2)->default(0);    // lo que se cobra al mes
            $table->string('descripcion', 500)->nullable(); // qué cubre, para el recibo
            $table->smallInteger('orden')->default(99);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->index(['aliado_id', 'activo'], 'aliado_seguros_aliado_activo_idx');
        });

        // El contrato guarda CUÁL seguro se vendió; el cuánto sigue en `seguro`, que ya
        // existe y ya lo suman la facturación, el cuadre diario y los informes.
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('seguro_id')->nullable()->after('seguro');
            // Sin ON DELETE: con `set null` SQL Server ve dos caminos de borrado hacia
            // contratos (por aliado y por aliado_seguros) y rechaza la constraint. Un
            // seguro con contratos encima no se borra, se marca inactivo.
            $table->foreign('seguro_id')->references('id')->on('aliado_seguros');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['seguro_id']);
            $table->dropColumn('seguro_id');
        });

        Schema::dropIfExists('aliado_seguros');
    }
};
