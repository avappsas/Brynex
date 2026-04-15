<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eps', function (Blueprint $table) {
            // Ruta relativa al PDF del formulario de afiliación (ej: formularios/EMSANAR.pdf)
            $table->string('formulario_pdf')->nullable()->after('nombre');
            // JSON con coordenadas de campos para superposición con FPDI
            // Estructura: { "campo_id": { "pagina":1, "x":100, "y":200, "dato":"cliente.primer_nombre" } }
            $table->json('formulario_campos')->nullable()->after('formulario_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('eps', function (Blueprint $table) {
            $table->dropColumn(['formulario_pdf', 'formulario_campos']);
        });
    }
};
