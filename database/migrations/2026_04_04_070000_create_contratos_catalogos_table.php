<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. TIPO MODALIDAD ──
        Schema::create('tipo_modalidad', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        DB::table('tipo_modalidad')->insert([
            ['nombre' => 'Dependiente E',           'activo' => true],
            ['nombre' => 'Dependiente por Empresa', 'activo' => true],
            ['nombre' => 'Independiente',            'activo' => true],
            ['nombre' => 'Pensionado',               'activo' => true],
            ['nombre' => 'Rentista de Capital',      'activo' => true],
            ['nombre' => 'Madre Comunitaria',        'activo' => true],
            ['nombre' => 'Estudiante',               'activo' => true],
            ['nombre' => 'Recuperado',               'activo' => true],
        ]);

        // ── 2. PLANES CONTRATO ──
        Schema::create('planes_contrato', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('codigo', 30)->unique();  // EPS, EPS_ARL, EPS_ARL_CCF…
            $table->string('nombre', 100);
            $table->boolean('incluye_eps')->default(false);
            $table->boolean('incluye_arl')->default(false);
            $table->boolean('incluye_pension')->default(false);
            $table->boolean('incluye_caja')->default(false);
            $table->boolean('activo')->default(true);
        });

        DB::table('planes_contrato')->insert([
            ['codigo' => 'SOLO_EPS',         'nombre' => 'Solo EPS',               'incluye_eps' => 1, 'incluye_arl' => 0, 'incluye_pension' => 0, 'incluye_caja' => 0, 'activo' => 1],
            ['codigo' => 'SOLO_ARL',         'nombre' => 'Solo ARL',               'incluye_eps' => 0, 'incluye_arl' => 1, 'incluye_pension' => 0, 'incluye_caja' => 0, 'activo' => 1],
            ['codigo' => 'EPS_ARL',          'nombre' => 'EPS + ARL',              'incluye_eps' => 1, 'incluye_arl' => 1, 'incluye_pension' => 0, 'incluye_caja' => 0, 'activo' => 1],
            ['codigo' => 'EPS_ARL_CCF',      'nombre' => 'EPS + ARL + CCF',        'incluye_eps' => 1, 'incluye_arl' => 1, 'incluye_pension' => 0, 'incluye_caja' => 1, 'activo' => 1],
            ['codigo' => 'EPS_ARL_AFP',      'nombre' => 'EPS + ARL + AFP',        'incluye_eps' => 1, 'incluye_arl' => 1, 'incluye_pension' => 1, 'incluye_caja' => 0, 'activo' => 1],
            ['codigo' => 'EPS_ARL_AFP_CCF',  'nombre' => 'EPS + ARL + AFP + CCF',  'incluye_eps' => 1, 'incluye_arl' => 1, 'incluye_pension' => 1, 'incluye_caja' => 1, 'activo' => 1],
            ['codigo' => 'EPS_AFP',          'nombre' => 'EPS + AFP',              'incluye_eps' => 1, 'incluye_arl' => 0, 'incluye_pension' => 1, 'incluye_caja' => 0, 'activo' => 1],
            ['codigo' => 'EPS_AFP_CCF',      'nombre' => 'EPS + AFP + CCF',        'incluye_eps' => 1, 'incluye_arl' => 0, 'incluye_pension' => 1, 'incluye_caja' => 1, 'activo' => 1],
        ]);

        // ── 3. MOTIVOS AFILIACIÓN ──
        Schema::create('motivos_afiliacion', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
        });

        DB::table('motivos_afiliacion')->insert([
            ['nombre' => 'Nuevo',              'activo' => true],
            ['nombre' => 'Traslado EPS',       'activo' => true],
            ['nombre' => 'Traslado ARL',       'activo' => true],
            ['nombre' => 'Reingreso',          'activo' => true],
            ['nombre' => 'Recuperado',         'activo' => true],
            ['nombre' => 'Cambio Razón Social','activo' => true],
            ['nombre' => 'Otro',               'activo' => true],
        ]);

        // ── 4. MOTIVOS RETIRO ──
        Schema::create('motivos_retiro', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 100);
            $table->boolean('es_reingreso')->default(false); // true = NO es retiro real
            $table->boolean('activo')->default(true);
        });

        DB::table('motivos_retiro')->insert([
            ['nombre' => 'Retiro Real',           'es_reingreso' => false, 'activo' => true],
            ['nombre' => 'Retiro-Reingreso',       'es_reingreso' => true,  'activo' => true],
            ['nombre' => 'Fallecimiento',          'es_reingreso' => false, 'activo' => true],
            ['nombre' => 'Pensión',                'es_reingreso' => false, 'activo' => true],
            ['nombre' => 'Traslado Empresa',       'es_reingreso' => true,  'activo' => true],
            ['nombre' => 'Cambio Razón Social',    'es_reingreso' => true,  'activo' => true],
            ['nombre' => 'Incumplimiento de Pago', 'es_reingreso' => false, 'activo' => true],
            ['nombre' => 'Solicitud del Cliente',  'es_reingreso' => false, 'activo' => true],
            ['nombre' => 'Otro',                   'es_reingreso' => false, 'activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_retiro');
        Schema::dropIfExists('motivos_afiliacion');
        Schema::dropIfExists('planes_contrato');
        Schema::dropIfExists('tipo_modalidad');
    }
};
