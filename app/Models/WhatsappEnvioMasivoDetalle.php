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
        'estado',
        'wa_message_id',
        'error',
    ];

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
