<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraCobro extends Model
{
    protected $table = 'bitacora_cobros';

    protected $fillable = [
        'aliado_id',
        'contrato_id',
        'empresa_id',
        'factura_id',
        'usuario_id',
        'fecha_llamada',
        'resultado',
        'observacion',
    ];


    protected $casts = [
        'fecha_llamada' => 'datetime',
    ];

    // ── Etiquetas de resultado ──
    const RESULTADOS = [
        'no_contesta'  => '📵 No contesta',
        'promesa_pago' => '🤝 Promesa de pago',
        'pagado'       => '✅ Pagado',
        'numero_errado'=> '❌ Número errado',
        'otro'         => '📝 Otro',
    ];

    public function getEtiquetaResultadoAttribute(): string
    {
        return self::RESULTADOS[$this->resultado] ?? ucfirst($this->resultado);
    }

    /** Días transcurridos desde la llamada */
    public function getDiasAttribute(): int
    {
        return (int) $this->fecha_llamada->diffInDays(now());
    }

    // ── Relaciones ──
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
