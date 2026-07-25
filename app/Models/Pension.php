<?php

namespace App\Models;

use App\Models\BaseModel;

class Pension extends BaseModel
{
    protected $table = 'pensiones';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
    ];

    /**
     * Fondo "PENSIONADO": no es una AFP real, marca que el cliente YA está pensionado.
     * Quien lo tenga queda exento del aporte a pensión sin importar edad, género ni documento.
     */
    public const ID_PENSIONADO = 4;

    public function esPensionado(): bool
    {
        return (int) $this->id === self::ID_PENSIONADO;
    }
}
