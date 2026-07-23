<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaginaLead extends BaseModel
{
    protected $table = 'pagina_leads';

    protected $fillable = [
        'aliado_id',
        'nombre',
        'celular',
        'perfil',
        'coberturas',
        'ingreso_mensual',
        'valor_mensual_cotizado',
        'plan_interes',
        'origen',
        'estado',
        'ip_hash',
    ];

    protected $casts = [
        'coberturas'             => 'array',
        'ingreso_mensual'        => 'decimal:2',
        'valor_mensual_cotizado' => 'decimal:2',
    ];

    protected $attributes = [
        'origen' => 'cotizador',
        'estado' => 'nuevo',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }
}
