<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente de un préstamo formal: los datos legales del deudor, el codeudor
 * y la garantía con los que se llenan el contrato de mutuo, el pagaré y la
 * carta de instrucciones.
 *
 * Vive aparte de `finanzas_prestamos` a propósito. Un expediente sin firmar no
 * es todavía un préstamo: no hay plata entregada, no debe sumar cartera, ni
 * liquidar intereses, ni disparar recordatorios de WhatsApp. El registro en
 * `finanzas_prestamos` (y el gasto que descuenta la caja) nace apenas se
 * registra el desembolso, y desde ahí el préstamo se comporta como cualquier
 * otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_prestamo_expedientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prestamista_id');
            $table->unsignedBigInteger('prestamo_id')->nullable(); // se llena al desembolsar
            $table->string('estado', 20)->default('pendiente_firma'); // pendiente_firma | firmado | desembolsado | anulado
            $table->string('pagare_numero', 30);
            $table->string('ciudad', 60)->default('Cali'); // suscripción, pago y domicilio contractual

            // Deudor (EL MUTUARIO)
            $table->string('deudor_nombre', 120);
            $table->string('deudor_cedula', 20);
            $table->string('deudor_expedida_en', 60)->nullable();
            $table->string('deudor_direccion', 150)->nullable();
            $table->string('deudor_ciudad', 60)->nullable();
            $table->string('deudor_telefono', 30)->nullable();
            $table->string('deudor_correo', 120)->nullable();
            $table->string('deudor_ocupacion', 100)->nullable();

            // Codeudor solidario (opcional)
            $table->boolean('tiene_codeudor')->default(false);
            $table->string('codeudor_nombre', 120)->nullable();
            $table->string('codeudor_cedula', 20)->nullable();
            $table->string('codeudor_expedida_en', 60)->nullable();
            $table->string('codeudor_direccion', 150)->nullable();
            $table->string('codeudor_ciudad', 60)->nullable();
            $table->string('codeudor_telefono', 30)->nullable();
            $table->string('codeudor_correo', 120)->nullable();
            $table->string('codeudor_ocupacion', 100)->nullable();

            // Garantía prendaria sobre vehículo (opcional)
            $table->boolean('tiene_prenda')->default(false);
            $table->string('prenda_placa', 10)->nullable();
            $table->string('prenda_clase', 40)->nullable(); // automóvil, motocicleta...
            $table->string('prenda_marca', 40)->nullable();
            $table->string('prenda_linea', 60)->nullable();
            $table->string('prenda_modelo', 10)->nullable(); // año
            $table->string('prenda_color', 30)->nullable();
            $table->string('prenda_motor', 40)->nullable();
            $table->string('prenda_chasis', 40)->nullable();
            $table->string('prenda_matricula', 40)->nullable(); // licencia de tránsito
            $table->decimal('prenda_avaluo', 18, 2)->nullable();
            $table->string('prenda_propietario', 120)->nullable();
            $table->string('prenda_propietario_cedula', 20)->nullable();

            // Condiciones económicas
            $table->decimal('monto', 18, 2);
            $table->decimal('tasa_interes_mensual', 5, 3);
            $table->integer('plazo_meses');
            $table->date('fecha_desembolso'); // prevista al crear, real al desembolsar
            $table->date('fecha_vencimiento');
            $table->tinyInteger('dia_cobro');
            $table->integer('dias_mora_alerta')->default(30);
            $table->tinyInteger('pagare_factor')->default(2); // tope del pagaré en veces el monto
            $table->decimal('pagare_tope', 18, 2);

            // Firma y desembolso
            $table->date('fecha_firma')->nullable();
            $table->string('documentos_path', 255)->nullable(); // PDF unido, ya firmado
            $table->string('descripcion', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado'], 'ix_expediente_user_estado');
            $table->index('prestamo_id', 'ix_expediente_prestamo');
        });
    }

    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_prestamo_expedientes');
    }
};
