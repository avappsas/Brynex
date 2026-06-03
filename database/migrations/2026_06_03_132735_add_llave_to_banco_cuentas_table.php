<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->string('llave', 255)->nullable()->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->dropColumn('llave');
        });
    }
};
