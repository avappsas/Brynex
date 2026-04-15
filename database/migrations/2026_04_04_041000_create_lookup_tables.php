<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── EPS ──
        Schema::create('eps', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nit')->nullable()->index();
            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 255)->nullable();        // N_EPS
            $table->string('razon_social', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nombre_aportes', 255)->nullable(); // N_EPS_aportes
            $table->string('nombre_asopagos', 255)->nullable();
        });

        // ── PENSIONES ──
        Schema::create('pensiones', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nit')->nullable()->index();
            $table->string('codigo', 50)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nombre_asopagos', 255)->nullable();
        });

        // ── ARL ──
        Schema::create('arls', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nit')->nullable()->index();
            $table->string('codigo', 50)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nombre_arl', 255)->nullable();     // ARL col
            $table->string('nombre_asopagos', 255)->nullable();
        });

        // ── CAJAS DE COMPENSACIÓN ──
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('nit')->nullable()->index();
            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 255)->nullable();         // N_CAJA
            $table->string('razon_social', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('ciudad', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nombre_asopagos', 255)->nullable();
        });

        // ── RAZONES SOCIALES (Empresas) ──
        Schema::create('razones_sociales', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // Mantener ID original
            $table->integer('dv')->nullable();        // Dígito verificación NIT
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
            $table->bigInteger('arl_nit')->nullable();   // FK lógica a arls.nit
            $table->bigInteger('caja_nit')->nullable();  // FK lógica a cajas.nit
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razones_sociales');
        Schema::dropIfExists('cajas');
        Schema::dropIfExists('arls');
        Schema::dropIfExists('pensiones');
        Schema::dropIfExists('eps');
    }
};
