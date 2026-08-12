<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token propio para crear anuncios, separado del de publicación en la página.
 *
 * Meta exige que quien crea un anuncio haya aceptado la certificación de no discriminación.
 * El token de redes del aliado es un token de PÁGINA generado por un usuario del sistema
 * ("Brynex", admin del negocio), y un usuario del sistema no puede aceptar esa certificación:
 * no tiene sesión ni navegador. Certificar a los usuarios humanos no sirve, porque ante Meta
 * el anuncio no lo crea ninguno de ellos.
 *
 * La salida es guardar aquí un token de un usuario humano YA certificado, que se usa solo
 * para la pauta. Si está vacío se sigue usando el de la página, así que nada cambia para
 * quien no lo configure.
 *
 * Va cifrado, igual que el resto de tokens (ver RedSocialConfig y WhatsappConfig).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->text('access_token_ads')->nullable()->after('ad_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->dropColumn('access_token_ads');
        });
    }
};
