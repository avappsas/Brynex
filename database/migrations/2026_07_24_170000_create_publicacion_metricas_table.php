<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métricas orgánicas por pieza publicada (leídas de la Graph API con el token existente):
 * una fila por publicación+red, actualizada a diario por marketing:metricas. `alcance` solo
 * viene en Instagram (Meta retiró las métricas de alcance por post de la API de Páginas).
 * `estilo_imagen` en publicaciones permite comparar rendimiento ilustración vs fotorrealista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicacion_metricas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('publicacion_id');
            $table->string('red', 20);
            $table->unsignedInteger('alcance')->nullable();
            $table->unsignedInteger('me_gusta')->default(0);
            $table->unsignedInteger('comentarios')->default(0);
            $table->unsignedInteger('compartidos')->default(0);
            $table->dateTime('medido_at');
            $table->timestamps();

            $table->foreign('publicacion_id')->references('id')->on('publicaciones')->cascadeOnDelete();
            $table->unique(['publicacion_id', 'red']);
        });

        Schema::table('publicaciones', function (Blueprint $table) {
            $table->string('estilo_imagen', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('estilo_imagen');
        });
        Schema::dropIfExists('publicacion_metricas');
    }
};
