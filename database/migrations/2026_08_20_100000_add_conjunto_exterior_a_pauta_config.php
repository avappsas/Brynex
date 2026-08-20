<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo conjunto de anuncios: colombianos en el exterior.
 *
 * La segmentación vive en el CONJUNTO, no en el anuncio, así que no se puede decir "esta pieza
 * va a España y esta a Colombia" dentro del mismo. Hacen falta dos, cada uno con su público y
 * su presupuesto — que además es lo que permite comparar después qué mercado trae
 * conversaciones más baratas, en vez de un promedio que no dice nada.
 *
 * Es un segmento distinto y no una variación del actual: quien aporta a pensión desde afuera
 * tiene ingreso en otra moneda y una motivación de largo plazo, así que no se retira "porque
 * este mes no me alcanzó", que es la razón por la que se va la mayoría de los de acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->boolean('exterior_activo')->default(false)->after('meta_adset_permanente_id');
            $table->string('meta_adset_exterior_id', 60)->nullable()->after('exterior_activo');
            $table->string('exterior_pais', 2)->nullable()->after('meta_adset_exterior_id');
            // Meta eliminó en 2022 la segmentación por "expatriados", así que a un colombiano
            // en España solo se le llega por proxy de interés. Se guarda el id para no
            // depender de buscarlo por nombre en cada creación.
            $table->string('exterior_interes_id', 40)->nullable()->after('exterior_pais');
            $table->string('exterior_interes_nombre', 120)->nullable()->after('exterior_interes_id');
            $table->decimal('exterior_presupuesto_diario_cop', 12, 2)->nullable()->after('exterior_interes_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->dropColumn([
                'exterior_activo', 'meta_adset_exterior_id', 'exterior_pais',
                'exterior_interes_id', 'exterior_interes_nombre', 'exterior_presupuesto_diario_cop',
            ]);
        });
    }
};
