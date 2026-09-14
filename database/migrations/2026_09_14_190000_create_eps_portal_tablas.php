<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas para tramitar en los portales de empleadores de las EPS (primero Nueva
 * EPS): usuarios del portal, qué empresa entra con cuál, el historial de cada
 * trámite y las equivalencias de cargos.
 *
 * - `eps_usuarios_portal`: la clave es de la persona, no de la empresa (en
 *   Nueva EPS el mismo usuario administra ELITES y Elitex). Guardarla una vez
 *   evita copias que queden viejas — la lección de `arl_usuarios_portal`.
 *   Sin `aliado_id`: el usuario es de la EPS y la empresa está en varios aliados.
 * - `eps_portal_empresas`: la empresa (por NIT) → usuario del portal y asesor
 *   que la EPS le asignó (Nueva EPS lo exige en cada reingreso).
 * - `eps_afiliaciones`: cada trámite con lo enviado y lo recibido, porque los
 *   portales no tienen ambiente de pruebas.
 * - `eps_ocupaciones`: BryNex guarda el cargo con su código de ocupación y nivel
 *   de riesgo; cada EPS tiene su propio catálogo de cargos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eps_usuarios_portal', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 30);                      // nueva_eps
            $table->string('tipo_documento', 4)->default('CC');
            $table->string('usuario', 30);
            $table->text('contrasena');                         // cifrada por Eloquent
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_sesion_at')->nullable();
            $table->string('ultimo_error', 300)->nullable();
            $table->timestamps();

            $table->unique(['entidad', 'tipo_documento', 'usuario'], 'uq_eps_usuario_portal');
        });

        Schema::create('eps_portal_empresas', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 30);
            $table->string('nit', 20);
            $table->unsignedBigInteger('usuario_portal_id');
            $table->string('codigo_asesor', 20)->nullable();
            $table->string('nombre_asesor', 120)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('usuario_portal_id')->references('id')->on('eps_usuarios_portal');
            $table->unique(['entidad', 'nit'], 'uq_eps_portal_empresa');
        });

        Schema::create('eps_afiliaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('contrato_id');             // contratos.id es int (legacy)
            $table->unsignedBigInteger('radicado_id')->nullable();
            $table->string('entidad', 30);
            $table->string('operacion', 20);                    // reingreso | retiro
            $table->string('estado', 20);                       // exitosa | fallida | existente
            $table->string('numero_radicado', 40)->nullable();
            $table->text('payload')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('mensaje_error', 500)->nullable();
            $table->string('ruta_pdf', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('contrato_id')->references('id')->on('contratos');
            $table->index(['contrato_id', 'entidad'], 'ix_eps_afi_contrato');
        });

        Schema::create('eps_ocupaciones', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 30);
            $table->string('codigo_ocupacion', 20);             // el de razon_social_cargos
            $table->string('codigo_entidad', 20);               // el del catálogo de la EPS
            $table->string('descripcion_entidad', 200);
            $table->timestamps();

            $table->unique(['entidad', 'codigo_ocupacion'], 'uq_eps_ocupacion');
        });

        // Primera equivalencia, elegida con el usuario el 14-sep-2026 para
        // OPERARIO DE CONFECCION (nivel 2): el 8263 de BryNex es "operadores de
        // máquinas de coser", que en el catálogo de Nueva EPS es el 8153.
        DB::table('eps_ocupaciones')->insert([
            'entidad'             => 'nueva_eps',
            'codigo_ocupacion'    => '8263',
            'codigo_entidad'      => '8153',
            'descripcion_entidad' => 'OPERADORES DE MÁQUINAS DE COSER',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('eps_ocupaciones');
        Schema::dropIfExists('eps_afiliaciones');
        Schema::dropIfExists('eps_portal_empresas');
        Schema::dropIfExists('eps_usuarios_portal');
    }
};
