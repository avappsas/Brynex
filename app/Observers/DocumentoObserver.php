<?php

namespace App\Observers;

use App\Models\DocumentoCliente;
use App\Models\Bitacora;

class DocumentoObserver
{
    public function created(DocumentoCliente $model): void
    {
        $destino = $model->doc_beneficiario
            ? "beneficiario {$model->doc_beneficiario}"
            : 'titular';

        Bitacora::registrar(
            'created',
            'DocumentoCliente',
            $model->id,
            "Subió documento '{$model->nombre_archivo}' ({$model->tipo_documento}) para {$destino} — CC cliente {$model->cc_cliente}",
            ['tipo' => $model->tipo_documento, 'archivo' => $model->nombre_archivo, 'beneficiario' => $model->doc_beneficiario],
            $model->aliado_id
        );
    }

    public function deleted(DocumentoCliente $model): void
    {
        Bitacora::registrar(
            'deleted',
            'DocumentoCliente',
            $model->id,
            "Eliminó documento '{$model->nombre_archivo}' de CC cliente {$model->cc_cliente}",
            $model->toArray(),
            $model->aliado_id
        );
    }
}
