<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Marca como confirmados por la entidad los radicados en OK que ya cerró un
 * proceso automático antes de que existiera `confirmado_por` (sep-2026).
 *
 * Solo los que tienen rastro claro. En EPS, el último movimiento que dejó el
 * radicado en OK debe ser el de la conciliación o el trámite por portal; si
 * después alguien lo volvió a guardar en OK a mano, no se marca. En ARL Sura
 * (que no deja movimiento) la observación debe seguir siendo la de la
 * afiliación automática y no puede haber movimientos posteriores.
 *
 * Sin --ejecutar solo cuenta. Corre sobre todos los aliados salvo --aliado.
 */
class RadicadosMarcarConfirmados extends Command
{
    protected $signature = 'radicados:marcar-confirmados
                            {--aliado= : Solo este aliado}
                            {--ejecutar : Escribe; sin esto solo muestra cuántos marcaría}';

    protected $description = 'Marca como confirmados por la entidad los radicados en OK cerrados por conciliaciones o afiliaciones automáticas';

    /** Observaciones que deja cada proceso al cerrar un radicado de EPS en OK. */
    private const EPS = [
        'nueva_eps' => [
            'Conciliación con Nueva EPS:%PROCESADO%',
            'Ya tenía reingreso en Nueva EPS%PROCESADO%',
        ],
        'eps_sura' => [
            'Ya vigente en EPS SURA con %conciliación automática%',
        ],
        'salud_total' => [
            '%Salud Total: novedad % APROBADA%',
            'Conciliación con Salud Total:%',
            'Ya activo en Salud Total con %',
        ],
    ];

    public function handle(): int
    {
        $aliado   = $this->option('aliado') ? (int) $this->option('aliado') : null;
        $ejecutar = (bool) $this->option('ejecutar');
        $filas    = [];
        $total    = 0;

        foreach (self::EPS as $entidad => $patrones) {
            [$sql, $bind] = $this->consultaEps($patrones, $aliado);
            $total += $this->contar($entidad, $sql, $bind, $filas);

            if ($ejecutar) {
                DB::update(
                    "UPDATE r SET r.confirmado_por = ?, r.confirmado_en = m.created_at {$sql}",
                    array_merge([$entidad], $bind)
                );
            }
        }

        [$sql, $bind] = $this->consultaArlSura($aliado);
        $total += $this->contar('arl_sura', $sql, $bind, $filas);

        if ($ejecutar) {
            DB::update(
                "UPDATE r SET r.confirmado_por = 'arl_sura', r.confirmado_en = COALESCE(r.fecha_confirmacion, r.updated_at) {$sql}",
                $bind
            );
        }

        $this->table(['Entidad', 'Aliado', 'Radicados'], $filas);
        $this->info(($ejecutar ? 'Marcados: ' : '[SIMULACIÓN] Se marcarían: ').$total.($ejecutar ? '' : '. Corre con --ejecutar para escribir.'));

        return self::SUCCESS;
    }

    /**
     * FROM + WHERE compartido por el conteo y el UPDATE: radicados de EPS en OK
     * sin confirmar cuyo último movimiento a OK coincide con algún patrón.
     */
    private function consultaEps(array $patrones, ?int $aliado): array
    {
        $likes = implode(' OR ', array_fill(0, count($patrones), 'm.observacion LIKE ?'));

        $sql = "FROM radicados r
            JOIN radicado_movimientos m ON m.id = (
                SELECT MAX(m2.id) FROM radicado_movimientos m2
                WHERE m2.radicado_id = r.id AND m2.estado_nuevo = 'ok'
            )
            WHERE r.estado = 'ok' AND r.tipo = 'eps' AND r.confirmado_por IS NULL
              AND ({$likes})".($aliado ? ' AND r.aliado_id = ?' : '');

        return [$sql, array_merge($patrones, $aliado ? [$aliado] : [])];
    }

    private function consultaArlSura(?int $aliado): array
    {
        $sql = "FROM radicados r
            WHERE r.estado = 'ok' AND r.tipo = 'arl' AND r.confirmado_por IS NULL
              AND r.observacion LIKE 'Afiliación automática en ARL Sura desde BryNex%'
              AND NOT EXISTS (
                  SELECT 1 FROM radicado_movimientos m
                  WHERE m.radicado_id = r.id AND m.created_at > r.fecha_confirmacion
              )".($aliado ? ' AND r.aliado_id = ?' : '');

        return [$sql, $aliado ? [$aliado] : []];
    }

    private function contar(string $entidad, string $sql, array $bind, array &$filas): int
    {
        $grupos = DB::select("SELECT r.aliado_id, COUNT(*) AS n {$sql} GROUP BY r.aliado_id ORDER BY r.aliado_id", $bind);
        $suma = 0;

        foreach ($grupos as $g) {
            $filas[] = [$entidad, $g->aliado_id, $g->n];
            $suma += (int) $g->n;
        }

        if (! $grupos) {
            $filas[] = [$entidad, '—', 0];
        }

        return $suma;
    }
}
