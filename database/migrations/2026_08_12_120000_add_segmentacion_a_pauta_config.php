<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segmentación geográfica y de edad de la pauta.
 *
 * Hasta ahora estaba quemada en MetaAdsService: toda Colombia, 18 a 65 años. Con un
 * presupuesto de 5.000 COP/día eso reparte el alcance entre 50 millones de personas y
 * encarece cada conversación — para una empresa que opera desde Cali no tiene sentido.
 *
 * `ciudades` guarda los nombres tal como se buscan en Meta (["Cali", "Palmira", ...]);
 * las claves internas de Meta se resuelven al crear la campaña y se cachean, porque
 * cambian entre cuentas y no se pueden escribir a mano.
 *
 * Vacío = todo el país, que es el comportamiento anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->json('ciudades')->nullable()->after('audiencias_sync_at');
            $table->json('ciudades_claves')->nullable()->after('ciudades');
            $table->unsignedTinyInteger('edad_min')->default(25)->after('ciudades_claves');
            $table->unsignedTinyInteger('edad_max')->default(55)->after('edad_min');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->dropColumn(['ciudades', 'ciudades_claves', 'edad_min', 'edad_max']);
        });
    }
};
