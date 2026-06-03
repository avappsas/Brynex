<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Campo whatsapp en aliados ──────────────────────────────────────
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('celular');
        });

        // ── 2. Catálogo de módulos de servicio ────────────────────────────────
        Schema::create('brynex_modulos', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->smallInteger('orden')->default(0);
            $table->timestamps();
        });

        // ── 3. Tramos de tarifa (globales o por aliado) ───────────────────────
        Schema::create('brynex_tramos_tarifa', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('modulo_id');
            $table->unsignedBigInteger('aliado_id')->nullable(); // NULL = global
            $table->integer('desde_cant')->default(0);
            $table->integer('hasta_cant')->nullable();        // NULL = sin límite
            $table->decimal('tarifa_unidad', 15, 2)->nullable();
            $table->decimal('tarifa_minima', 15, 2)->nullable();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->foreign('modulo_id')->references('id')->on('brynex_modulos');
            $table->foreign('aliado_id')->references('id')->on('aliados');

            // Índice para búsqueda rápida por módulo + aliado
            $table->index(['modulo_id', 'aliado_id', 'vigente_desde'], 'idx_brynex_tramos_busqueda');
        });

        // ── 4. Módulos contratados por aliado ─────────────────────────────────
        Schema::create('brynex_modulos_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedSmallInteger('modulo_id');
            $table->boolean('activo')->default(true);
            $table->string('notas_negociacion', 500)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('modulo_id')->references('id')->on('brynex_modulos');
            $table->unique(['aliado_id', 'modulo_id'], 'uk_brynex_modulos_aliado');
        });

        // ── 5. Cobros mensuales cerrados por aliado ───────────────────────────
        Schema::create('brynex_cobros_aliados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->smallInteger('mes');           // 1-12
            $table->smallInteger('anio');
            $table->string('estado', 20)->default('pendiente'); // pendiente|parcial|pagado
            $table->decimal('total_cobrado', 15, 2)->default(0);
            $table->decimal('total_pagado', 15, 2)->default(0);
            $table->dateTime('fecha_cierre');
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->unique(['aliado_id', 'mes', 'anio'], 'uk_brynex_cobros_periodo');
        });

        // ── 6. Detalle por módulo de cada cobro ───────────────────────────────
        Schema::create('brynex_cobros_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedSmallInteger('modulo_id');
            $table->string('descripcion', 255)->nullable();
            $table->integer('cant_unidades')->default(0);
            $table->decimal('tarifa_unidad', 15, 2)->nullable();
            $table->decimal('tarifa_minima_aplicada', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('cobro_id')->references('id')->on('brynex_cobros_aliados')->onDelete('cascade');
            $table->foreign('modulo_id')->references('id')->on('brynex_modulos');
        });

        // ── 7. Pagos / abonos del aliado ──────────────────────────────────────
        Schema::create('brynex_pagos_aliados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->decimal('valor', 15, 2);
            $table->date('fecha_pago');
            $table->string('banco', 150)->nullable();
            $table->string('forma_pago', 100)->default('transferencia'); // transferencia|efectivo|cheque|otro
            $table->string('soporte_url', 500)->nullable();    // ruta imagen comprobante
            $table->string('observacion', 500)->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('cobro_id')->references('id')->on('brynex_cobros_aliados');
            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brynex_pagos_aliados');
        Schema::dropIfExists('brynex_cobros_detalle');
        Schema::dropIfExists('brynex_cobros_aliados');
        Schema::dropIfExists('brynex_modulos_aliado');
        Schema::dropIfExists('brynex_tramos_tarifa');
        Schema::dropIfExists('brynex_modulos');

        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
