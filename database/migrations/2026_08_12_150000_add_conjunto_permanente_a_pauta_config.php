<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conjunto de anuncios permanente ("evergreen").
 *
 * El esquema anterior creaba una campaña nueva por cada pieza. Meta necesita ~50
 * conversiones semanales por conjunto para salir de la fase de aprendizaje; con una
 * campaña nueva cada día el aprendizaje se reiniciaba a diario y nunca se llegaba —
 * se pagaba precio de novato de forma permanente.
 *
 * Ahora hay UN conjunto por aliado que acumula historial, y las piezas entran como
 * anuncios (creatividades) dentro de él. El presupuesto vive en el conjunto, no en
 * la pieza.
 *
 * `creatividades_max` acota cuántos anuncios se dejan activos a la vez y
 * `piezas_semana_max` cuántas piezas NUEVAS pueden entrar por semana. Son dos cosas
 * distintas: el piloto genera una pieza diaria, así que sin el segundo límite entrarían
 * siete por semana y el presupuesto se partiría en siete — con 50.000 semanales, ninguna
 * juntaría datos suficientes para saber si sirve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->decimal('presupuesto_semanal_cop', 12, 2)->nullable()->after('presupuesto_diario_default_cop');
            $table->string('meta_campana_permanente_id', 64)->nullable()->after('presupuesto_semanal_cop');
            $table->string('meta_adset_permanente_id', 64)->nullable()->after('meta_campana_permanente_id');
            $table->unsignedTinyInteger('creatividades_max')->default(3)->after('meta_adset_permanente_id');
            $table->unsignedTinyInteger('piezas_semana_max')->default(3)->after('creatividades_max');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->dropColumn([
                'presupuesto_semanal_cop',
                'meta_campana_permanente_id',
                'meta_adset_permanente_id',
                'creatividades_max',
                'piezas_semana_max',
            ]);
        });
    }
};
