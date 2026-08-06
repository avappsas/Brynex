<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancia de que el usuario autorizó el tratamiento de sus datos.
 *
 * Se guarda la versión aceptada, no solo un booleano: cuando el aviso cambie,
 * la versión nueva no coincidirá con la aceptada y el sistema volverá a pedirla.
 * Con un booleano habría que acordarse de resetearlo a mano en 82 usuarios.
 *
 * La IP y la fecha son lo que convierte la aceptación en prueba. Sin ellas es
 * la palabra de uno contra la del otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('acepto_tratamiento_at')->nullable()->after('ultimo_dispositivo_id');
            $table->string('acepto_tratamiento_ip', 45)->nullable()->after('acepto_tratamiento_at');
            $table->string('acepto_tratamiento_version', 20)->nullable()->after('acepto_tratamiento_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['acepto_tratamiento_at', 'acepto_tratamiento_ip', 'acepto_tratamiento_version']);
        });
    }
};
