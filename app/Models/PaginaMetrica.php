<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaginaMetrica extends BaseModel
{
    protected $table = 'pagina_metricas';

    protected $fillable = ['aliado_id', 'tipo', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }
}
