<?php

namespace App\Models;

/**
 * Puente entre la ficha maestra (un NIT) y las filas de `razones_sociales`
 * que cada aliado tiene para esa misma empresa.
 *
 * Se reconstruye solo cruzando el NIT: ver
 * `BrynexRazonSocialService::sincronizarVinculos()`. Nunca se edita a mano.
 */
class BrynexRazonSocialVinculo extends BaseModel
{
    protected $table = 'brynex_razon_social_vinculos';

    protected $fillable = ['ficha_id', 'razon_social_id', 'aliado_id'];

    public function ficha()
    {
        return $this->belongsTo(BrynexRazonSocial::class, 'ficha_id');
    }

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function aliado()
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }
}
