<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El semáforo del tipo de tarea `mora_eps`.
 *
 * Sin una fila aquí, `TareaSemaforoConfig::fechaLimiteParaTipo()` devuelve null
 * y la tarea nace sin fecha límite: nunca se pone en rojo y se queda en el
 * fondo de la lista. Quince días, como las de trámite: la mora se reclama con
 * papeles y la EPS no responde el mismo día.
 *
 * También le pone plazo a las que ya se crearon sin él.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('tarea_semaforo_config')->where('tipo_tarea', 'mora_eps')->whereNull('aliado_id')->exists();

        if (! $existe) {
            DB::table('tarea_semaforo_config')->insert([
                'aliado_id' => null,
                'tipo_tarea' => 'mora_eps',
                'dias_limite' => 15,
                'dias_alerta_amarilla' => 7,
            ]);
        }

        DB::table('tareas')
            ->where('tipo', 'mora_eps')
            ->whereNull('fecha_limite')
            ->update(['fecha_limite' => now()->addDays(15)]);
    }

    public function down(): void
    {
        DB::table('tarea_semaforo_config')->where('tipo_tarea', 'mora_eps')->whereNull('aliado_id')->delete();
    }
};
