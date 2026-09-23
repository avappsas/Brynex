<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El candado de la revisión de cajas pasa a ser por empresa, no por aliado.
 *
 * Se hizo por aliado y resultó ser la unidad equivocada: la clave del portal
 * vive en la razón social —si BryNex guardó la de una empresa, la usa cualquier
 * aliado que la tenga activa—, y quien decide si hay algo que revisar es la
 * empresa, no quien la factura. Con el candado por aliado, revisar UNA empresa
 * desde el portal daba el día por hecho y el barrido nocturno se saltaba a las
 * demás de ese aliado.
 *
 * Además, así una consulta al portal sirve para todos los aliados que compartan
 * la empresa, en vez de repetir el mismo recorrido una vez por cada uno.
 *
 * `aliado_id` se queda, pero solo como dato de quién la disparó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_revisiones', function (Blueprint $table) {
            $table->string('nit', 20)->nullable()->after('entidad');
        });

        // Las corridas viejas son del candado por aliado: sin NIT no encajan en
        // el nuevo índice y tampoco sirven para nada, pero no se borran — se
        // marcan para que no choquen entre ellas.
        DB::table('caja_revisiones')->whereNull('nit')->update(['nit' => DB::raw("'aliado-' + CAST(aliado_id AS varchar(10))")]);

        Schema::table('caja_revisiones', function (Blueprint $table) {
            $table->dropUnique('UQ_caja_revisiones_dia');
            $table->unique(['entidad', 'nit', 'fecha'], 'UQ_caja_revisiones_empresa_dia');
        });
    }

    public function down(): void
    {
        Schema::table('caja_revisiones', function (Blueprint $table) {
            $table->dropUnique('UQ_caja_revisiones_empresa_dia');
            $table->unique(['aliado_id', 'entidad', 'fecha'], 'UQ_caja_revisiones_dia');
            $table->dropColumn('nit');
        });
    }
};
