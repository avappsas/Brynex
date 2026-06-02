<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_asesores', function (Blueprint $table) {
            try {
                $table->dropForeign('FK_pagos_asesores_asesor');
            } catch (\Throwable $e) {}

            $table->unsignedBigInteger('asesor_id')->nullable()->change();
            $table->foreign('asesor_id', 'FK_pagos_asesores_asesor')->references('id')->on('asesores');

            $table->unsignedBigInteger('encargado_usuario_id')->nullable()->after('asesor_id');
            $table->foreign('encargado_usuario_id')->references('id')->on('users');
            
            $table->index(['aliado_id', 'encargado_usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pagos_asesores', function (Blueprint $table) {
            try {
                $table->dropForeign(['encargado_usuario_id']);
                $table->dropIndex(['aliado_id', 'encargado_usuario_id']);
            } catch (\Throwable $e) {}
            
            $table->dropColumn('encargado_usuario_id');
            
            try {
                $table->dropForeign('FK_pagos_asesores_asesor');
            } catch (\Throwable $e) {}

            $table->unsignedBigInteger('asesor_id')->nullable(false)->change();
            $table->foreign('asesor_id', 'FK_pagos_asesores_asesor')->references('id')->on('asesores');
        });
    }
};
