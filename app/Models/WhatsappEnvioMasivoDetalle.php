<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappEnvioMasivoDetalle extends BaseModel
{
    protected $table = 'whatsapp_envios_masivos_detalle';

    protected $fillable = [
        'envio_id',
        'contrato_id',
        'empresa_id',
        'wa_numero',
        'nombre_destinatario',
        'valor_cobro',
        'contrato_ids_json',
        'estado',
        'wa_message_id',
        'error',
    ];

    protected $casts = [
        'valor_cobro' => 'decimal:2',
    ];

    /**
     * Devuelve el array de IDs de contratos combinados (cuando se agruparon
     * múltiples contratos de planilla del mismo cliente en un solo detalle).
     */
    public function contratoIdsAgrupados(): array
    {
        if (empty($this->contrato_ids_json)) {
            return $this->contrato_id ? [$this->contrato_id] : [];
        }
        $decoded = json_decode($this->contrato_ids_json, true);
        return is_array($decoded) ? $decoded : [$this->contrato_id];
    }

    /**
     * Retorna el contrato_id primario (el primero del grupo o el directo).
     */
    public function contratoIdPrimario(): ?int
    {
        $ids = $this->contratoIdsAgrupados();
        return $ids[0] ?? null;
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(WhatsappEnvioMasivo::class, 'envio_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
