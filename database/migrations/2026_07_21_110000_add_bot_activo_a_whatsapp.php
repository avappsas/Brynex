<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->boolean('bot_activo')->default(true)->after('estado');
        });

        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            $table->boolean('es_bot')->default(false)->after('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropColumn('bot_activo');
        });

        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            $table->dropColumn('es_bot');
        });
    }
};
