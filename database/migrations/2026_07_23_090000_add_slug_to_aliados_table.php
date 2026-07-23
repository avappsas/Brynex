<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Slug público del aliado (ej. "brygar") para la página web pública en /aliado/{slug}.
     * Se agrega sin unique() para poder respaldar TODOS los aliados existentes antes de
     * aplicar la restricción — SQL Server solo permite un NULL en columnas UNIQUE, así que
     * el índice único se crea en un paso separado, después del backfill.
     */
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->after('color_primario');
        });

        $usados = [];
        foreach (DB::table('aliados')->select('id', 'nombre')->get() as $aliado) {
            $base = Str::slug($aliado->nombre) ?: 'aliado';
            $slug = $base;
            $i = 1;
            while (in_array($slug, $usados, true)) {
                $slug = $base . '-' . (++$i);
            }
            $usados[] = $slug;

            DB::table('aliados')->where('id', $aliado->id)->update(['slug' => $slug]);
        }

        Schema::table('aliados', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
