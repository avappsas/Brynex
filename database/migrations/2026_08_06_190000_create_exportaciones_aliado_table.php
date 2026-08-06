<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas de datos a un aliado que se va: quién la pidió, cuándo, con qué
 * corte y qué archivo salió.
 *
 * La tabla hace tres oficios a la vez, y es a propósito:
 *
 *  1. **Reto de confirmación.** El código que llega por WhatsApp vive aquí, no
 *     en caché. `CACHE_DRIVER=file` y en producción `storage/` se queda sin
 *     escritura para Apache cada vez que artisan corre como root — un reto que
 *     no se puede guardar es un reto que bloquea la exportación.
 *  2. **Bitácora.** Una entrega de datos personales de 10.000 personas tiene
 *     que quedar registrada con nombre propio, IP y fecha.
 *  3. **Traza.** Guarda el sha256 del ZIP y el token firmado que va dentro del
 *     LEEME. Si mañana aparece un archivo, se compara el hash y se sabe si
 *     salió de aquí y de cuál entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportaciones_aliado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('solicitado_por');

            // pendiente → confirmado → generado. vencido/cancelado/fallido son finales.
            $table->string('estado', 20)->default('pendiente');

            // Reto por WhatsApp. El código se guarda hasheado: son 6 dígitos y
            // esta BD la comparten 12 aliados.
            $table->string('codigo_hash', 100)->nullable();
            $table->dateTime('codigo_expira_at')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('confirmado_at')->nullable();

            // Resultado
            $table->string('archivo', 300)->nullable();
            $table->string('archivo_hash', 64)->nullable();
            $table->unsignedBigInteger('archivo_bytes')->nullable();
            // Contraseña del ZIP cifrada con APP_KEY: se muestra una vez al
            // generar, y poder volver a verla evita regenerar 400.000 filas
            // porque alguien cerró la pestaña.
            $table->text('zip_password')->nullable();
            $table->unsignedInteger('filas_total')->nullable();
            $table->text('resumen')->nullable();            // JSON: filas por archivo
            $table->string('traza_token', 120)->nullable();

            // Quién y desde dónde
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('descargas')->default(0);
            $table->dateTime('ultima_descarga_at')->nullable();
            $table->dateTime('purgado_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('solicitado_por')->references('id')->on('users');
            $table->index(['estado', 'codigo_expira_at']);
            $table->index('aliado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportaciones_aliado');
    }
};
