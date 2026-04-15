<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dropear tabla clientes vieja (vacía, estructura anterior no normalizada)
        DB::statement("IF OBJECT_ID('clientes', 'U') IS NOT NULL DROP TABLE clientes");

        Schema::create('clientes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // Mantener ID original
            $table->integer('cod_empresa')->nullable();
            $table->string('tipo_doc', 10)->nullable();
            $table->bigInteger('cedula')->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
