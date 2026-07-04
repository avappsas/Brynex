<?php

namespace App\Models\Finanzas;

use App\Models\BaseModel;

/**
 * BaseFinanzasModel - Todos los modelos de finanzas extienden esta clase
 * para usar la base de datos separada 'BryNex_Finanzas'.
 */
abstract class BaseFinanzasModel extends BaseModel
{
    /**
     * Usar la conexión de la base de datos de finanzas.
     */
    protected $connection = 'finanzas';
}
