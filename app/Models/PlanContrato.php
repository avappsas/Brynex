<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanContrato extends Model
{
    public $timestamps = false;
    protected $table = 'planes_contrato';
    protected $fillable = [
        'codigo', 'nombre',
        'incluye_eps', 'incluye_arl', 'incluye_pension', 'incluye_caja',
        'activo',
    ];
    protected $casts = [
        'incluye_eps'     => 'boolean',
        'incluye_arl'     => 'boolean',
        'incluye_pension' => 'boolean',
        'incluye_caja'    => 'boolean',
        'activo'          => 'boolean',
    ];

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'plan_id');
    }

    public function configuracionesAliado(): HasMany
    {
        return $this->hasMany(ConfiguracionAliado::class, 'plan_id');
    }

    /** Devuelve un array de los tipos de radicado que requiere este plan */
    public function tiposRadicado(): array
    {
        $tipos = [];
        if ($this->incluye_eps)     $tipos[] = 'eps';
        if ($this->incluye_arl)     $tipos[] = 'arl';
        if ($this->incluye_pension) $tipos[] = 'pension';
        if ($this->incluye_caja)    $tipos[] = 'caja';
        return $tipos;
    }
}
