<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las APIs de Enlace Operativo piden tres datos para autenticar
     * (usuario, contraseña de 4 dígitos y clave secreta), no dos. Además la
     * cuenta es una sola para todas las razones sociales del aliado, así que
     * `razon_social_id` pasa a ser opcional: NULL = credencial del aliado.
     */
    public function up(): void
    {
        Schema::table('operadores_credenciales', function (Blueprint $table) {
            if (!Schema::hasColumn('operadores_credenciales', 'contrasena')) {
                $table->text('contrasena')->nullable()->after('usuario');
            }
            if (!Schema::hasColumn('operadores_credenciales', 'clave_secreta_expira_at')) {
                $table->date('clave_secreta_expira_at')->nullable()->after('clave_secreta');
            }
        });

        // Nullability: sin doctrine/dbal hay que ir por SQL nativo.
        // `clave_secreta` también se afloja porque una credencial puede
        // guardarse a medias mientras se consigue la clave del tablero.
        DB::statement('ALTER TABLE operadores_credenciales ALTER COLUMN razon_social_id BIGINT NULL');
        DB::statement('ALTER TABLE operadores_credenciales ALTER COLUMN clave_secreta NVARCHAR(MAX) NULL');
    }

    public function down(): void
    {
        Schema::table('operadores_credenciales', function (Blueprint $table) {
            $table->dropColumn(['contrasena', 'clave_secreta_expira_at']);
        });

        // No se revierte la nullability: revertirla fallaría si ya existen
        // credenciales a nivel de aliado con razon_social_id NULL.
    }
};
