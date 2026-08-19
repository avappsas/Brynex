<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado de consignación en los abonos a préstamos.
 * El archivo vive en el disco `local` (storage/app), nunca en public:
 * es un soporte bancario con datos del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->string('soporte_path', 255)->nullable()->after('observacion');
            $table->string('soporte_nombre', 200)->nullable()->after('soporte_path');
        });
    }

    public function down(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->dropColumn(['soporte_path', 'soporte_nombre']);
        });
    }
};
