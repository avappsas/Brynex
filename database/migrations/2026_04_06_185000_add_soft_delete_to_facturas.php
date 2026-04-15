<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->softDeletes();                              // columna deleted_at
            $table->text('motivo_anulacion')->nullable();      // motivo legible
            $table->unsignedSmallInteger('anulado_por')->nullable(); // usuario que anuló
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['motivo_anulacion', 'anulado_por']);
        });
    }
};
