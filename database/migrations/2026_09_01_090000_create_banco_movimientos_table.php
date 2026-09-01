<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos bancarios traídos del API del banco.
 *
 * Es el espejo del extracto, no un libro contable: aquí entra lo que el banco
 * dice que pasó, tal cual, y nunca se edita a mano. El libro de BryNex sigue
 * siendo `consignaciones` + `saldos_banco`; esta tabla existe para poder
 * cruzarlos y descubrir dónde no coinciden.
 *
 * La idempotencia se apoya en `huella`, no en el id del banco: varios APIs de
 * extracto no devuelven un identificador estable por movimiento, y sin una
 * llave propia cada corrida duplicaría el día entero. La huella se calcula en
 * el DTO (App\Services\Banco\MovimientoBanco) y es NOT NULL a propósito —
 * SQL Server solo admite un NULL por índice único, así que una huella nula
 * haría fallar la segunda fila sin llave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banco_movimientos', function (Blueprint $table) {
            $table->id();

            // Multi-tenant. Mismo tipo que banco_cuentas.aliado_id (int), no
            // bigint: la FK a aliados no se declara aquí por la misma razón
            // que en banco_cuentas y consignaciones.
            $table->unsignedInteger('aliado_id')->index();
            $table->unsignedBigInteger('banco_cuenta_id');

            // Qué adaptador trajo la fila: 'fake' mientras no llegue el banco.
            $table->string('proveedor', 30)->default('fake');

            // Identificador del movimiento en el banco, cuando lo hay.
            $table->string('id_externo', 100)->nullable();

            // Llave de deduplicación propia. Ver comentario de cabecera.
            $table->string('huella', 64);

            $table->date('fecha');                          // fecha contable
            $table->dateTime('fecha_hora')->nullable();     // si el API la trae

            $table->string('tipo', 10);                     // credito | debito
            $table->decimal('valor', 18, 2);                // siempre positivo
            $table->decimal('saldo_despues', 18, 2)->nullable();

            $table->string('descripcion', 255)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->string('canal', 60)->nullable();        // sucursal, PSE, QR…

            // Quién consignó, cuando el banco lo informa. Es lo que permite
            // amarrar una entrada suelta a un cliente por cédula/NIT.
            $table->string('contraparte_nombre', 150)->nullable();
            $table->string('contraparte_documento', 30)->nullable();

            // Estado del cruce contra el libro de BryNex.
            $table->string('estado_conciliacion', 20)->default('pendiente');
            $table->unsignedBigInteger('consignacion_id')->nullable();
            $table->unsignedInteger('conciliado_por')->nullable();
            $table->dateTime('conciliado_at')->nullable();

            // Respuesta cruda del banco. Cuando un cruce salga raro, esto es
            // lo único que dice qué mandó el banco de verdad.
            $table->text('payload')->nullable();

            $table->timestamps();

            $table->foreign('banco_cuenta_id')->references('id')->on('banco_cuentas');

            // Idempotencia: dos corridas del mismo rango no duplican.
            $table->unique(['banco_cuenta_id', 'huella'], 'ux_banco_mov_cuenta_huella');

            // Cómo se consulta: por cuenta y rango de fechas, y la bandeja de
            // lo que falta conciliar.
            $table->index(['banco_cuenta_id', 'fecha'], 'ix_banco_mov_cuenta_fecha');
            $table->index(['aliado_id', 'estado_conciliacion'], 'ix_banco_mov_aliado_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_movimientos');
    }
};
