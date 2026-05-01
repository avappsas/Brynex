<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convertir IDs manuales a IDENTITY auto-increment en:
 *  - clientes          (id manual → id IDENTITY, cedula bigInt indexada)
 *  - razones_sociales  (id manual → id IDENTITY, nit bigInt indexado)
 *  - empresas          (id manual → id IDENTITY, nit bigInt indexado)
 *
 * Seguro: todas las tablas están vacías tras el reset de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Deshabilitar FKs que apunten a estas tablas ──────────────────────────
        DB::statement('EXEC sp_MSforeachtable \'ALTER TABLE ? NOCHECK CONSTRAINT ALL\'');

        // ════════════════════════════════════════════════════════
        // 1. CLIENTES  → id IDENTITY + cedula bigInteger indexada
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('clientes', 'U') IS NOT NULL DROP TABLE clientes");

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();                                    // IDENTITY auto-increment
            $table->bigInteger('cedula')->index();           // número doc (bigInt)
            $table->integer('id_legacy')->nullable()->index(); // trazabilidad
            $table->integer('cod_empresa')->nullable();
            $table->string('tipo_doc', 10)->nullable();
            $table->string('primer_nombre', 55)->nullable();
            $table->string('segundo_nombre', 55)->nullable();
            $table->string('primer_apellido', 55)->nullable();
            $table->string('segundo_apellido', 55)->nullable();
            $table->string('genero', 10)->nullable();
            $table->string('sisben', 50)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_expedicion')->nullable();
            $table->string('rh', 10)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->bigInteger('celular')->nullable();
            $table->string('correo', 100)->nullable();
            $table->unsignedSmallInteger('departamento_id')->nullable();
            $table->unsignedInteger('municipio_id')->nullable();
            $table->string('direccion_vivienda', 150)->nullable();
            $table->string('direccion_cobro', 150)->nullable();
            $table->string('barrio', 80)->nullable();
            $table->unsignedBigInteger('eps_id')->nullable();
            $table->unsignedBigInteger('pension_id')->nullable();
            $table->string('ips', 100)->nullable();
            $table->string('urgencias', 100)->nullable();
            $table->string('iva', 20)->nullable();
            $table->string('ocupacion', 80)->nullable();
            $table->string('referido', 80)->nullable();
            $table->text('observacion')->nullable();
            $table->text('observacion_llamada')->nullable();
            $table->string('claves', 255)->nullable();
            $table->string('datos', 255)->nullable();
            $table->integer('deuda')->nullable();
            $table->string('fecha_probable_pago', 50)->nullable();
            $table->string('modo_probable_pago', 50)->nullable();
            $table->timestamps();

            $table->foreign('departamento_id')->references('id')->on('departamentos')->nullOnDelete();
            $table->foreign('municipio_id')->references('id')->on('ciudades')->nullOnDelete();
            $table->foreign('eps_id')->references('id')->on('eps')->nullOnDelete();
            $table->foreign('pension_id')->references('id')->on('pensiones')->nullOnDelete();
        });

        // ════════════════════════════════════════════════════════
        // 2. RAZONES SOCIALES → id IDENTITY + nit bigInteger
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('razones_sociales', 'U') IS NOT NULL DROP TABLE razones_sociales");

        Schema::create('razones_sociales', function (Blueprint $table) {
            $table->id();                                     // IDENTITY auto-increment
            $table->bigInteger('nit')->nullable()->index();   // NIT real de la empresa
            $table->integer('dv')->nullable();
            $table->integer('id_legacy')->nullable()->index(); // legacy COD_RAZON_SOC
            $table->unsignedBigInteger('aliado_id')->nullable()->index();
            $table->string('razon_social', 255)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('plan', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefonos', 255)->nullable();
            $table->string('correos', 255)->nullable();
            $table->string('actividad_economica', 255)->nullable();
            $table->string('objeto_social', 255)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->decimal('salario_minimo', 18, 2)->nullable();
            $table->bigInteger('arl_nit')->nullable();
            $table->bigInteger('caja_nit')->nullable();
            $table->integer('mes_pagos')->nullable();
            $table->integer('anio_pagos')->nullable();
            $table->integer('n_plano')->nullable();
            $table->datetime('fecha_constitucion')->nullable();
            $table->datetime('fecha_limite_pago')->nullable();
            $table->integer('dia_habil')->nullable();
            $table->string('forma_presentacion', 50)->nullable();
            $table->string('codigo_sucursal', 50)->nullable();
            $table->string('nombre_sucursal', 100)->nullable();
            $table->string('notas_factura1', 255)->nullable();
            $table->string('notas_factura2', 255)->nullable();
            $table->string('dir_formulario', 100)->nullable();
            $table->string('tel_formulario', 20)->nullable();
            $table->string('correo_formulario', 100)->nullable();
            $table->bigInteger('cedula_rep')->nullable();
            $table->string('nombre_rep', 100)->nullable();
            $table->boolean('es_independiente')->default(false);
            $table->unsignedBigInteger('encargado_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
        });

        // ════════════════════════════════════════════════════════
        // 3. EMPRESAS → id IDENTITY + nit bigInteger
        // ════════════════════════════════════════════════════════
        DB::statement("IF OBJECT_ID('empresas', 'U') IS NOT NULL DROP TABLE empresas");

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();                                     // IDENTITY auto-increment
            $table->integer('id_legacy')->nullable()->index(); // ID original de legacy
            $table->bigInteger('nit')->nullable()->index();
            $table->string('empresa', 255)->nullable();
            $table->string('contacto', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('celular', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('cliente_de', 255)->nullable();
            $table->string('tipo_facturacion', 30)->nullable();
            $table->string('iva', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('actividad_economica', 1000)->nullable();
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->unsignedBigInteger('asesor_id')->nullable();
            $table->unsignedBigInteger('encargado_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
        });

        // ── Re-habilitar FKs ─────────────────────────────────────────────────────
        DB::statement('EXEC sp_MSforeachtable \'ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('razones_sociales');
        Schema::dropIfExists('empresas');
    }
};
