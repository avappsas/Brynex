<?php

namespace App\Services\Caja;

use App\Models\Contrato;
use Illuminate\Support\Facades\DB;

/**
 * Guarda en BryNex los beneficiarios que reportan las cajas de compensación.
 *
 * Las cajas saben quién tiene a cargo a quién —es de lo que depende el subsidio—
 * y BryNex casi no lo tenía: antes del 16-sep-2026 había tres registros en toda
 * la base. Comfandi los entrega en el Excel del listado de trabajadores; de
 * Comfenalco salen de su consulta de grupo familiar.
 *
 * Solo se agregan los que faltan. Los que ya están no se tocan: pueden tener
 * datos corregidos a mano y la caja no siempre es la fuente más fiel.
 */
class BeneficiariosCaja
{
    /**
     * @param  array<int>  $aliadoIds  aliados donde vive esa empresa
     * @param  array<string, array>  $porTrabajador  documento del trabajador → beneficiarios
     * @return array{nuevos:int, personas:int}
     */
    public function guardar(array $aliadoIds, array $porTrabajador, bool $simular, string $origen): array
    {
        if (! $porTrabajador) {
            return ['nuevos' => 0, 'personas' => 0];
        }

        // Solo de quien tenga contrato en alguno de esos aliados: no se traen
        // personas que BryNex no conoce.
        $conContrato = Contrato::whereIn('aliado_id', $aliadoIds)->whereIn('cedula', array_keys($porTrabajador))
            ->get(['cedula', 'aliado_id'])
            ->groupBy(fn ($c) => ltrim((string) $c->cedula, '0'))
            ->map(fn ($g) => (int) $g->first()->aliado_id);

        $nuevos = 0;
        $personas = 0;

        foreach ($porTrabajador as $cedula => $suyos) {
            $aliadoId = $conContrato->get((string) $cedula);
            if (! $aliadoId) {
                continue;
            }

            $yaTiene = DB::table('beneficiarios')->where('aliado_id', $aliadoId)->where('cc_cliente', (string) $cedula)
                ->pluck('n_documento')->map(fn ($d) => ltrim(preg_replace('/\D/', '', (string) $d), '0'))->filter()->all();

            $faltan = array_values(array_filter($suyos, fn ($b) => ! empty($b['documento']) && ! in_array($b['documento'], $yaTiene, true)));
            if (! $faltan) {
                continue;
            }

            $personas++;
            $nuevos += count($faltan);
            if ($simular) {
                continue;
            }

            DB::table('beneficiarios')->insert(array_map(fn ($b) => [
                'aliado_id' => $aliadoId,
                'cc_cliente' => (string) $cedula,
                'tipo_doc' => $b['tipo_doc'] ?? null,
                'n_documento' => $b['documento'],
                'nombres' => $b['nombre'] ?? '',
                'fecha_nacimiento' => $b['nacimiento'] ?? null,
                'parentesco' => $b['parentesco'] ?? null,
                'observacion' => "Bajado de {$origen} el ".now()->format('d/m/Y').'.',
                'created_at' => now(),
                'updated_at' => now(),
            ], $faltan));
        }

        return ['nuevos' => $nuevos, 'personas' => $personas];
    }
}
