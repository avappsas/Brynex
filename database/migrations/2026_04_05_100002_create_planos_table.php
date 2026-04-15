<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_id')->nullable()->index();
            $table->unsignedInteger('contrato_id')->nullable()->index();
            $table->unsignedInteger('aliado_id')->index();

            // ── Identificación del cotizante ─────────────────────
            $table->string('tipo_reg', 2)->default('01');       // TIPO_REG
            $table->string('tipo_doc', 5)->nullable();           // TIPO_DOC (CC,CE,PA...)
            $table->string('no_identifi', 20)->nullable();       // NO_IDENTIFI
            $table->string('tipo_cot', 5)->nullable();           // TIPO_COT
            $table->string('sub_tipo_cot', 5)->nullable();       // SUB_TIPO_COT

            // ── Nombre ───────────────────────────────────────────
            $table->string('primer_ape', 60)->nullable();
            $table->string('segundo_ape', 60)->nullable();
            $table->string('primer_nombre', 60)->nullable();
            $table->string('segundo_nombre', 60)->nullable();

            // ── Novedades ─────────────────────────────────────────
            $table->boolean('ing')->default(false);
            $table->date('fecha_ing')->nullable();
            $table->boolean('ret')->default(false);
            $table->date('fecha_ret')->nullable();

            // ── Días cotizados ────────────────────────────────────
            $table->smallInteger('num_dias_pension')->default(30);
            $table->smallInteger('num_dias_salud')->default(30);
            $table->smallInteger('num_dias_riesgo')->default(30);
            $table->smallInteger('num_dias_caja')->default(30);

            // ── IBC y salario ─────────────────────────────────────
            $table->decimal('salario_basico', 14, 0)->default(0);
            $table->decimal('ibc_pension',    14, 0)->default(0);
            $table->decimal('ibc_salud',      14, 0)->default(0);
            $table->decimal('ibc_riesgos',    14, 0)->default(0);
            $table->decimal('ibc_caja',       14, 0)->default(0);

            // ── Entidades (snapshot al momento de facturar) ───────
            $table->string('cod_eps', 20)->nullable();
            $table->string('nombre_eps', 100)->nullable();
            $table->string('cod_afp', 20)->nullable();
            $table->string('nombre_afp', 100)->nullable();
            $table->string('cod_arl', 20)->nullable();
            $table->string('nombre_arl', 100)->nullable();
            $table->smallInteger('nivel_riesgo')->default(1);
            $table->string('cod_caja', 20)->nullable();
            $table->string('nombre_caja', 100)->nullable();

            // ── Tarifas aplicadas ─────────────────────────────────
            $table->decimal('tar_salud',   8, 4)->default(0);
            $table->decimal('tar_pension', 8, 4)->default(0);
            $table->decimal('tar_arl',     8, 4)->default(0);
            $table->decimal('tar_caja',    8, 4)->default(0);

            // ── Valores calculados (snapshot) ─────────────────────
            $table->decimal('val_eps',  14, 0)->default(0);
            $table->decimal('val_afp',  14, 0)->default(0);
            $table->decimal('val_arl',  14, 0)->default(0);
            $table->decimal('val_caja', 14, 0)->default(0);
            $table->decimal('total_cot',14, 0)->default(0);

            // ── Control del plano ─────────────────────────────────
            $table->unsignedInteger('n_plano')->nullable();
            $table->smallInteger('mes_plano')->nullable();
            $table->smallInteger('anio_plano')->nullable();
            $table->string('razon_social', 200)->nullable();
            $table->string('tipo_p', 20)->nullable();  // CC|PT|empresa...
            $table->unsignedInteger('usuario_id')->nullable();

            $table->timestamps();

            $table->foreign('factura_id')->references('id')->on('facturas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
