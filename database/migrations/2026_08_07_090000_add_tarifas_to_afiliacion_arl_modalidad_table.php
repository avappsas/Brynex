<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 0 del tarifario (ver docs/plan-tarifario-asesores.md).
     *
     * La tabla ya guardaba el costo de afiliación por (aliado, plan, modalidad, nivel ARL).
     * Ahora la misma celda guarda las otras tres piezas del tarifario, para no crear tablas
     * paralelas ni joins extra en una BD donde cada consulta cuesta ~250ms de red:
     *
     *   administracion → admon mensual TOTAL al cliente. Respaldo: configuracion_aliado.administracion
     *                    del plan → la global. NO lo lee el cotizador público, que sigue con la
     *                    genérica (CotizacionPublicaService::cotizar) — decisión explícita.
     *   retiro         → parte del costo de afiliación que va al retiro, en pesos. Respaldo: el
     *                    porcentaje actual configuracion_aliado.dist_retiro_pct.
     *   otros          → bolsa del aliado dentro de la afiliación (papelería, gestión, etc.), en
     *                    pesos. Respaldo: 0. Se muestra en el PDF del asesor y al facturar cae en
     *                    dist_admon.
     *
     * costo_afiliacion pasa a nullable: una celda puede existir solo para fijar la admon o el
     * retiro sin tocar el precio de afiliación, que entonces cae al del plan. Sin doctrine/dbal
     * en el proyecto, el cambio de nulabilidad va por SQL crudo de SQL Server.
     */
    public function up(): void
    {
        Schema::table('afiliacion_arl_modalidad', function (Blueprint $table) {
            $table->decimal('administracion', 12, 2)->nullable();
            $table->decimal('retiro', 12, 2)->nullable();
            $table->decimal('otros', 12, 2)->nullable();
        });

        DB::statement('ALTER TABLE afiliacion_arl_modalidad ALTER COLUMN costo_afiliacion DECIMAL(12,2) NULL');
    }

    public function down(): void
    {
        // Las filas creadas solo para admon/retiro/otros no tienen precio: se les pone 0 antes de
        // volver a NOT NULL, si no el ALTER falla.
        DB::statement('UPDATE afiliacion_arl_modalidad SET costo_afiliacion = 0 WHERE costo_afiliacion IS NULL');
        DB::statement('ALTER TABLE afiliacion_arl_modalidad ALTER COLUMN costo_afiliacion DECIMAL(12,2) NOT NULL');

        Schema::table('afiliacion_arl_modalidad', function (Blueprint $table) {
            $table->dropColumn(['administracion', 'retiro', 'otros']);
        });
    }
};
