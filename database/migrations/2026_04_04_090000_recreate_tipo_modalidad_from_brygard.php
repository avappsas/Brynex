<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Recrea tipo_modalidad usando los registros exactos de Brygar_BD (IDs originales).
 * La tabla anterior usaba tinyIncrements (TINYINT UNSIGNED) que no soporta IDs
 * negativos ni el ID -100. Se migra a smallInteger para soportar el rango completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Quitar FK en contratos que apunta a tipo_modalidad
        Schema::table('contratos', function (Blueprint $table) {
            try {
                $table->dropForeign(['tipo_modalidad_id']);
            } catch (\Exception $e) {
                // Ya no existe, continúa
            }
        });

        // 2. Recrear la tabla con smallInteger para soportar IDs negativos
        Schema::dropIfExists('tipo_modalidad');
        Schema::create('tipo_modalidad', function (Blueprint $table) {
            $table->smallInteger('id')->primary();          // Soporta -32768 a 32767
            $table->string('tipo_modalidad', 30);           // Código corto (E, I Act, K, TP7...)
            $table->string('observacion', 100)->nullable(); // Descripción larga
            $table->smallInteger('orden')->default(99);     // Orden de visualización
            $table->string('modalidad', 50)->nullable();    // Grupo/categoría
            $table->boolean('activo')->default(true);
        });

        // 3. Insertar los 19 registros exactos de Brygar_BD.[dbo].[tipo_modalidad]
        // Ordenados por 'orden' tal como aparecen en el sistema original
        DB::table('tipo_modalidad')->insert([
            ['id' => -100, 'tipo_modalidad' => '',         'observacion' => 'Todos',                    'orden' => 1,  'modalidad' => '*',             'activo' => 1],
            ['id' =>    0, 'tipo_modalidad' => 'E',        'observacion' => 'Dependiente E',            'orden' => 2,  'modalidad' => 'Tipo E',        'activo' => 1],
            ['id' =>   11, 'tipo_modalidad' => 'I Act',    'observacion' => 'Independientes Mes Actual','orden' => 3,  'modalidad' => 'Indep_Actual',  'activo' => 1],
            ['id' =>   10, 'tipo_modalidad' => 'I Venc',   'observacion' => 'Independientes',           'orden' => 3,  'modalidad' => 'Indepependiente','activo' => 1],
            ['id' =>    8, 'tipo_modalidad' => 'Y',        'observacion' => 'ARL Tipo Y',               'orden' => 4,  'modalidad' => 'Planilla Y',    'activo' => 1],
            ['id' =>    6, 'tipo_modalidad' => 'EPS',      'observacion' => 'Solo Eps',                 'orden' => 5,  'modalidad' => 'Solo EPS',      'activo' => 1],
            ['id' =>    7, 'tipo_modalidad' => 'EPS+ARL',  'observacion' => 'Solo Eps y planilla k',   'orden' => 6,  'modalidad' => 'Solo EPS',      'activo' => 1],
            ['id' =>   -1, 'tipo_modalidad' => 'K',        'observacion' => 'Estudiante K',             'orden' => 7,  'modalidad' => 'Tipo K',        'activo' => 1],
            ['id' =>    1, 'tipo_modalidad' => 'TP(7)',    'observacion' => 'Tiempo Parcial (7)',       'orden' => 8,  'modalidad' => 'Tiempo Parcial', 'activo' => 1],
            ['id' =>    2, 'tipo_modalidad' => 'TP(14)',   'observacion' => 'Tiempo Parcial (14)',      'orden' => 9,  'modalidad' => 'Tiempo Parcial', 'activo' => 1],
            ['id' =>    3, 'tipo_modalidad' => 'TP(21)',   'observacion' => 'Tiempo Parcial (21)',      'orden' => 10, 'modalidad' => 'Tiempo Parcial', 'activo' => 1],
            ['id' =>    4, 'tipo_modalidad' => 'TP(30)',   'observacion' => 'Tiempo Parcial (30)',      'orden' => 11, 'modalidad' => 'Tiempo Parcial', 'activo' => 1],
            ['id' =>   -6, 'tipo_modalidad' => 'TP(7-14)', 'observacion' => 'Tiempo Parcial (7-14)',   'orden' => 12, 'modalidad' => '2Tiempo Parcial','activo' => 1],
            ['id' =>   -7, 'tipo_modalidad' => 'TP(7-21)', 'observacion' => 'Tiempo Parcial (7-21)',   'orden' => 13, 'modalidad' => '2Tiempo Parcial','activo' => 1],
            ['id' =>   -8, 'tipo_modalidad' => 'TP(14-21)','observacion' => 'Tiempo Parcial (14-21)', 'orden' => 14, 'modalidad' => '2Tiempo Parcial','activo' => 1],
            ['id' =>   -4, 'tipo_modalidad' => 'SimpleP',  'observacion' => 'TipoE- (1 dia pension)',  'orden' => 15, 'modalidad' => 'E+1DiaAFP',     'activo' => 1],
            ['id' =>    5, 'tipo_modalidad' => 'CS',       'observacion' => 'Contribucion Solidaria',  'orden' => 16, 'modalidad' => 'Contribusion',   'activo' => 1],
            ['id' =>   12, 'tipo_modalidad' => 'Ing-Ret',  'observacion' => 'Ingreso-Retiro',          'orden' => 17, 'modalidad' => 'Ingreso-Retiro',  'activo' => 1],
            ['id' =>   13, 'tipo_modalidad' => 'UPC',      'observacion' => 'UPC',                     'orden' => 18, 'modalidad' => 'UPC',             'activo' => 1],
        ]);

        // 4. Ajustar la FK en contratos a smallInteger
        Schema::table('contratos', function (Blueprint $table) {
            // Cambiar el tipo de la columna tipo_modalidad_id para aceptar smallInt con signo
            $table->smallInteger('tipo_modalidad_id')->nullable()->change();
        });

        // 5. Volver a agregar la FK
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreign('tipo_modalidad_id')
                  ->references('id')
                  ->on('tipo_modalidad')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            try { $table->dropForeign(['tipo_modalidad_id']); } catch (\Exception $e) {}
        });

        Schema::dropIfExists('tipo_modalidad');

        // Recrear la tabla original simple
        Schema::create('tipo_modalidad', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedTinyInteger('tipo_modalidad_id')->nullable()->change();
            $table->foreign('tipo_modalidad_id')->references('id')->on('tipo_modalidad')->nullOnDelete();
        });
    }
};
