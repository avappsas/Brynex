<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve qué ARL mostrar para un contrato.
 *
 * En una razón social de empresa la ARL la define la empresa (`arl_nit`), así
 * que esa manda sobre lo que diga el contrato. Pero las razones sociales
 * marcadas con `es_independiente` son cajones genéricos donde conviven
 * afiliados de varias ARL: ahí manda la ARL del propio contrato y la de la
 * razón social solo sirve de respaldo cuando el contrato no tiene ninguna.
 *
 * Requiere que el contrato venga con `razonSocial` (incluyendo `arl_nit` y
 * `es_independiente`) y `arl` ya cargados.
 */
trait ResuelveArlEfectiva
{
    /**
     * Mapa nit => fila de `arls` para todas las razones sociales de la
     * colección, para no consultar una vez por contrato.
     */
    protected static function arlsPorNitDeContratos(Collection $contratos): Collection
    {
        $nits = $contratos->pluck('razonSocial.arl_nit')->filter()->unique();

        return $nits->isNotEmpty()
            ? DB::table('arls')->whereIn('nit', $nits)->get(['nit', 'nombre_arl', 'razon_social'])->keyBy('nit')
            : collect();
    }

    protected static function arlEfectiva($contrato, Collection $arlsPorNit): string
    {
        $rs           = $contrato->razonSocial;
        $arlContrato  = $contrato->arl?->nombre_arl ?? $contrato->arl?->razon_social;
        $arlRs        = $rs?->arl_nit ? $arlsPorNit->get($rs->arl_nit) : null;
        $nombreArlRs  = $arlRs ? ($arlRs->nombre_arl ?? $arlRs->razon_social) : null;

        // Razón social de independientes: manda el contrato.
        if ($rs?->es_independiente) {
            return $arlContrato ?? $nombreArlRs ?? '—';
        }

        // Empresa: manda la razón social. Si tiene arl_nit pero no cruza con
        // ninguna ARL registrada, se conserva el aviso en lugar de callarlo.
        if ($rs?->arl_nit) {
            return $nombreArlRs ?? $arlContrato ?? '[ARL Empresa]';
        }

        return $arlContrato ?? '—';
    }
}
