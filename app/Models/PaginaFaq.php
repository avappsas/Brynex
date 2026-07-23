<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaginaFaq extends BaseModel
{
    protected $table = 'pagina_faqs';

    protected $fillable = [
        'aliado_id',
        'pregunta',
        'respuesta',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden'  => 'integer',
        'activo' => 'boolean',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }
}
