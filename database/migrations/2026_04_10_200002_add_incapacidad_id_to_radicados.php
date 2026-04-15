<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la tabla radicados para soportar documentos de incapacidades.
 *
 * La tabla radicados ya almacena PDFs de afiliaciones; se reutiliza
 * para incapacidades agregando:
 *  - incapacidad_id: FK a la incapacidad a la que pertenece el documento
 *  - tipo_documento: categoriza el archivo subido
 *
 * Cuando incapacidad_id tiene valor, contrato_id puede ser NULL o tener
 * el contrato de contexto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            // FK a incapacidades (NULL si el radicado es de afiliación)
            $table->unsignedBigInteger('incapacidad_id')
                  ->nullable()
                  ->after('aliado_id')
                  ->index();

            // Tipo de documento:
            // incapacidad_original | historia_clinica | radicado_entidad
            // soporte_pago | transcripcion | otro
            $table->string('tipo_documento', 40)
                  ->nullable()
                  ->after('incapacidad_id');

            $table->foreign('incapacidad_id')
                  ->references('id')
                  ->on('incapacidades')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            $table->dropForeign(['incapacidad_id']);
            $table->dropIndex(['incapacidad_id']);
            $table->dropColumn(['incapacidad_id', 'tipo_documento']);
        });
    }
};
