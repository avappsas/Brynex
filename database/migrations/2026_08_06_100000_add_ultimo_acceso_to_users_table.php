<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Último acceso de cada usuario, para poder responder "¿esta cuenta se usa?"
 * sin recorrer el histórico de accesos_usuario en cada listado.
 *
 * El detalle completo vive en `accesos_usuario`; aquí solo queda el último,
 * que es lo que se pinta en la pantalla de usuarios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ultimo_acceso')->nullable()->after('activo');
            // 45 caracteres: cabe una IPv6 completa con notación mapeada.
            $table->string('ultima_ip', 45)->nullable()->after('ultimo_acceso');
            $table->string('ultimo_dispositivo_id', 64)->nullable()->after('ultima_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ultimo_acceso', 'ultima_ip', 'ultimo_dispositivo_id']);
        });
    }
};
