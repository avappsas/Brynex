<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_semaforo_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable()->index();
            $table->string('tipo_tarea', 50);
            $table->unsignedInteger('dias_limite');
            $table->unsignedInteger('dias_alerta_amarilla');
            $table->unique(['aliado_id', 'tipo_tarea']);
        });

        // Insertar valores por defecto globales (aliado_id = null)
        $defaults = [
            ['tipo_tarea' => 'traslado_eps',            'dias_limite' => 15, 'dias_alerta_amarilla' => 8],
            ['tipo_tarea' => 'inclusion_beneficiarios',  'dias_limite' => 10, 'dias_alerta_amarilla' => 5],
            ['tipo_tarea' => 'exclusion',                'dias_limite' => 10, 'dias_alerta_amarilla' => 5],
            ['tipo_tarea' => 'subsidios',                'dias_limite' => 20, 'dias_alerta_amarilla' => 10],
            ['tipo_tarea' => 'actualizar_documentos',    'dias_limite' => 7,  'dias_alerta_amarilla' => 4],
            ['tipo_tarea' => 'devolucion_aportes',       'dias_limite' => 30, 'dias_alerta_amarilla' => 15],
            ['tipo_tarea' => 'solicitud_documentos',     'dias_limite' => 7,  'dias_alerta_amarilla' => 3],
            ['tipo_tarea' => 'otros',                    'dias_limite' => 15, 'dias_alerta_amarilla' => 7],
        ];

        foreach ($defaults as $row) {
            DB::table('tarea_semaforo_config')->insert([
                'aliado_id'           => null,
                'tipo_tarea'          => $row['tipo_tarea'],
                'dias_limite'         => $row['dias_limite'],
                'dias_alerta_amarilla'=> $row['dias_alerta_amarilla'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_semaforo_config');
    }
};
