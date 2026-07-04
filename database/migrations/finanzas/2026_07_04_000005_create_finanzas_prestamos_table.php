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
        Schema::connection('finanzas')->create('finanzas_prestamos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre_deudor', 100);
            $table->string('cedula_deudor', 20)->nullable();
            $table->string('telefono_deudor', 20)->nullable();
            $table->decimal('monto_original', 18, 2);
            $table->decimal('tasa_interes_mensual', 5, 3); // Ej. 3.500 para 3.5%
            $table->date('fecha_desembolso');
            $table->date('ultimo_corte')->nullable();
            $table->decimal('saldo_actual', 18, 2);
            $table->string('estado', 20)->default('activo'); // activo | pagado | mora | castigado
            $table->integer('dias_mora_alerta')->default(30);
            $table->boolean('alertas_activas')->default(true);
            $table->string('soporte_path', 255)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('es_cuenta_corriente')->default(false);
            $table->string('cuenta_corriente_grupo', 50)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado'], 'ix_prestamo_user_estado');
            $table->index(['es_cuenta_corriente', 'cuenta_corriente_grupo'], 'ix_prestamo_cc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_prestamos');
    }
};
