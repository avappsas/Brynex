<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anulación de incapacidades que nunca entraron a trámite.
 *
 * Hasta hoy la máquina de estados solo tenía dos salidas y las dos son
 * respuestas de la ENTIDAD: 'rechazado' y 'negada'. Una incapacidad que muere
 * del lado del aliado — el cliente nunca mandó la documentación, se creó por
 * error, quedó duplicada — no tenía forma de cerrarse, así que se quedaba
 * "activa" para siempre engordando el contador de Activas y el de Sin gestión
 * +7d (el caso #149, con 122 días sin gestión y sin un solo dato cargado).
 *
 * El motivo va en columna aparte y NO como estados distintos: la lista de
 * motivos va a crecer, y cada motivo-como-estado obliga a tocar las seis
 * listas de estados repartidas entre modelo, controlador, vista e informes.
 *
 * `estado_previo_anulacion` existe para poder reabrir: devuelve la incapacidad
 * al punto exacto del flujo donde estaba, no a 'recibido' a la fuerza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incapacidades', function (Blueprint $table) {
            $table->string('motivo_anulacion', 40)->nullable()->after('estado');
            $table->string('anulacion_observacion', 500)->nullable()->after('motivo_anulacion');
            $table->string('estado_previo_anulacion', 40)->nullable()->after('anulacion_observacion');
            $table->unsignedBigInteger('anulada_por')->nullable()->after('estado_previo_anulacion');
            $table->dateTime('anulada_en')->nullable()->after('anulada_por');
        });
    }

    public function down(): void
    {
        Schema::table('incapacidades', function (Blueprint $table) {
            $table->dropColumn([
                'motivo_anulacion',
                'anulacion_observacion',
                'estado_previo_anulacion',
                'anulada_por',
                'anulada_en',
            ]);
        });
    }
};
