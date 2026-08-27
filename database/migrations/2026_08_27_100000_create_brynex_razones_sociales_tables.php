<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha maestra de razones sociales que administra BryNex.
 *
 * ── Por qué una tabla nueva y no columnas en `razones_sociales` ──────────
 *
 * `razones_sociales` está por aliado: el mismo NIT existe como fila distinta
 * en cada aliado que usa esa empresa. ELITES CREACIONES (901904750) tiene 4
 * filas (aliados 1, 2, 6 y 9) y entre Grupo Fecop y BRYGAR suma 357 afiliados
 * vigentes. Poner el régimen tributario ahí significaría cuatro respuestas
 * distintas a "¿esta empresa es RST u ordinaria?", que tiene una sola.
 *
 * Así que la unidad del módulo es el NIT: una ficha por NIT, y
 * `brynex_razon_social_vinculos` la amarra a las N filas de los aliados. Los
 * afiliados, el dinero y las obligaciones se consolidan por ficha.
 *
 * ── Por qué NO lleva aliado_id ───────────────────────────────────────────
 *
 * Rompe a propósito la regla de "toda tabla nueva lleva aliado_id": la ficha
 * es de BryNex (la empresa dueña de la plataforma), no de ningún aliado, igual
 * que `brynex_modulos`. El aislamiento lo da el permiso `brynex_razones.*`,
 * que exige `es_brynex` vía el Gate::before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brynex_razones_sociales')) {
            Schema::create('brynex_razones_sociales', function (Blueprint $table) {
                $table->id();

                // El NIT es la identidad real. `razones_sociales.nit` es bigint.
                $table->bigInteger('nit')->unique();
                $table->integer('dv')->nullable();
                $table->string('razon_social', 255);

                // 'brynex' = es de la casa (BRYGAR y compañía) | 'tercero'
                $table->string('propiedad', 20)->default('tercero');

                // ── Perfil tributario: es lo que dispara el checklist ──────
                // 'RST' (régimen simple) | 'ORDINARIO'
                $table->string('regimen', 20)->nullable();
                // 'bimestral' | 'cuatrimestral' | 'anual' | 'no_responsable'
                $table->string('periodicidad_iva', 20)->nullable();
                // Códigos de responsabilidad del RUT, como JSON: ["48","49"]
                $table->text('responsabilidades_rut')->nullable();

                // Obligatoria al poner la razón social en seguimiento: marca
                // desde qué año se genera el checklist hacia atrás. Está vacía
                // en 203 de las 249 filas de `razones_sociales`, por eso se
                // pide en el momento de seguir y no se copia a ciegas.
                $table->date('fecha_constitucion')->nullable();

                // Si la firma caduca no se puede declarar nada: entra al semáforo.
                $table->date('firma_electronica_vence')->nullable();

                // El municipio decide la periodicidad del ICA.
                $table->string('municipio_ica', 120)->nullable();
                $table->string('periodicidad_ica', 20)->nullable();

                // Contador de BryNex responsable. Recibe las alertas.
                $table->unsignedBigInteger('contador_id')->nullable()->index();

                // El listado muestra las 249; solo las seguidas tienen checklist.
                $table->boolean('en_seguimiento')->default(true);
                $table->string('estado', 20)->default('activa');
                $table->text('notas')->nullable();

                $table->unsignedBigInteger('creado_por')->nullable();
                $table->unsignedBigInteger('actualizado_por')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['en_seguimiento', 'propiedad']);
            });
        }

        if (! Schema::hasTable('brynex_razon_social_vinculos')) {
            Schema::create('brynex_razon_social_vinculos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ficha_id');
                // `razones_sociales.id` es int, no bigint: legacy sin IDENTITY.
                $table->integer('razon_social_id');
                $table->unsignedBigInteger('aliado_id');
                $table->timestamps();

                $table->unique(['ficha_id', 'razon_social_id'], 'ux_brs_vinculo');
                $table->index('razon_social_id');
                $table->index('aliado_id');

                $table->foreign('ficha_id')
                    ->references('id')->on('brynex_razones_sociales')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brynex_razon_social_vinculos');
        Schema::dropIfExists('brynex_razones_sociales');
    }
};
