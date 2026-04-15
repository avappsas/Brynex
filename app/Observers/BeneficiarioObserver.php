<?php

namespace App\Observers;

use App\Models\Beneficiario;
use App\Models\Bitacora;

class BeneficiarioObserver
{
    public function created(Beneficiario $model): void
    {
        Bitacora::registrar(
            'created',
            'Beneficiario',
            $model->id,
            "Ingresó beneficiario '{$model->nombres}' ({$model->parentesco}) para cédula {$model->cc_cliente}",
            ['cc_cliente' => $model->cc_cliente, 'nombres' => $model->nombres, 'parentesco' => $model->parentesco],
            $model->aliado_id
        );
    }

    public function updating(Beneficiario $model): void
    {
        // Captura los cambios ANTES de guardar
        $dirty = $model->getDirty();
        $diff  = [];
        foreach ($dirty as $campo => $valorNuevo) {
            if (in_array($campo, ['updated_at'])) continue;
            $diff[$campo] = [
                'de' => $model->getOriginal($campo),
                'a'  => $valorNuevo,
            ];
        }
        // Guarda en el modelo temporalmente para usarlo en updated()
        $model->_diffAudit = $diff;
    }

    public function updated(Beneficiario $model): void
    {
        $diff = $model->_diffAudit ?? [];
        if (empty($diff)) return;

        Bitacora::registrar(
            'updated',
            'Beneficiario',
            $model->id,
            "Actualizó beneficiario '{$model->nombres}' (CC cliente {$model->cc_cliente})",
            $diff,
            $model->aliado_id
        );
    }

    public function deleted(Beneficiario $model): void
    {
        Bitacora::registrar(
            'deleted',
            'Beneficiario',
            $model->id,
            "Eliminó beneficiario '{$model->nombres}' ({$model->parentesco}) de cédula {$model->cc_cliente}",
            $model->toArray(), // snapshot completo
            $model->aliado_id
        );
    }
}
