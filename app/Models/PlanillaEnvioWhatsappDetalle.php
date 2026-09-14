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

    /**
     * Qué tan lejos llegó el mensaje. Meta los reporta en este orden y a veces
     * fuera de orden, así que hay que poder compararlos.
     */
    public const AVANCE_ESTADO = ['enviado' => 1, 'entregado' => 2, 'leido' => 3];

    /**
     * Aplica el estado que reporta Meta, con las reglas que valen tanto para el
     * webhook en vivo como para una reconciliación posterior — de ahí que viva
     * aquí y no en uno de los dos: si divergieran, la pantalla diría una cosa
     * distinta según por dónde entró el dato.
     *
     * Un «fallido» manda siempre: es el que hay que atender. Los demás solo
     * avanzan, para que un `delivered` que llegue tarde no borre un `read` ya
     * registrado.
     *
     * @return bool si el estado cambió
     */
    public function aplicarEstadoDeMeta(string $estadoMeta, $errores = null): bool
    {
        $esFallo = $estadoMeta === 'fallido';

        if (! $esFallo
            && (self::AVANCE_ESTADO[$estadoMeta] ?? 0) <= (self::AVANCE_ESTADO[$this->estado] ?? 0)) {
            return false;
        }

        $this->update([
            'estado' => $estadoMeta,
            'error'  => $esFallo
                ? mb_substr(is_string($errores) ? $errores : json_encode($errores ?: 'Fallo reportado por Meta'), 0, 500)
                : null,
        ]);

        // El lote lleva su propio conteo y el historial lo muestra: si un envío
        // rebota después, deja de ser un enviado.
        if ($esFallo && $this->envio_id) {
            $lote = PlanillaEnvioWhatsapp::find($this->envio_id);
            if ($lote) {
                $lote->increment('total_fallidos');
                if ($lote->total_enviados > 0) {
                    $lote->decrement('total_enviados');
                }
            }
        }

        return true;
    }

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
