<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amarra las claves de portales críticos a la ficha maestra (el NIT) en vez de
 * a la fila de razón social de cada aliado.
 *
 * Ese es el mecanismo de "lo que modifique uno le actualiza al otro": la clave
 * de la DIAN de ELITES CREACIONES es una sola aunque la usen Grupo Fecop y
 * BRYGAR. Si cada aliado guardara la suya, la que quedó vieja no se nota hasta
 * que alguien no puede declarar.
 *
 * `aliado_id` se queda como está (NOT NULL) a propósito: pasa de ser la llave
 * de lectura a ser un dato de auditoría — qué aliado registró la clave. La
 * lectura ahora es por `ficha_id`. No se toca la nulabilidad porque la columna
 * tiene dos índices encima y en SQL Server eso obliga a tumbarlos y recrearlos
 * sobre la base de producción, riesgo que no compensa para una tabla que hoy
 * tiene 0 registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('razon_social_credenciales')) {
            return;
        }

        Schema::table('razon_social_credenciales', function (Blueprint $table) {
            if (! Schema::hasColumn('razon_social_credenciales', 'ficha_id')) {
                $table->unsignedBigInteger('ficha_id')->nullable()->after('razon_social_id');
                $table->index(['ficha_id', 'tipo'], 'ix_rsc_ficha_tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('razon_social_credenciales', function (Blueprint $table) {
            $table->dropIndex('ix_rsc_ficha_tipo');
            $table->dropColumn('ficha_id');
        });
    }
};
