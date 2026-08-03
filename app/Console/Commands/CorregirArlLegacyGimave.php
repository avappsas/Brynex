<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el arl_id de los contratos de GiMave migrados desde el legacy.
 *
 * En GiMave_Integral el campo `N_ARL` guarda el NIT de la ARL (800256161 =
 * SURA, 860011153 = POSITIVA...), no el nivel de riesgo — el nivel está en
 * `ARL`. La migración nunca lo miró: sacaba el arl_id del `arl_nit` de la
 * razón social, así que todos los independientes de una razón social genérica
 * heredaban la misma ARL sin importar la que realmente tuvieran.
 *
 * Guardián contra pisar trabajo humano: solo se corrige el contrato cuyo
 * arl_id actual es exactamente el que produjo la migración (el de la razón
 * social). Si difiere, alguien lo cambió a propósito y se deja quieto.
 *
 * Los contratos con nivel de riesgo 0 no llevan ARL: se omiten aunque el
 * legacy traiga un N_ARL residual.
 */
class CorregirArlLegacyGimave extends Command
{
    protected $signature = 'arl:corregir-legacy-gimave
                            {--apply : Aplica los cambios (por defecto solo muestra el diagnóstico)}
                            {--todos : Incluye razones sociales de empresa, no solo independientes}';

    protected $description = 'Corrige arl_id de contratos de GiMave usando N_ARL del legacy';

    private const ALIADO_ID = 4;
    private const LEGACY_DB = 'GiMave_Integral';

    public function handle(): int
    {
        $aplicar    = (bool) $this->option('apply');
        $soloIndep  = !$this->option('todos');

        $this->info('Leyendo el legacy ' . self::LEGACY_DB . '...');

        // N_ARL solo es NIT de ARL cuando tiene pinta de NIT; los valores 0 y
        // los cortos son basura legacy.
        $legacy = [];
        foreach (DB::connection('sqlsrv_legacy')->select(
            'SELECT Id, ARL, N_ARL FROM [' . self::LEGACY_DB . '].dbo.Contratos WHERE Id IS NOT NULL'
        ) as $r) {
            $legacy[(int) $r->Id] = [
                'nivel' => $r->ARL,
                'nit'   => ($r->N_ARL && $r->N_ARL > 100000000) ? (string) $r->N_ARL : null,
            ];
        }
        $this->line('  ' . count($legacy) . ' filas legacy indexadas');

        $arlPorNit = DB::table('arls')->whereNotNull('nit')->get(['id', 'nit', 'nombre_arl'])->keyBy('nit');

        // ARL que la migración habría puesto: la del arl_nit de la razón social.
        $arlPorRs = [];
        foreach (DB::table('razones_sociales')->where('aliado_id', self::ALIADO_ID)->get(['id', 'arl_nit']) as $rs) {
            $arlPorRs[$rs->id] = $rs->arl_nit ? ($arlPorNit->get($rs->arl_nit)->id ?? null) : null;
        }

        $query = DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.aliado_id', self::ALIADO_ID)
            ->whereNotNull('c.id_legacy');
        if ($soloIndep) $query->where('rs.es_independiente', 1);

        $contratos = $query->get(['c.id', 'c.cedula', 'c.estado', 'c.arl_id', 'c.id_legacy', 'c.razon_social_id']);
        $this->line('  ' . $contratos->count() . ' contratos a evaluar' . ($soloIndep ? ' (solo independientes)' : ''));

        $corregir = [];
        $omit     = ['sin_nit' => 0, 'nivel_cero' => 0, 'ya_correcto' => 0, 'tocado_a_mano' => []];

        foreach ($contratos as $c) {
            $l = $legacy[(int) $c->id_legacy] ?? null;
            if (!$l || !$l['nit'] || !$arlPorNit->has($l['nit'])) { $omit['sin_nit']++; continue; }

            // Nivel 0 = sin ARL. El N_ARL que traiga es residual.
            if ((int) $l['nivel'] === 0) { $omit['nivel_cero']++; continue; }

            $esperado = $arlPorNit->get($l['nit'])->id;
            if ((int) $c->arl_id === (int) $esperado) { $omit['ya_correcto']++; continue; }

            $migracion = $arlPorRs[$c->razon_social_id] ?? null;
            if ((int) $c->arl_id !== (int) $migracion) { $omit['tocado_a_mano'][] = $c; continue; }

            $corregir[] = ['contrato' => $c, 'esperado' => $esperado, 'nombre' => $arlPorNit->get($l['nit'])->nombre_arl];
        }

        $this->newLine();
        $this->info('Diagnóstico:');
        $this->line('  ya correctos            : ' . $omit['ya_correcto']);
        $this->line('  sin N_ARL utilizable    : ' . $omit['sin_nit']);
        $this->line('  nivel 0 (sin ARL)       : ' . $omit['nivel_cero']);
        $this->line('  tocados a mano (se omiten): ' . count($omit['tocado_a_mano']));
        $this->line('  A CORREGIR              : ' . count($corregir));

        foreach ($omit['tocado_a_mano'] as $c) {
            $this->warn("    revisar manual → contrato {$c->id} (cédula {$c->cedula}, {$c->estado})");
        }

        if (!$corregir) {
            $this->info('Nada que corregir.');
            return self::SUCCESS;
        }

        // Resumen por transición
        $resumen = [];
        foreach ($corregir as $x) {
            $actual = $x['contrato']->arl_id
                ? DB::table('arls')->where('id', $x['contrato']->arl_id)->value('nombre_arl')
                : 'SIN ARL';
            $k = "{$actual} → {$x['nombre']} [{$x['contrato']->estado}]";
            $resumen[$k] = ($resumen[$k] ?? 0) + 1;
        }
        arsort($resumen);
        $this->newLine();
        $this->info('Cambios por transición:');
        foreach ($resumen as $k => $v) $this->line('  ' . str_pad($k, 44) . ' => ' . $v);

        if (!$aplicar) {
            $this->newLine();
            $this->comment('Dry-run. Nada se escribió. Volver a correr con --apply para aplicar.');
            return self::SUCCESS;
        }

        // Respaldo antes de escribir: solo las columnas que se tocan.
        $backup = 'contratos_arlgimave_' . now()->format('Ymd_His');
        $ids    = implode(',', array_map(fn($x) => $x['contrato']->id, $corregir));
        DB::statement("SELECT id, arl_id, razon_social_id, id_legacy INTO [$backup] FROM contratos WHERE id IN ($ids)");
        $this->info("Respaldo creado: $backup (" . DB::table($backup)->count() . ' filas)');

        // Agrupar por ARL destino para no hacer un UPDATE por contrato.
        $porArl = [];
        foreach ($corregir as $x) $porArl[$x['esperado']][] = $x['contrato']->id;

        $total = 0;
        foreach ($porArl as $arlId => $lista) {
            foreach (array_chunk($lista, 500) as $chunk) {
                $total += DB::table('contratos')->whereIn('id', $chunk)->update(['arl_id' => $arlId]);
            }
        }

        $this->info("✅ $total contratos actualizados.");
        $this->comment("Para revertir: UPDATE c SET c.arl_id = b.arl_id FROM contratos c JOIN [$backup] b ON b.id = c.id");

        return self::SUCCESS;
    }
}
