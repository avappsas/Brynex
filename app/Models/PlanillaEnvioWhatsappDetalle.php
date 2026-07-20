<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaEnvioWhatsappDetalle extends BaseModel
{
    protected $table = 'planilla_envios_whatsapp_detalle';

    protected $fillable = [
        'envio_id',
        'plano_id',
        'contrato_id',
        'cliente_cedula',
        'empresa_id',
        'wa_numero',
        'nombre_destinatario',
        'numero_planilla',
        'operador_nombre',
        'periodo_mes',
        'periodo_anio',
        'estado',
        'wa_message_id',
        'error',
        'enviado_at',
    ];

    protected $casts = [
        'periodo_mes'  => 'integer',
        'periodo_anio' => 'integer',
        'enviado_at'   => 'datetime',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function envio(): BelongsTo
    {
        return $this->belongsTo(PlanillaEnvioWhatsapp::class, 'envio_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function etiquetaEstado(): string
    {
        return match($this->estado) {
            'pendiente' => '⏳ Pendiente',
            'enviado'   => '🟢 Enviado',
            'fallido'   => '🔴 Fallido',
            'omitido'   => '⚪ Omitido',
            default     => $this->estado,
        };
    }
}
