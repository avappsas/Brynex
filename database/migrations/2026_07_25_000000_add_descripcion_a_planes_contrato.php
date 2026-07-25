<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto de negocio "para qué sirve este plan" — fuente de verdad tanto para el admin
 * como para el conocimiento que usa la IA al cotizar (CotizarPlanTool).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_contrato', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('planes_contrato', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
