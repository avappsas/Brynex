<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye la tabla 'contratos' con FKs normalizadas y campos nuevos.
 *
 * NOTA: Esta migración elimina y recrea la tabla contratos.
 * Los datos ya migrados desde legacy se deben volver a importar después
 * ejecutando: php artisan db:seed --class=LegacyMigrationSeeder
 *
 * Razón del enfoque DROP+CREATE:
 * La versión anterior no tenía FKs correctas y tenía campos no normalizados.
 * Se hace antes de datos de producción reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eliminar tabla vieja (incluye FK razon_social_id)
        Schema::dropIfExists('contratos');

        Schema::create('contratos', function (Blueprint $table) {
            // ── Identificadores ──
            $table->unsignedInteger('id')->primary(); // Mantener ID original Access
            $table->unsignedBigInteger('aliado_id')->nullable()->index();
            $table->bigInteger('cedula')->index();

            // ── Estado ──
            $table->string('estado', 20)->default('vigente'); // vigente | retirado

            // ── Razón Social (bloqueada después de crear) ──
            $table->unsignedInteger('razon_social_id')->nullable();
            $table->boolean('razon_social_bloqueada')->default(false); // Solo admin puede editar

            // ── Plan y Modalidad ──
            $table->unsignedTinyInteger('plan_id')->nullable();            // FK planes_contrato
            $table->unsignedTinyInteger('tipo_modalidad_id')->nullable();  // FK tipo_modalidad

            // ── Entidades del contrato ──
            $table->unsignedBigInteger('eps_id')->nullable();       // FK eps
            $table->unsignedBigInteger('pension_id')->nullable();   // FK pensiones
            $table->unsignedBigInteger('arl_id')->nullable();       // FK arls
            $table->tinyInteger('n_arl')->unsigned()->nullable();   // Nivel riesgo ARL 1-5
            $table->string('arl_modo', 20)->nullable();             // 'razon_social' | 'independiente' (solo para independientes)
            $table->unsignedBigInteger('caja_id')->nullable();      // FK cajas

            // ── Información laboral ──
            $table->string('cargo', 255)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->unsignedBigInteger('actividad_economica_id')->nullable(); // FK actividades_economicas

            // ── Cotización / Salarios ──
            $table->decimal('salario', 18, 2)->nullable();          // Salario mensual
            $table->decimal('ibc', 18, 2)->nullable();              // Ingreso Base Cotización (=salario; independientes editable)
            $table->decimal('porcentaje_caja', 5, 2)->nullable();   // 2.00 ó 0.60 para independientes

            // ── Tarifas del contrato (heredadas de configuracion_aliado, editables) ──
            $table->decimal('administracion', 10, 2)->default(0);
            $table->decimal('admon_asesor', 10, 2)->default(0);     // Comisión asesor que trajo el cliente
            $table->decimal('costo_afiliacion', 10, 2)->default(0);
            $table->decimal('seguro', 10, 2)->default(0);           // Valor seguro adicional

            // ── Personas relacionadas ──
            $table->unsignedBigInteger('asesor_id')->nullable();       // FK asesores (quien consiguió el cliente)
            $table->unsignedBigInteger('encargado_id')->nullable();    // FK users (trabajador interno que hace la afiliación)

            // ── Motivos (listas desplegables) ──
            $table->unsignedTinyInteger('motivo_afiliacion_id')->nullable(); // FK motivos_afiliacion
            $table->unsignedTinyInteger('motivo_retiro_id')->nullable();     // FK motivos_retiro
            $table->date('fecha_arl')->nullable();

            // ── Planilla / Envío ──
            $table->string('envio_planilla', 55)->nullable();     // Medio de envío planilla
            $table->string('fecha_probable_pago', 255)->nullable();
            $table->string('modo_probable_pago', 255)->nullable();

            // ── Observaciones ──
            $table->text('observacion')->nullable();
            $table->text('observacion_afiliacion')->nullable();
            $table->text('observacion_llamada')->nullable();
            $table->string('np', 255)->nullable(); // Número de planilla u otro identificador

            // ── Trazabilidad ──
            $table->datetime('fecha_created')->nullable(); // Fecha original Access
            $table->timestamps();

            // ── Foreign Keys ──
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('planes_contrato')->nullOnDelete();
            $table->foreign('tipo_modalidad_id')->references('id')->on('tipo_modalidad')->nullOnDelete();
            $table->foreign('eps_id')->references('id')->on('eps')->nullOnDelete();
            $table->foreign('pension_id')->references('id')->on('pensiones')->nullOnDelete();
            $table->foreign('arl_id')->references('id')->on('arls')->nullOnDelete();
            $table->foreign('caja_id')->references('id')->on('cajas')->nullOnDelete();
            $table->foreign('actividad_economica_id')->references('id')->on('actividades_economicas')->nullOnDelete();
            $table->foreign('asesor_id')->references('id')->on('asesores')->noActionOnDelete();
            $table->foreign('encargado_id')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('motivo_afiliacion_id')->references('id')->on('motivos_afiliacion')->nullOnDelete();
            $table->foreign('motivo_retiro_id')->references('id')->on('motivos_retiro')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
