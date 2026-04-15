<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->integer('numero_factura');          // secuencial por aliado

            // Tipo y periodo
            $table->string('tipo', 20);                 // 'afiliacion' | 'planilla'
            $table->bigInteger('cedula')->index();
            $table->unsignedInteger('contrato_id')->nullable()->index();
            $table->smallInteger('mes');                // 1-12: periodo de pago
            $table->smallInteger('anio');               // año del periodo

            // Fechas
            $table->date('fecha_pago');                 // fecha real de facturación
            $table->date('fecha_probable_pago')->nullable();

            // Estado
            $table->string('estado', 20)->default('pre_factura');
            // pre_factura | abono | pagada | prestamo
            $table->boolean('es_prestamo')->default(false);

            // Forma de pago
            $table->string('forma_pago', 20)->nullable(); // efectivo|consignacion|mixto
            $table->unsignedInteger('banco_cuenta_id')->nullable();
            $table->decimal('valor_consignado', 14, 0)->default(0);
            $table->decimal('valor_efectivo',   14, 0)->default(0);

            // ── Valores de seguridad social ──────────────────────
            $table->smallInteger('dias_cotizados')->default(30);
            $table->decimal('v_eps',  14, 0)->default(0);
            $table->decimal('v_arl',  14, 0)->default(0);
            $table->decimal('v_afp',  14, 0)->default(0);
            $table->decimal('v_caja', 14, 0)->default(0);
            $table->decimal('total_ss', 14, 0)->default(0);

            // ── Valores administrativos ──────────────────────────
            $table->decimal('admon',         14, 0)->default(0);
            $table->decimal('admin_asesor',  14, 0)->default(0);
            $table->decimal('seguro',        14, 0)->default(0);
            $table->decimal('afiliacion',    14, 0)->default(0); // primer mes
            $table->decimal('mensajeria',    14, 0)->default(0);
            $table->decimal('otros',         14, 0)->default(0);
            $table->decimal('iva',           14, 0)->default(0);
            $table->decimal('total',         14, 0)->default(0);

            // ── Saldos ───────────────────────────────────────────
            $table->decimal('saldo_a_favor',  14, 0)->default(0); // aplicado de mes anterior
            $table->decimal('saldo_pendiente',14, 0)->default(0); // debe de mes anterior
            $table->decimal('saldo_proximo',  14, 0)->default(0); // queda para siguiente mes

            // ── Distribución interna (para informes) ─────────────
            $table->decimal('c_asesor',       14, 0)->default(0); // comisión asesor
            $table->decimal('c_utilidad',     14, 0)->default(0); // utilidad aliado
            $table->decimal('retiro',         14, 0)->default(0); // cálculo retiro afiliación

            // ── Agrupación empresa ────────────────────────────────
            $table->unsignedInteger('np')->nullable()->index(); // número de pago grupal
            $table->unsignedInteger('n_plano')->nullable();     // número de plano

            // ── Razon social ──────────────────────────────────────
            $table->unsignedInteger('razon_social_id')->nullable();

            // ── Meta ──────────────────────────────────────────────
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('observacion', 500)->nullable();
            $table->text('obs_factura')->nullable();
            $table->timestamps();
        });

        // Secuencia de número de factura por aliado (tabla auxiliar)
        Schema::create('factura_secuencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->unique();
            $table->unsignedInteger('ultimo_numero')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_secuencias');
        Schema::dropIfExists('facturas');
    }
};
