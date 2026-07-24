<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piloto automático de marketing: la IA genera una pieza publicitaria al día por aliado
 * (tema + copy + imagen Gemini) y la deja pendiente de aprobación o la publica sola
 * según el modo. `tema` en publicaciones alimenta el anti-repetición del prompt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autopilot_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->unique();
            $table->boolean('activo')->default(false);
            // aprobar: crea la pieza en estado pendiente | auto: la aprueba y publica sola
            $table->string('modo', 20)->default('aprobar');
            // Hora local Colombia a partir de la cual se genera la pieza del día (formato HH:MM)
            $table->string('hora', 5)->default('09:00');
            // Días de la semana activos (ISO 1=lunes..7=domingo); null = todos los días
            $table->json('dias')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
        });

        Schema::table('publicaciones', function (Blueprint $table) {
            $table->string('tema', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('tema');
        });
        Schema::dropIfExists('autopilot_config');
    }
};
