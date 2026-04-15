<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_cliente', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id');
            $table->bigInteger('cc_cliente');                    // → clientes.cedula
            $table->string('doc_beneficiario', 20)->nullable();  // NULL = titular; nº doc beneficiario
            $table->string('tipo_documento', 50);               // cedula, carta_laboral, registro_civil…
            $table->string('nombre_archivo', 255);              // Nombre original del archivo
            $table->string('ruta', 500);                        // documentos/{aliado_id}/{cedula}/file.ext
            $table->unsignedBigInteger('subido_por');           // → users.id
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->foreign('subido_por')->references('id')->on('users');
            $table->index(['aliado_id', 'cc_cliente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_cliente');
    }
};
