<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            if (! Schema::hasColumn('banco_cuentas', 'facturacion')) {
                // Aparece en el selector de cuenta al facturar / registrar ingresos
                $table->boolean('facturacion')->default(false)->after('cobro');
            }
            if (! Schema::hasColumn('banco_cuentas', 'incapacidad')) {
                // Marca informativa: cuenta usada para reportar entradas de incapacidades
                $table->boolean('incapacidad')->default(false)->after('facturacion');
            }
        });

        // Hasta ahora el modal de facturar mostraba TODAS las cuentas activas.
        // Se marcan todas para que el despliegue no cambie nada de golpe; el
        // aliado va desmarcando desde Configuración → Cuentas Bancarias.
        DB::table('banco_cuentas')->where('activo', true)->update(['facturacion' => true]);
    }

    public function down(): void
    {
        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->dropColumn(['facturacion', 'incapacidad']);
        });
    }
};
