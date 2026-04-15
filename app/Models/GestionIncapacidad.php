<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestionIncapacidad extends Model
{
    public $timestamps = false;
    protected $table = 'gestiones_incapacidad';

    protected $fillable = [
        'incapacidad_id',
        'user_id',
        'aplica_a_familia',
        'tipo',
        'tramite',
        'respuesta',
        'estado_resultado',
        'fecha_recordar',
    ];

    protected $casts = [
        'created_at'       => 'datetime',
        'fecha_recordar'   => 'date',
        'aplica_a_familia' => 'boolean',
    ];

    public function incapacidad(): BelongsTo
    {
        return $this->belongsTo(Incapacidad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoLabel(): string
    {
        return Incapacidad::TIPOS_GESTION[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function estadoResultadoLabel(): string
    {
        return Incapacidad::ESTADOS[$this->estado_resultado] ?? ucfirst($this->estado_resultado ?? '');
    }
}
