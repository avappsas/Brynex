<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica por API de Dataico.
 *
 * Hasta hoy el módulo `facturacion.electronica` exportaba un Excel que alguien
 * subía a mano al portal de Dataico y luego marcaba `facturas.fe_marcada`.
 * Estas dos tablas reemplazan ese paso manual por un envío directo al API.
 *
 * `dataico_configuraciones`
 *   Una fila por razón social EMISORA. Arranca solo con BRYGAR SAS (aliado 2,
 *   razón social 42, NIT 901918923-2). El token va cifrado con el mismo patrón
 *   de `razon_social_credenciales`: cast `encrypted` + `$hidden`.
 *
 *   `banco_cuenta_id` es el criterio de selección, no un dato informativo:
 *   se factura electrónicamente lo que entró por esa cuenta. En BRYGAR eso es
 *   la cuenta 145 (Bancolombia 812-000169-10), por donde pasa el 95% del
 *   dinero. Ojo: `facturas.razon_social_id` NO sirve para esto — ahí va la
 *   razón social por la que está AFILIADO el cliente (la de la planilla PILA),
 *   no la que emite la factura.
 *
 * `dataico_envios`
 *   Una fila por grupo `numero_factura` de Brynex. El índice único
 *   (aliado_id, numero_factura) es lo que impide emitir dos veces la misma
 *   factura ante la DIAN si un job se reintenta o dos disparos se cruzan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dataico_configuraciones')) {
            Schema::create('dataico_configuraciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aliado_id')->index();
                // Razón social emisora (la que tiene la resolución DIAN).
                $table->unsignedBigInteger('razon_social_id')->index();

                $table->boolean('activo')->default(false);

                // 'factura' → se emite al quedar pagada.
                // 'diario'  → se emite en el cierre del día, a la hora_cierre.
                $table->string('modo', 20)->default('diario');
                $table->string('hora_cierre', 5)->default('20:00');

                // Criterio de selección: solo lo consignado en esta cuenta.
                $table->unsignedBigInteger('banco_cuenta_id')->nullable();

                // Corte de arranque: nada anterior a esta fecha_pago se emite.
                $table->date('fecha_inicio')->nullable();

                // Credenciales del API. El token cifrado pasa largo de 255.
                $table->string('dataico_account_id', 100)->nullable();
                $table->text('auth_token')->nullable();

                // Correo al que Dataico envía la representación gráfica cuando
                // el cliente no tiene correo propio. Si va nulo, se emite como
                // consumidor final sin envío.
                $table->string('correo_fallback', 150)->nullable();
                $table->boolean('enviar_email')->default(true);

                $table->string('observacion', 500)->nullable();
                $table->timestamps();

                $table->unique(['aliado_id', 'razon_social_id'], 'ux_dataico_cfg_aliado_rs');
            });
        }

        if (! Schema::hasTable('dataico_envios')) {
            Schema::create('dataico_envios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aliado_id')->index();
                $table->unsignedBigInteger('razon_social_id')->nullable();

                // Grupo de facturación de Brynex (facturas.numero_factura).
                $table->unsignedBigInteger('numero_factura');

                // pendiente | enviando | enviado | error | omitido
                $table->string('estado', 20)->default('pendiente');

                // Lo que se facturó: admon + afiliacion del grupo completo.
                $table->decimal('base_admon', 18, 2)->default(0);

                // Copia del adquiriente al momento de emitir, para poder
                // auditar sin depender de que el cliente no haya cambiado.
                $table->string('cliente_identificacion', 30)->nullable();
                $table->string('cliente_nombre', 250)->nullable();
                $table->boolean('es_consumidor_final')->default(false);

                // Lo que devuelve Dataico.
                $table->string('dataico_uuid', 100)->nullable();
                $table->string('dataico_numero', 50)->nullable();
                $table->string('cufe', 200)->nullable();

                $table->text('payload')->nullable();
                $table->text('respuesta')->nullable();
                $table->string('error_mensaje', 1000)->nullable();

                $table->integer('intentos')->default(0);
                $table->timestamp('enviado_at')->nullable();
                $table->timestamps();

                // Idempotencia: una sola FE por grupo de factura.
                $table->unique(['aliado_id', 'numero_factura'], 'ux_dataico_envio_factura');
                $table->index(['aliado_id', 'estado'], 'ix_dataico_envio_estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dataico_envios');
        Schema::dropIfExists('dataico_configuraciones');
    }
};
