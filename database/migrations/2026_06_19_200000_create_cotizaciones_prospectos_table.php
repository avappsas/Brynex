<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_prospectos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->unsignedBigInteger('asesor_id')->nullable()->index();
            
            $table->string('tipo_doc', 10)->nullable();
            $table->string('cedula', 20)->nullable()->index();
            $table->string('primer_nombre', 55)->nullable();
            $table->string('segundo_nombre', 55)->nullable();
            $table->string('primer_apellido', 55)->nullable();
            $table->string('segundo_apellido', 55)->nullable();
            
            $table->string('celular', 20)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('ocupacion', 80)->nullable();
            $table->unsignedInteger('municipio_id')->nullable();
            $table->string('referido', 80)->nullable();
            
            $table->string('canal_origen', 20)->nullable(); // redes_sociales, whatsapp, campana, referido, empresa
            
            $table->unsignedBigInteger('modalidad_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->integer('salario_base')->nullable();
            
            $table->string('estado', 20)->default('sin_respuesta')->index(); // interesado, sin_respuesta, pendiente_resp, no_interesado, convertido
                  
            $table->text('razon_no_afiliacion')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            
            $table->date('fecha_cotizacion')->nullable();
            $table->date('proxima_llamada')->nullable();
            
            $table->unsignedBigInteger('creado_por')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_prospectos');
    }
};
