<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Descripción libre del trámite (solo para tipo='otro_ingreso')
            $table->string('descripcion_tramite', 300)->nullable()->after('observacion');
            // Comisión manual del asesor en otro ingreso (diferente a admin_asesor de planilla)
            $table->decimal('admon_asesor_oi', 14, 0)->default(0)->after('descripcion_tramite');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['descripcion_tramite', 'admon_asesor_oi']);
        });
    }
};
