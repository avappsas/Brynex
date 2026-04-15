<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            // Indica si esta cuenta aparece en la Cuenta de Cobro como opción para consignar
            $table->boolean('cobro')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->dropColumn('cobro');
        });
    }
};
