<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            // Categoría de la plantilla de Meta que originó la conversación:
            // MARKETING | UTILITY (cobros/transaccional) | AUTHENTICATION
            $table->string('origen_campana_categoria', 30)->nullable()->after('origen_campana');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropColumn('origen_campana_categoria');
        });
    }
};
