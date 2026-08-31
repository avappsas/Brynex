<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada credencial pasa a apuntar al usuario del portal que la usa.
 *
 * `arl_credenciales` queda como el vínculo empresa → usuario: sigue sabiendo de
 * qué NIT, póliza y aliado se trata, pero la contraseña vive una sola vez en
 * `arl_usuarios_portal`. Las columnas viejas se conservan por ahora para no
 * romper filas que todavía no estén migradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arl_credenciales', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_portal_id')->nullable()->after('aliado_id');
            $table->foreign('usuario_portal_id')->references('id')->on('arl_usuarios_portal');
        });

        // Se pasan las credenciales que ya existen. Va por el modelo y no por
        // SQL crudo porque la contraseña está cifrada por Eloquent.
        foreach (\App\Models\ArlCredencial::all() as $credencial) {
            $tipo    = $credencial->getRawOriginal('tipo_documento') ?: 'C';
            $usuario = trim((string) $credencial->getRawOriginal('usuario'));

            if ($usuario === '') {
                continue;
            }

            $portal = \App\Models\ArlUsuarioPortal::firstOrNew([
                'tipo_documento' => $tipo,
                'usuario'        => $usuario,
            ]);

            // Si dos empresas traían claves distintas del mismo usuario, gana la
            // de la credencial que funcionó por última vez: es la vigente.
            if (! $portal->exists || ! $credencial->ultimo_error) {
                $portal->contrasena = $credencial->contrasena;
                $portal->activo     = (bool) $credencial->activo;
            }

            $portal->save();

            $credencial->usuario_portal_id = $portal->id;
            $credencial->saveQuietly();
        }

        // Las columnas viejas dejan de ser obligatorias: en las credenciales
        // nuevas el login vive en `arl_usuarios_portal` y aquí queda vacío. Se
        // hace con ALTER nativo para no depender de doctrine/dbal.
        DB::statement('ALTER TABLE arl_credenciales ALTER COLUMN contrasena nvarchar(max) NULL');
        DB::statement('ALTER TABLE arl_credenciales ALTER COLUMN usuario nvarchar(30) NULL');
    }

    public function down(): void
    {
        Schema::table('arl_credenciales', function (Blueprint $table) {
            $table->dropForeign(['usuario_portal_id']);
            $table->dropColumn('usuario_portal_id');
        });
    }
};
