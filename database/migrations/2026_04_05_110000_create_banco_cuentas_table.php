<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banco_cuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();     // multi-tenant

            // Datos del titular
            $table->string('nombre', 150);                    // nombre empresa/persona
            $table->string('nit', 30)->nullable();            // NIT o cédula del titular

            // Datos bancarios
            $table->string('banco', 100);                     // nombre del banco
            $table->string('tipo_cuenta', 30)->nullable();    // Ahorros | Corriente
            $table->string('numero_cuenta', 50)->nullable();  // número de cuenta

            // Control
            $table->boolean('activo')->default(true);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_cuentas');
    }
};
