<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración del CMS ligero de la página pública de cada aliado (/aliado/{slug}).
     * activo=false por defecto: la página no queda visible al público hasta que el
     * aliado (o BryNex) la active explícitamente desde el panel de administración.
     */
    public function up(): void
    {
        Schema::create('pagina_aliado_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->unique();

            $table->boolean('activo')->default(false);

            $table->string('hero_titulo', 150)->nullable();
            $table->string('hero_subtitulo', 255)->nullable();
            $table->string('hero_cta_texto', 60)->nullable();

            $table->string('seo_titulo', 160)->nullable();
            $table->string('seo_descripcion', 300)->nullable();

            $table->boolean('mostrar_precios')->default(true);
            $table->string('precios_modo', 10)->default('exacto'); // exacto|desde

            $table->json('secciones')->nullable(); // toggles: {hero, planes, cotizador, ahorro, pasos, faq, promos, contacto}

            $table->text('whatsapp_mensaje_base')->nullable();
            $table->boolean('estadisticas_visibles')->default(true);

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_aliado_config');
    }
};
