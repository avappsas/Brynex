<?php

namespace App\Models;

class Pension extends BaseModel
{
    protected $table = 'pensiones';

    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
        'formulario_pdf', 'formulario_campos',
    ];

    protected $casts = [
        'formulario_campos' => 'array',
    ];

    /**
     * Alias para reutilizar el editor de formularios, que trabaja con `nombre`
     * (así lo llama la tabla `eps`). Aquí el nombre es la razón social.
     */
    public function getNombreAttribute(): ?string
    {
        return $this->razon_social;
    }

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
