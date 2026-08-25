<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cliente de cuenta corriente: el contenedor (ej. "Oficina Arroyave") bajo el
     * que cuelgan los trabajos que se le hacen. Antes el único vínculo entre
     * trabajos era el texto libre `cuenta_corriente_grupo`, que no permitía
     * totalizar, cobrar ni contactar al cliente como una sola cuenta.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_cc_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 100);
            $table->string('cedula', 20)->nullable();
            $table->string('telefono', 20)->nullable();
            // Tasa por defecto que hereda cada trabajo nuevo; el trabajo puede pisarla.
            $table->decimal('tasa_interes_mensual', 5, 3)->default(0);
            $table->integer('dias_mora_alerta')->default(30);
            $table->boolean('alertas_activas')->default(true);
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'activo'], 'ix_cc_cliente_user_activo');
        });
    }

    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_cc_clientes');
    }
};
