<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('planos', 'id_legacy')) {
            Schema::table('planos', function (Blueprint $table) {
                $table->bigInteger('id_legacy')->nullable()->index()->after('id')
                      ->comment('ID original del registro en la BD legacy (PLANOS.Id)');
            });
        }
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('id_legacy');
        });
    }
};
