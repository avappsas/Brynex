<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id');
            $table->string('cedula', 20);
            $table->string('nombre', 200);
            $table->string('telefono', 50)->nullable();
            $table->string('celular', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('cuenta_bancaria', 100)->nullable();

            // Comisión de afiliación (1er mes por contrato)
            $table->string('comision_afil_tipo', 20)->default('fijo'); // 'fijo' | 'porcentaje'
            $table->decimal('comision_afil_valor', 10, 2)->default(0);

            // Comisión de administración (todos los meses siguientes)
            $table->string('comision_admon_tipo', 20)->default('fijo'); // 'fijo' | 'porcentaje'
            $table->decimal('comision_admon_valor', 10, 2)->default(0);

            $table->date('fecha_ingreso')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('id_original_access')->nullable(); // Trazabilidad migración

            $table->timestamps();
            $table->softDeletes();

            // FK sin cascade para evitar problemas SQL Server rutas múltiples
            $table->foreign('aliado_id')->references('id')->on('aliados');

            // Cédula única por aliado (no global)
            $table->unique(['aliado_id', 'cedula'], 'asesores_aliado_cedula_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesores');
    }
};
