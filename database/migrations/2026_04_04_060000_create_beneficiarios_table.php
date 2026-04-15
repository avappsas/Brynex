<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aliado_id');
            $table->bigInteger('cc_cliente');          // → clientes.cedula
            $table->string('tipo_doc', 10)->nullable();
            $table->string('n_documento', 20)->nullable();
            $table->string('nombres', 255);
            $table->date('fecha_expedicion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('parentesco', 100)->nullable(); // libre: hijo, sobrino, cónyuge…
            $table->text('observacion')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->index(['aliado_id', 'cc_cliente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};
