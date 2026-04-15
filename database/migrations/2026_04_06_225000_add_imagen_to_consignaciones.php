<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            // Ruta del soporte (jpg/png/pdf) de la consignación
            // Almacenada en: consignaciones/{aliado_id}/{factura_id}/{id}.ext
            $table->string('imagen_path', 500)->nullable()->after('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->dropColumn('imagen_path');
        });
    }
};
