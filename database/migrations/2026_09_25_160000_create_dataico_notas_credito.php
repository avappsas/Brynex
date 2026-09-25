<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas crédito electrónicas por API de Dataico.
 *
 * Una factura aceptada por la DIAN no se borra: se anula con una nota crédito
 * que la referencia. Hasta ahora anular un recibo en Brynex dejaba viva su FE,
 * y al re-facturar salía otra FE por la misma plata (FE2314/FE2326 y
 * FE2321/FE2327, sep-2026).
 *
 * Una fila por nota. El índice único sobre `dataico_envio_id` es la garantía de
 * que una FE no se anule dos veces: dos clics o un reintento chocan contra él.
 *
 * El consecutivo NC lo ponemos nosotros, igual que el de las facturas: la
 * numeración se creó en el portal el 25-sep-2026 (prefijo NC, desde 1, sin
 * resolución DIAN — las notas crédito no la necesitan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dataico_notas_credito')) {
            Schema::create('dataico_notas_credito', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aliado_id');
                $table->unsignedBigInteger('dataico_envio_id')->unique();

                // A qué recibo de Brynex y a qué FE anula.
                $table->unsignedBigInteger('numero_factura');
                $table->string('factura_dataico_numero', 50)->nullable();

                // enviando | enviado | error
                $table->string('estado', 20)->default('enviando');
                $table->string('razon', 30)->default('ANULACION');
                $table->string('motivo', 500)->nullable();
                $table->decimal('valor', 18, 2)->default(0);

                // Lo que devuelve Dataico.
                $table->string('numero', 50)->nullable();
                $table->string('dataico_uuid', 100)->nullable();
                $table->string('cude', 200)->nullable();

                $table->text('payload')->nullable();
                $table->text('respuesta')->nullable();
                $table->string('error_mensaje', 1000)->nullable();

                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->timestamps();

                $table->index(['aliado_id', 'numero_factura']);
            });
        }

        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('dataico_configuraciones', 'nc_prefijo')) {
                $table->string('nc_prefijo', 10)->nullable();
            }
            if (! Schema::hasColumn('dataico_configuraciones', 'nc_ultimo_numero')) {
                $table->unsignedInteger('nc_ultimo_numero')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataico_notas_credito');

        Schema::table('dataico_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['nc_prefijo', 'nc_ultimo_numero']);
        });
    }
};
