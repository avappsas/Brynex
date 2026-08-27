<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ambiente y consecutivo para la emisión por API.
 *
 * `env` es lo que destrabó el bloqueo del ticket #47903972886: sin
 * `"env": "PRODUCCION"` dentro de `invoice`, el API respondía 401 «No se
 * encuentra numeración» aunque la numeración estuviera vigente y en uso. No
 * está en ninguna documentación pública; lo mandó el soporte de Dataico el
 * 27-ago-2026 en el JSON de ejemplo.
 *
 * `ultimo_numero` existe porque el API **exige** `number`: el consecutivo lo
 * ponemos nosotros, no Dataico. Guarda el último emitido con éxito para saber
 * cuál sigue. Se puede dejar en null y el sistema lo detecta consultando el
 * API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->string('env', 20)->default('PRODUCCION')->after('resolucion');
            $table->unsignedInteger('ultimo_numero')->nullable()->after('env');
        });
    }

    public function down(): void
    {
        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['env', 'ultimo_numero']);
        });
    }
};
