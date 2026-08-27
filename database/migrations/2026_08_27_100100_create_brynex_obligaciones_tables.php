<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de obligaciones tributarias por razón social.
 *
 * Cuatro tablas con papeles distintos:
 *
 *  1. `brynex_obligaciones_catalogo`  — QUÉ obligaciones existen y a qué
 *     régimen aplican. Es parametría, no cambia por razón social.
 *  2. `brynex_calendario_vencimientos` — CUÁNDO vence cada una. La DIAN vence
 *     por último dígito del NIT y cambia el calendario cada año, así que es
 *     una tabla por (año, obligación, período, dígito) que se recarga cada
 *     enero con un seeder.
 *  3. `brynex_obligaciones` — el renglón que el contador chulea.
 *  4. `brynex_obligacion_documentos` — los soportes, al disco `local`.
 *
 * Los años viejos (anteriores al calendario cargado) generan renglones sin
 * `fecha_vencimiento`: sirven para subir el soporte y ponerse al día, pero no
 * entran al semáforo ni disparan alertas, porque una fecha inventada es peor
 * que ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brynex_obligaciones_catalogo')) {
            Schema::create('brynex_obligaciones_catalogo', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('nombre', 150);
                // DIAN | CAMARA | MUNICIPIO
                $table->string('entidad', 30)->default('DIAN');
                // 2593, 260, 300, 350, 110… null para las que no son formulario
                $table->string('formulario', 20)->nullable();
                // 'RST' | 'ORDINARIO' | null = aplica a los dos
                $table->string('regimen', 20)->nullable();
                // bimestral | cuatrimestral | mensual | anual
                $table->string('periodicidad', 20);
                // Solo se genera si la razón social es responsable de IVA.
                $table->boolean('requiere_iva')->default(false);
                // Solo se genera si la periodicidad de IVA de la ficha coincide.
                $table->string('periodicidad_iva_requerida', 20)->nullable();
                $table->string('descripcion', 400)->nullable();
                $table->integer('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('brynex_calendario_vencimientos')) {
            Schema::create('brynex_calendario_vencimientos', function (Blueprint $table) {
                $table->id();
                $table->integer('anio');
                $table->string('obligacion_codigo', 40);
                // Bimestre 1..6 | mes 1..12 | cuatrimestre 1..3 | anual = 1
                $table->integer('periodo');
                // Último dígito del NIT (0..9). Null = misma fecha para todos,
                // como la renovación de matrícula mercantil (31 de marzo).
                $table->integer('ultimo_digito')->nullable();
                $table->date('fecha_vencimiento');
                $table->timestamps();

                $table->unique(
                    ['anio', 'obligacion_codigo', 'periodo', 'ultimo_digito'],
                    'ux_brynex_calendario'
                );
                $table->index(['anio', 'obligacion_codigo']);
            });
        }

        if (! Schema::hasTable('brynex_obligaciones')) {
            Schema::create('brynex_obligaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ficha_id');
                $table->integer('anio');
                $table->string('obligacion_codigo', 40);
                $table->integer('periodo');
                // 'Bimestre 1 (ene-feb)' — se calcula al generar para no tener
                // que rearmarlo en cada vista.
                $table->string('periodo_etiqueta', 60);

                // Null en los años anteriores al calendario cargado.
                $table->date('fecha_vencimiento')->nullable();

                // pendiente | presentada | pagada | no_aplica
                $table->string('estado', 20)->default('pendiente');
                $table->decimal('valor_pagado', 18, 2)->nullable();
                $table->date('fecha_pago')->nullable();
                $table->string('observacion', 500)->nullable();

                // Quién movió el estado por última vez.
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['ficha_id', 'anio', 'obligacion_codigo', 'periodo'],
                    'ux_brynex_obligacion'
                );
                $table->index(['ficha_id', 'anio']);
                // Para el semáforo de vencidas y el comando de alertas.
                $table->index(['estado', 'fecha_vencimiento']);

                $table->foreign('ficha_id')
                    ->references('id')->on('brynex_razones_sociales')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('brynex_obligacion_documentos')) {
            Schema::create('brynex_obligacion_documentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('obligacion_id');
                $table->string('nombre_original', 255);
                // Ruta en el disco `local` (storage/app). Nunca en `public`:
                // una declaración de renta no puede quedar accesible por URL.
                $table->string('ruta', 400);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamano')->nullable();
                $table->unsignedBigInteger('subido_por')->nullable();
                $table->timestamps();

                $table->index('obligacion_id');

                $table->foreign('obligacion_id')
                    ->references('id')->on('brynex_obligaciones')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brynex_obligacion_documentos');
        Schema::dropIfExists('brynex_obligaciones');
        Schema::dropIfExists('brynex_calendario_vencimientos');
        Schema::dropIfExists('brynex_obligaciones_catalogo');
    }
};
