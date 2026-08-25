<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marcas de control del envío automático de recordatorios.
     *
     * Guardan la fecha de corte para la que ya se envió cada mensaje, no la fecha
     * del envío: así el comando puede correr todos los días sin repetirle nada al
     * deudor, y si el cron se cae un par de días recupera el envío pendiente en
     * vez de saltárselo (que es lo que pasaría comparando contra "hoy").
     */
    public function up(): void
    {
        if (Schema::connection('finanzas')->hasColumn('finanzas_prestamos', 'aviso_previo_enviado_para')) {
            return;
        }

        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->date('aviso_previo_enviado_para')->nullable()->after('alertas_activas');
            $table->date('cobro_enviado_para')->nullable()->after('aviso_previo_enviado_para');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('finanzas')->hasColumn('finanzas_prestamos', 'aviso_previo_enviado_para')) {
            return;
        }

        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->dropColumn(['aviso_previo_enviado_para', 'cobro_enviado_para']);
        });
    }
};
