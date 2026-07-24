<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoción real de precio de afiliación, por plan (o general, plan_id null). Se resuelve
 * dentro de CotizacionPublicaService::cotizar() — la MISMA fuente que usan la web pública,
 * el asistente de WhatsApp y el piloto de marketing — así que activar una promoción aquí
 * la refleja automáticamente en los tres a la vez, y al vencer vuelve sola al precio normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->decimal('promocion_costo_afiliacion', 12, 2)->nullable()->after('costo_afiliacion');
            $table->date('promocion_vencimiento')->nullable()->after('promocion_costo_afiliacion');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn(['promocion_costo_afiliacion', 'promocion_vencimiento']);
        });
    }
};
