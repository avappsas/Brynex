<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar empresa_id para vincular claves directamente a empresas.
     */
    public function up(): void
    {
        Schema::table('clave_accesos', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('razon_social_id')->index();
            $table->index(['aliado_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::table('clave_accesos', function (Blueprint $table) {
            $table->dropIndex(['aliado_id', 'empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
