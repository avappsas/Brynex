<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca si el aportante está exonerado de SENA e ICBF (art. 114-1 ET).
 *
 * Hasta ahora BryNex daba por hecho que todo dependiente lo estaba: liquidaba
 * la salud al 4% y nunca los parafiscales. Eso deja corta la planilla de un
 * aportante no exonerado —un sindicato o cualquier entidad del art. 23 del ET,
 * que al no ser contribuyente de renta no accede a la exoneración—, donde la
 * salud va al 12,5% y encima se pagan SENA (2%) e ICBF (3%).
 *
 * El default es `true` porque es la situación de casi todos los clientes y es
 * como se venía calculando: la columna no mueve a nadie hasta que se apague a
 * mano en la ficha de la empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('exonerado_parafiscales')->default(true)->after('factura_electronica');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('exonerado_parafiscales');
        });
    }
};
