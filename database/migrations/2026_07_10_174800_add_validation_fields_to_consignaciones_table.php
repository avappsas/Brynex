<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->boolean('no_aparece')->default(false)->after('confirmado');
            $table->unsignedBigInteger('usuario_validador_id')->nullable()->after('no_aparece');
            $table->timestamp('fecha_validacion')->nullable()->after('usuario_validador_id');

            $table->foreign('usuario_validador_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->dropForeign(['usuario_validador_id']);
            $table->dropColumn(['no_aparece', 'usuario_validador_id', 'fecha_validacion']);
        });
    }
};
