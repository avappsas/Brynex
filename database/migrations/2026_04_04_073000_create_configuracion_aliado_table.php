<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de tarifas de afiliación por aliado y plan.
     * El contrato hereda estos valores al crearse pero puede sobrescribirlos.
     *
     * Lógica admon_asesor:
     * - El aliado define administración base.
     * - Si el contrato tiene asesor, se descuenta admon_asesor de la administración.
     * - Ej: admon=46.000, asesor gana 6.000 → admon_contrato=40.000, admon_asesor=6.000
     */
    public function up(): void
    {
        // ── Tabla de tarifas ARL por nivel de riesgo ──
        // Los porcentajes de ARL dependen del nivel 1-5 y los define la ARL.
        // El aliado puede configurar tarifas personalizadas por nivel.
        Schema::create('arl_tarifas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable(); // null = tarifa global del sistema
            $table->tinyInteger('nivel')->unsigned(); // 1 al 5
            $table->decimal('porcentaje', 6, 4);     // ej: 0.5220, 1.0440, 2.4360, 4.3500, 6.9600
            $table->string('descripcion', 100)->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->unique(['aliado_id', 'nivel'], 'arl_tarifas_aliado_nivel_unique');
        });

        // Tarifas globales ARL (valores estándar Colombia 2025)
        $now = now();
        \Illuminate\Support\Facades\DB::table('arl_tarifas')->insert([
            ['aliado_id' => null, 'nivel' => 1, 'porcentaje' => 0.5220, 'descripcion' => 'Riesgo Mínimo – Oficinas, Domésticas, Docentes',           'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nivel' => 2, 'porcentaje' => 1.0440, 'descripcion' => 'Riesgo Bajo – Meseros, Asesores, Vendedores ext.',          'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nivel' => 3, 'porcentaje' => 2.4360, 'descripcion' => 'Riesgo Medio – Ebanistas, Médicos, Enfermeras',             'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nivel' => 4, 'porcentaje' => 4.3500, 'descripcion' => 'Riesgo Alto – Taxistas, Conductores, Soldadores',           'created_at' => $now, 'updated_at' => $now],
            ['aliado_id' => null, 'nivel' => 5, 'porcentaje' => 6.9600, 'descripcion' => 'Riesgo Máximo – Constructores, Mineros, Obras civiles',     'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Configuración de tarifas por aliado (y opcionalmente por plan) ──
        Schema::create('configuracion_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedTinyInteger('plan_id')->nullable(); // null = aplica a todos los planes

            // Valores que se precargan en el formulario del contrato
            $table->decimal('administracion', 10, 2)->default(0);       // Tarifa administración base
            $table->decimal('costo_afiliacion', 10, 2)->default(0);     // Costo de afiliación (1er mes)
            $table->decimal('admon_asesor', 10, 2)->default(0);         // Comisión mensual del asesor
            $table->decimal('seguro_valor', 10, 2)->default(0);         // Valor seguro adicional
            $table->unsignedBigInteger('encargado_default_id')->nullable(); // user_id encargado de afiliación por defecto
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('planes_contrato')->nullOnDelete();
            $table->foreign('encargado_default_id')->references('id')->on('users')->noActionOnDelete();

            // Combinación única aliado + plan
            $table->unique(['aliado_id', 'plan_id'], 'cfg_aliado_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_aliado');
        Schema::dropIfExists('arl_tarifas');
    }
};
