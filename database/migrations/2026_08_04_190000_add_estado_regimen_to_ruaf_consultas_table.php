<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado y régimen de salud en columnas propias de `ruaf_consultas`.
 *
 * El dato ya venía en la respuesta del operador y quedaba guardado dentro de
 * `payload`, pero ahí no se puede filtrar ni agrupar. Estas tres columnas son
 * lo que se consulta de verdad:
 *
 *   - `estado_eps`  → Activo / Retirado / vacío. Un cliente que Brynex tiene
 *                     como vigente pero el registro marca Retirado es un
 *                     problema de afiliación que hay que ver.
 *   - `regimen`     → Contributivo / Subsidiado. Determina si la persona
 *                     puede cotizar como independiente.
 *   - `consultado_at` → cuándo se preguntó. `created_at` sirve para eso hoy,
 *                     pero se pierde si la fila se actualiza con
 *                     --reconsultar; esta columna siempre trae la última.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruaf_consultas', function (Blueprint $table) {
            $table->string('estado_eps', 30)->nullable()->after('pension_id_ruaf');
            $table->string('regimen', 30)->nullable()->after('estado_eps');
            $table->timestamp('consultado_at')->nullable()->after('regimen');
        });

        Schema::table('ruaf_consultas', function (Blueprint $table) {
            $table->index(['aliado_id', 'estado_eps']);
            $table->index(['aliado_id', 'regimen']);
        });

        // Las filas que ya existen tienen el dato dentro de payload; se sube a
        // las columnas nuevas para no perder lo ya consultado.
        foreach (DB::table('ruaf_consultas')->whereNotNull('payload')->get(['id', 'payload', 'created_at']) as $fila) {
            $d = json_decode($fila->payload, true);

            if (! is_array($d)) {
                continue;
            }

            DB::table('ruaf_consultas')->where('id', $fila->id)->update([
                'estado_eps' => $d['estado'] ?? null,
                'regimen' => $d['regimen'] ?? null,
                'consultado_at' => $fila->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ruaf_consultas', function (Blueprint $table) {
            $table->dropIndex(['aliado_id', 'estado_eps']);
            $table->dropIndex(['aliado_id', 'regimen']);
            $table->dropColumn(['estado_eps', 'regimen', 'consultado_at']);
        });
    }
};
