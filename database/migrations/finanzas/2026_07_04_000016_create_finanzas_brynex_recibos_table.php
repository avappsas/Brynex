<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_brynex_recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('monto_total', 18, 2);
            $table->date('fecha_pago');
            $table->string('soporte_path', 500)->nullable();
            $table->string('banco', 100)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->timestamps();
        });

        Schema::connection('finanzas')->table('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('recibo_id')->nullable()->after('user_id');
            $table->foreign('recibo_id')->references('id')->on('finanzas_brynex_recibos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->dropForeign(['recibo_id']);
            $table->dropColumn('recibo_id');
        });

        Schema::connection('finanzas')->dropIfExists('finanzas_brynex_recibos');
    }
};
