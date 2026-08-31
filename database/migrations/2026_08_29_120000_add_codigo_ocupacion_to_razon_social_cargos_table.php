<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de cargos pasa a ser único y con código de ocupación.
 *
 * `codigo_ocupacion` es el código DANE de 4 dígitos que maneja ARL Sura en
 * `sel-services/cargo/ocupacionesDane` (401 ocupaciones). Sirve para normalizar
 * el cargo contra un catálogo oficial en vez de texto libre.
 *
 * `razon_social_id` se vuelve nullable: en NULL el cargo es genérico y lo ven
 * todas las razones sociales. Una razón social puede seguir agregando los suyos
 * cuando tenga algo que no está en la lista común.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razon_social_cargos', function (Blueprint $table) {
            $table->string('codigo_ocupacion', 6)->nullable()->after('cargo');
        });

        // SQL Server no deja cambiar la nulabilidad mientras la columna esté en
        // un índice, así que se quita, se altera y se vuelve a crear.
        DB::statement('DROP INDEX ix_rs_cargos_rs_activo ON razon_social_cargos');
        DB::statement('DROP INDEX ix_rs_cargos_rs_riesgo ON razon_social_cargos');
        DB::statement('ALTER TABLE razon_social_cargos ALTER COLUMN razon_social_id INT NULL');
        DB::statement('CREATE INDEX ix_rs_cargos_rs_activo ON razon_social_cargos (razon_social_id, activo)');
        DB::statement('CREATE INDEX ix_rs_cargos_rs_riesgo ON razon_social_cargos (razon_social_id, nivel_riesgo)');
    }

    public function down(): void
    {
        Schema::table('razon_social_cargos', function (Blueprint $table) {
            $table->dropColumn('codigo_ocupacion');
        });
    }
};
