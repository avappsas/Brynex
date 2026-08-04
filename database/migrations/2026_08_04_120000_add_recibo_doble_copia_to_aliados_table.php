<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor por aliado: imprimir el recibo dos veces en la misma hoja carta
 * (copia CLIENTE arriba, copia EMPRESA abajo) para que se parta por la mitad.
 *
 * La copia EMPRESA siempre sale detallada, con el desglose de administración,
 * seguro, 4x1000 y los saldos. Ver resources/views/admin/facturacion/recibo.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->boolean('recibo_doble_copia')->default(false)->after('imagen_planes');
        });

        // Por ahora solo GiMave Integral lo tiene activo; los demás lo prenden
        // desde Configuración → Parámetros Especiales.
        DB::table('aliados')->where('nombre', 'GiMave Integral')->update(['recibo_doble_copia' => true]);
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('recibo_doble_copia');
        });
    }
};
