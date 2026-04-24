<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadEconomica extends BaseModel
{
    public $timestamps = false;
    protected $table = 'actividades_economicas';
    protected $fillable = ['codigo_ciiu', 'nombre', 'nivel_arl_sugerido', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'actividad_economica_id');
    }
}
