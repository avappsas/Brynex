<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla incapacidades con los campos necesarios para:
 *  - Guardar el salario_base al momento de radicar (no cambia aunque cambie el contrato)
 *  - Calcular y almacenar valor_esperado automáticamente
 *  - Generar token único para link de subida de documentos del cliente
 *  - Controlar a quién se paga (cliente o empresa) con FK explícita
 *  - Normalizar estados al nuevo catálogo del módulo rediseñado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incapacidades', function (Blueprint $table) {

            // ── Salario base guardado al momento de crear la incapacidad ────────
            // Se copia de contratos.salario para que no cambie aunque el contrato
            // sea retirado o el salario del año siguiente cambie.
            if (!Schema::hasColumn('incapacidades', 'salario_base')) {
                $table->decimal('salario_base', 12, 2)->nullable()->after('valor_esperado');
            }

            // ── Token para link de subida de documentos (cliente) ───────────────
            // Se genera con Str::uuid() y se usa en ruta pública /incapacidades/subir/{token}
            if (!Schema::hasColumn('incapacidades', 'token_subida')) {
                $table->string('token_subida', 64)->nullable()->unique()->after('salario_base');
            }

            // ── A quién se paga: 'cliente' | 'empresa' (manual, case by case) ───
            if (!Schema::hasColumn('incapacidades', 'pagado_a_tipo')) {
                $table->string('pagado_a_tipo', 10)->nullable()->after('pagado_a');
            }

            // ── FK explícita al cliente que recibe el pago ───────────────────────
            if (!Schema::hasColumn('incapacidades', 'pagado_a_cliente_id')) {
                $table->unsignedBigInteger('pagado_a_cliente_id')->nullable()->after('pagado_a_tipo');
            }

            // ── FK explícita a la empresa cliente que recibe el pago ─────────────
            if (!Schema::hasColumn('incapacidades', 'pagado_a_empresa_id')) {
                $table->unsignedBigInteger('pagado_a_empresa_id')->nullable()->after('pagado_a_cliente_id');
            }

            // ── Descripción del accidente/enfermedad (la sube el cliente) ────────
            if (!Schema::hasColumn('incapacidades', 'descripcion_cliente')) {
                $table->text('descripcion_cliente')->nullable()->after('observacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incapacidades', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('incapacidades', 'salario_base')        ? 'salario_base'        : null,
                Schema::hasColumn('incapacidades', 'token_subida')        ? 'token_subida'        : null,
                Schema::hasColumn('incapacidades', 'pagado_a_tipo')       ? 'pagado_a_tipo'       : null,
                Schema::hasColumn('incapacidades', 'pagado_a_cliente_id') ? 'pagado_a_cliente_id' : null,
                Schema::hasColumn('incapacidades', 'pagado_a_empresa_id') ? 'pagado_a_empresa_id' : null,
                Schema::hasColumn('incapacidades', 'descripcion_cliente') ? 'descripcion_cliente' : null,
            ]));
        });
    }
};
