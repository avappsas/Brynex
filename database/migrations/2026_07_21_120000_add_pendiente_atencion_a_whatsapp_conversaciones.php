<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->boolean('pendiente_atencion')->default(false)->after('bot_activo');
            $table->string('pendiente_motivo', 255)->nullable()->after('pendiente_atencion');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropColumn(['pendiente_atencion', 'pendiente_motivo']);
        });
    }
};
