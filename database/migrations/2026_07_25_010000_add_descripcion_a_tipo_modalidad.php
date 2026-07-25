<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto de negocio "para qué sirve esta modalidad y a quién aplica" — igual que en
 * planes_contrato.descripcion, es la fuente de verdad para el admin y para el
 * conocimiento que usa la IA al cotizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipo_modalidad', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('tipo_modalidad', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
