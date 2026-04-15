<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Models\Bitacora;

class ClienteObserver
{
    public function updating(Cliente $model): void
    {
        $dirty = $model->getDirty();
        $diff  = [];
        // Excluir campos no significativos
        $excluir = ['updated_at', 'created_at'];
        foreach ($dirty as $campo => $valorNuevo) {
            if (in_array($campo, $excluir)) continue;
            $diff[$campo] = [
                'de' => $model->getOriginal($campo),
                'a'  => $valorNuevo,
            ];
        }
        $model->_diffAudit = $diff;
    }

    public function updated(Cliente $model): void
    {
        $diff = $model->_diffAudit ?? [];
        if (empty($diff)) return;

        Bitacora::registrar(
            'updated',
            'Cliente',
            $model->id,
            "Actualizó cliente CC {$model->cedula} — {$model->primer_nombre} {$model->primer_apellido}",
            $diff,
            $model->aliado_id
        );
    }

    public function created(Cliente $model): void
    {
        Bitacora::registrar(
            'created',
            'Cliente',
            $model->id,
            "Ingresó cliente CC {$model->cedula} — {$model->primer_nombre} {$model->primer_apellido}",
            ['cedula' => $model->cedula, 'nombres' => "{$model->primer_nombre} {$model->primer_apellido}"],
            $model->aliado_id
        );
    }

    public function deleted(Cliente $model): void
    {
        Bitacora::registrar(
            'deleted',
            'Cliente',
            $model->id,
            "Eliminó cliente CC {$model->cedula} — {$model->primer_nombre} {$model->primer_apellido}",
            ['cedula' => $model->cedula, 'nombres' => "{$model->primer_nombre} {$model->primer_apellido}"],
            $model->aliado_id
        );
    }
}
