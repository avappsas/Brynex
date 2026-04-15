<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de incapacidades laborales y de salud.
 *
 * Cada registro puede ser:
 *  - Incapacidad original (incapacidad_padre_id = NULL)
 *  - Prórroga de otra incapacidad (incapacidad_padre_id = ID del padre)
 *
 * El campo `prorroga` indica si el documento físico viene marcado como prórroga
 * (impacta el cálculo: EPS no descuenta los 2 primeros días en una prórroga).
 *
 * El estado se actualiza automáticamente cada vez que se registra una gestión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incapacidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->index();

            // ── Relación familiar (padre / prórroga) ─────────────────────────
            $table->unsignedBigInteger('incapacidad_padre_id')->nullable()->index();
            $table->unsignedSmallInteger('numero_proroga')->default(0);
            // 0 = original, 1 = 1ª prórroga, 2 = 2ª, ...

            // ── Vínculo con el contrato (contexto al momento de radicar) ─────
            $table->unsignedInteger('contrato_id')->nullable();

            // ── Datos del afiliado ───────────────────────────────────────────
            $table->string('cedula_usuario', 20)->index();
            // quien_remite: cod_empresa del cliente o cedula_usuario si es independiente
            $table->string('quien_remite', 100)->nullable();
            // quien_recibe: usuario asignado para gestionar
            $table->unsignedBigInteger('quien_recibe_id')->nullable();

            // ── Datos de la incapacidad ──────────────────────────────────────
            // enfermedad_general | licencia_maternidad | licencia_paternidad
            // accidente_transito | accidente_laboral
            $table->string('tipo_incapacidad', 30);
            $table->unsignedSmallInteger('dias_incapacidad')->default(0);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_terminacion')->nullable();
            $table->date('fecha_recibido')->nullable();

            // ¿Viene marcada como prórroga en el documento físico?
            // true → EPS no descuenta los 2 primeros días al calcular
            $table->boolean('prorroga')->default(false);

            // ── Entidad responsable ──────────────────────────────────────────
            // eps | arl | afp
            $table->string('tipo_entidad', 10);
            $table->unsignedBigInteger('entidad_responsable_id')->nullable();
            // Nombre guardado al momento de radicar (por si cambia de entidad)
            $table->string('entidad_nombre', 150)->nullable();

            // ── Razón Social (NIT empresa donde está afiliado al radicar) ────
            // int para coincidir con razones_sociales.id (SQL Server: int, no bigint)
            $table->unsignedInteger('razon_social_id')->nullable();
            $table->string('razon_social_nombre', 200)->nullable();

            // ── Radicado en la entidad ───────────────────────────────────────
            $table->string('numero_radicado', 80)->nullable();
            $table->date('fecha_radicado')->nullable();

            // ── Transcripción IPS → EPS/ARL ──────────────────────────────────
            // Si el cliente fue atendido en IPS distinta, la incapacidad
            // debe transcribirse a la EPS/ARL antes de poder radicarla.
            $table->boolean('transcripcion_requerida')->default(false);
            $table->boolean('transcripcion_completada')->default(false);

            // ── Estado de pago ───────────────────────────────────────────────
            // pendiente | autorizado | liquidado | pagado_afiliado | rechazado
            $table->string('estado_pago', 25)->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->decimal('valor_esperado', 12, 2)->nullable();
            $table->text('detalle_pago')->nullable();

            // ── Pago final al afiliado o empresa ─────────────────────────────
            // cliente | empresa
            $table->string('pagado_a', 10)->nullable();
            // Ruta del soporte firmado de pago
            $table->string('ruta_soporte_pago', 500)->nullable();

            // ── Información médica ───────────────────────────────────────────
            $table->string('diagnostico', 200)->nullable();
            $table->text('concepto_rehabilitacion')->nullable();
            $table->text('observacion')->nullable();

            // ── Estado general (calculado desde la última gestión) ───────────
            // recibido | radicado | en_tramite | autorizado | liquidado
            // pagado_afiliado | rechazado | cerrado
            $table->string('estado', 25)->default('recibido');

            // ── Auditoría ────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Claves foráneas ──────────────────────────────────────────────
            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            // Auto-referencial: SQL Server no acepta cascade en ciclos
            $table->foreign('incapacidad_padre_id')->references('id')->on('incapacidades')->noActionOnDelete();
            // contratos sin FK para evitar ciclos de cascade en SQL Server
            $table->foreign('quien_recibe_id')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales')->noActionOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->noActionOnDelete();

            // ── Índices de búsqueda ──────────────────────────────────────────
            $table->index(['aliado_id', 'estado']);
            $table->index(['aliado_id', 'cedula_usuario']);
            $table->index(['aliado_id', 'tipo_entidad', 'estado_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incapacidades');
    }
};
