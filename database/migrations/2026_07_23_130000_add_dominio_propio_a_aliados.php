<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dominio propio del aliado (ej. "brygar.com", sin protocolo ni "www.") para que su
     * página pública se sirva directamente en su dominio en vez de solo en
     * brynex.co/aliado/{slug}. Requiere que el DNS del dominio apunte a este servidor
     * (ver docs/plan-pagina-publica-aliado.md, sección Fase 6, para los pasos exactos).
     *
     * Índice único FILTRADO (WHERE dominio_propio IS NOT NULL): a diferencia de MySQL/Postgres,
     * SQL Server trata todos los NULL como iguales en una columna UNIQUE normal, así que con
     * los 12 aliados existentes en NULL un UNIQUE simple fallaría (mismo problema ya resuelto
     * para aliados.slug). El índice filtrado es la solución nativa de SQL Server para esto.
     */
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('dominio_propio', 150)->nullable()->after('slug');
        });

        DB::statement('CREATE UNIQUE INDEX aliados_dominio_propio_unique ON aliados (dominio_propio) WHERE dominio_propio IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX aliados_dominio_propio_unique ON aliados');

        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('dominio_propio');
        });
    }
};
