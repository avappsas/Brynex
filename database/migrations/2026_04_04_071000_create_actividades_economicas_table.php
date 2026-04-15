<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_economicas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ciiu', 10)->nullable();
            $table->string('nombre', 255);
            $table->tinyInteger('nivel_arl_sugerido')->default(1); // 1 al 5
            $table->boolean('activo')->default(true);
            $table->index('nivel_arl_sugerido');
        });

        // Seed con actividades basadas en niveles ARL de la imagen Brygar
        DB::table('actividades_economicas')->insert([
            // Nivel 1 – Riesgo Mínimo
            ['codigo_ciiu' => '8411',  'nombre' => 'Administración pública / Oficinas', 'nivel_arl_sugerido' => 1, 'activo' => 1],
            ['codigo_ciiu' => '9700',  'nombre' => 'Actividades domésticas',            'nivel_arl_sugerido' => 1, 'activo' => 1],
            ['codigo_ciiu' => '8530',  'nombre' => 'Docentes / Enseñanza',              'nivel_arl_sugerido' => 1, 'activo' => 1],
            ['codigo_ciiu' => '4711',  'nombre' => 'Ventas mostrador / Comercio',       'nivel_arl_sugerido' => 1, 'activo' => 1],
            ['codigo_ciiu' => '6201',  'nombre' => 'Tecnología / Sistemas',             'nivel_arl_sugerido' => 1, 'activo' => 1],
            ['codigo_ciiu' => '6491',  'nombre' => 'Actividades financieras',           'nivel_arl_sugerido' => 1, 'activo' => 1],

            // Nivel 2 – Riesgo Bajo
            ['codigo_ciiu' => '5610',  'nombre' => 'Meseros / Restaurantes',            'nivel_arl_sugerido' => 2, 'activo' => 1],
            ['codigo_ciiu' => '6612',  'nombre' => 'Asesores comerciales',              'nivel_arl_sugerido' => 2, 'activo' => 1],
            ['codigo_ciiu' => '4711',  'nombre' => 'Vendedores ext. / Distribución',    'nivel_arl_sugerido' => 2, 'activo' => 1],
            ['codigo_ciiu' => '5610',  'nombre' => 'Cocina / Preparación alimentos',    'nivel_arl_sugerido' => 2, 'activo' => 1],
            ['codigo_ciiu' => '1071',  'nombre' => 'Panadería',                         'nivel_arl_sugerido' => 2, 'activo' => 1],

            // Nivel 3 – Riesgo Medio
            ['codigo_ciiu' => '1610',  'nombre' => 'Ebanistas / Carpintería',           'nivel_arl_sugerido' => 3, 'activo' => 1],
            ['codigo_ciiu' => '8610',  'nombre' => 'Médicos / Servicios de salud',      'nivel_arl_sugerido' => 3, 'activo' => 1],
            ['codigo_ciiu' => '8610',  'nombre' => 'Enfermeras / Auxiliares de salud',  'nivel_arl_sugerido' => 3, 'activo' => 1],
            ['codigo_ciiu' => '4330',  'nombre' => 'Acabados de construcción',          'nivel_arl_sugerido' => 3, 'activo' => 1],
            ['codigo_ciiu' => '5224',  'nombre' => 'Estibadores / Cargue y descargue',  'nivel_arl_sugerido' => 3, 'activo' => 1],

            // Nivel 4 – Riesgo Alto
            ['codigo_ciiu' => '4922',  'nombre' => 'Taxistas / Transporte público',     'nivel_arl_sugerido' => 4, 'activo' => 1],
            ['codigo_ciiu' => '4923',  'nombre' => 'Conductores / Transporte carga',    'nivel_arl_sugerido' => 4, 'activo' => 1],
            ['codigo_ciiu' => '2511',  'nombre' => 'Soldadores / Metalmecánica',        'nivel_arl_sugerido' => 4, 'activo' => 1],
            ['codigo_ciiu' => '5310',  'nombre' => 'Mensajeros / Correo y Logística',   'nivel_arl_sugerido' => 4, 'activo' => 1],

            // Nivel 5 – Riesgo Máximo
            ['codigo_ciiu' => '4111',  'nombre' => 'Constructores / Obras civiles',     'nivel_arl_sugerido' => 5, 'activo' => 1],
            ['codigo_ciiu' => '0510',  'nombre' => 'Mineros / Extracción mineral',      'nivel_arl_sugerido' => 5, 'activo' => 1],
            ['codigo_ciiu' => '0610',  'nombre' => 'Petróleo / Gas',                    'nivel_arl_sugerido' => 5, 'activo' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_economicas');
    }
};
