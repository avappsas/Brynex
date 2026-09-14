<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La clave del portal de la EPS sale del módulo de claves de BryNex
 * (`clave_accesos`), como en ARL Sura, y no de una copia propia: así cambiarla
 * allí basta. `eps_usuarios_portal` queda solo como sustituto manual.
 *
 * Lo que sí se guarda por empresa es la memoria del portal: el asesor que la EPS
 * le asignó, la última sesión y la huella de una clave que el portal rechazó.
 * Esa huella es la protección contra bloqueos: una clave mala del módulo no se
 * vuelve a probar hasta que alguien la cambie (la huella deja de coincidir).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE eps_portal_empresas ALTER COLUMN usuario_portal_id BIGINT NULL');

        Schema::table('eps_portal_empresas', function (Blueprint $table) {
            $table->string('clave_fallida_hash', 64)->nullable();
            $table->string('ultimo_error', 300)->nullable();
            $table->timestamp('ultima_sesion_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eps_portal_empresas', function (Blueprint $table) {
            $table->dropColumn(['clave_fallida_hash', 'ultimo_error', 'ultima_sesion_at']);
        });
    }
};
