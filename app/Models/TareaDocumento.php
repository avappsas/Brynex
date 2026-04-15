<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaDocumento extends Model
{
    public $timestamps = false;
    protected $table = 'tarea_documentos';

    protected $fillable = [
        'tarea_id', 'user_id', 'nombre', 'ruta', 'tipo_archivo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIconoAttribute(): string
    {
        return match(strtolower($this->tipo_archivo ?? '')) {
            'pdf'  => '📄',
            'jpg', 'jpeg', 'png', 'gif' => '🖼️',
            'docx', 'doc' => '📝',
            'xlsx', 'xls' => '📊',
            default       => '📎',
        };
    }
}
