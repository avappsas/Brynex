<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestionIncapacidad extends BaseModel
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
        'cambia_estado',
        'estado_nuevo',
        'created_at',
    ];

    protected $casts = [
        'created_at'       => 'datetime',
        'fecha_recordar'   => 'date',
        'aplica_a_familia' => 'boolean',
        'cambia_estado'    => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function incapacidad(): BelongsTo
    {
        return $this->belongsTo(Incapacidad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function tipoLabel(): string
    {
        $cfg = Incapacidad::TIPOS_GESTION[$this->tipo] ?? null;
        return $cfg['label'] ?? ucfirst($this->tipo);
    }

    public function estadoResultadoLabel(): string
    {
        $cfg = Incapacidad::ESTADOS[$this->estado_resultado] ?? null;
        return $cfg['label'] ?? ucfirst($this->estado_resultado ?? '');
    }

    /**
     * Icono del tipo de gestión para el timeline visual.
     */
    public function tipoIcono(): string
    {
        return match($this->tipo) {
            'llamada'         => '📞',
            'correo'          => '📧',
            'whatsapp'        => '💬',
            'portal'          => '🌐',
            'transcripcion'   => '🏥',
            'radico'          => '📋',
            'tutela'          => '⚖️',
            'tutela_radicada' => '📜',
            'liquidacion'     => '💰',
            'pago'            => '✅',
            'rechazado'       => '❌',
            default           => '📝',
        };
    }

    /**
     * ¿Esta gestión cambió el estado de la incapacidad?
     */
    public function cambioEstado(): bool
    {
        return (bool) $this->cambia_estado;
    }

    /**
     * Aplica el cambio de estado a la incapacidad si este tipo lo requiere.
     * Se llama desde el controller al guardar la gestión.
     *
     * @return bool  true si se actualizó el estado, false si no aplica.
     */
    public function aplicarCambioEstado(): bool
    {
        $cfg = Incapacidad::TIPOS_GESTION[$this->tipo] ?? null;
        if (!$cfg || !$cfg['cambia_estado'] || !$cfg['nuevo_estado']) {
            return false;
        }

        $incapacidad = $this->incapacidad;
        if (!$incapacidad) return false;

        $incapacidad->estado = $cfg['nuevo_estado'];

        // Si es pago, marcar también estado_pago
        if ($this->tipo === 'pago') {
            $incapacidad->estado_pago = 'pagado_afiliado';
        }

        $incapacidad->saveQuietly();

        // Marcar esta gestión como la que cambió el estado
        $this->cambia_estado = true;
        $this->estado_nuevo  = $cfg['nuevo_estado'];

        return true;
    }
}
