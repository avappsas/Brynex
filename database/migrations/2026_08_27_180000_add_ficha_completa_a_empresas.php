<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha completa de la empresa: documento, nombre legal, ubicación y contacto.
 *
 * `empresas` mezcla sociedades con empleadores persona natural, y hasta hoy
 * tenía un solo campo de nombre. Cuando el empleador es una persona con
 * establecimiento —MAXIDROGAS, CHOMPAS, TORQUE— ahí quedaba el nombre del
 * negocio, y eso llegaba tal cual a la factura electrónica. Ante la DIAN el
 * adquiriente es el dueño, identificado por su cédula, no el establecimiento.
 *
 * Por eso se separan:
 *   `empresa`      → el nombre del negocio. Es lo que se ve en todo Brynex y
 *                    no cambia de significado, así que ninguna pantalla se
 *                    entera de esta migración.
 *   `nombre_legal` → el nombre según el documento, que es el que viaja a la
 *                    DIAN. Lo trae el registro oficial por la cédula.
 *
 * `tipo_documento` faltaba: `nit` es un bigint sin tipo, y una CC y un NIT con
 * el mismo número son cosas distintas. Se ofrecen los cuatro documentos
 * numéricos (CC, NIT, CE, PT); un pasaporte no cabría en la columna.
 *
 * `contacto_celular` separa al encargado de la empresa: si una empresa tiene a
 * alguien encargado de la seguridad social, las cuentas de cobro y las
 * planillas van a su celular y no al número general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('tipo_documento', 5)->nullable()->after('nit');
            $table->string('nombre_legal', 255)->nullable()->after('empresa');
            $table->string('contacto_celular', 50)->nullable()->after('contacto');
            $table->unsignedBigInteger('departamento_id')->nullable()->after('direccion');
            $table->unsignedBigInteger('municipio_id')->nullable()->after('departamento_id');
        });

        // Arranque razonable para lo que ya existe: 9-10 dígitos empezando en
        // 8 o 9 es un NIT de sociedad; el resto son cédulas.
        DB::statement("
            UPDATE empresas
               SET tipo_documento = CASE
                     WHEN nit IS NULL THEN NULL
                     WHEN LEN(CAST(nit AS VARCHAR(20))) BETWEEN 9 AND 10
                      AND LEFT(CAST(nit AS VARCHAR(20)), 1) IN ('8','9') THEN 'NIT'
                     ELSE 'CC'
                   END
        ");
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['tipo_documento', 'nombre_legal', 'contacto_celular', 'departamento_id', 'municipio_id']);
        });
    }
};
