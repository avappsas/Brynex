<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un radicado en OK puede estar confirmado por la entidad (el portal o el API
 * de Nueva EPS, EPS SURA, Salud Total o ARL Sura lo dio por hecho) o marcado a
 * mano. El estado sigue siendo `ok` en ambos casos —filtros, semáforos e
 * informes no cambian—; estas columnas solo dicen quién lo confirmó y cuándo,
 * para pintarlo distinto. Los aliados que no usan confirmación no se enteran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            if (! Schema::hasColumn('radicados', 'confirmado_por')) {
                $table->string('confirmado_por', 30)->nullable();
            }
            if (! Schema::hasColumn('radicados', 'confirmado_en')) {
                $table->datetime('confirmado_en')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('radicados', function (Blueprint $table) {
            $table->dropColumn(['confirmado_por', 'confirmado_en']);
        });
    }
};
