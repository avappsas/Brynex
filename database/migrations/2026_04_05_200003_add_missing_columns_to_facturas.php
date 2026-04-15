<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Segunda cuenta bancaria para pagos mixtos
            if (!Schema::hasColumn('facturas', 'banco_cuenta2_id')) {
                $table->unsignedInteger('banco_cuenta2_id')->nullable()->after('banco_cuenta_id');
            }
            if (!Schema::hasColumn('facturas', 'valor_banco2')) {
                $table->decimal('valor_banco2', 14, 0)->default(0)->after('banco_cuenta2_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['banco_cuenta2_id', 'valor_banco2']);
        });
    }
};
