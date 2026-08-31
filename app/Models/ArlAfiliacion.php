<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento del contrato ante ARL Sura: afiliación, retiro o anulación.
 *
 * Es historial, no estado: la modalidad 15 es un ciclo mensual y guardar el
 * resultado en columnas de `contratos` borraría el mes anterior. Cada fila
 * conserva su `codigo_transaccion` —lo que Sura pide cuando hay algo que
 * reclamar— junto con el JSON enviado y el recibido, porque el API no tiene
 * ambiente de pruebas y la única forma de reconstruir qué pasó es haberlo
 * guardado.
 */
class ArlAfiliacion extends BaseModel
{
    protected $table = 'arl_afiliaciones';

    public const OP_AFILIACION = 'afiliacion';
    public const OP_RETIRO     = 'retiro';
    public const OP_ANULACION  = 'anulacion';
    /** Mover la fecha de inicio de una cobertura ya creada: renovar sin recrear. */
    public const OP_MODIFICACION = 'modificacion';

    public const ESTADO_EXITOSA = 'exitosa';
    public const ESTADO_FALLIDA = 'fallida';
    public const ESTADO_ANULADA = 'anulada';

    protected $fillable = [
        'aliado_id', 'contrato_id', 'razon_social_id', 'cedula',
        'operacion', 'estado', 'poliza', 'tipo_afiliado', 'tipo_cotizante',
        'codigo_centro', 'nivel_riesgo',
        'fecha_inicio_cobertura', 'fecha_fin_cobertura',
        'codigo_transaccion', 'fecha_proceso',
        'payload', 'respuesta', 'mensaje_error', 'usuario_id',
    ];

    /** El payload lleva datos personales del afiliado: nunca sale en un JSON de respuesta. */
    protected $hidden = ['payload', 'respuesta'];

    protected $casts = [
        'nivel_riesgo'           => 'integer',
        'fecha_inicio_cobertura' => 'date',
        'fecha_fin_cobertura'    => 'date',
        'fecha_proceso'          => 'datetime',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    public function scopeExitosas($q)
    {
        return $q->where('estado', self::ESTADO_EXITOSA);
    }

    /**
     * La afiliación viva del contrato: la última exitosa que no haya sido
     * anulada ni cerrada con un retiro posterior.
     */
    public static function vigenteDe(int $contratoId): ?self
    {
        return static::where('contrato_id', $contratoId)
            ->where('operacion', self::OP_AFILIACION)
            ->where('estado', self::ESTADO_EXITOSA)
            ->orderByDesc('fecha_proceso')
            ->first();
    }

    /**
     * Sura solo deja anular dentro de los 30 días siguientes al INICIO de la
     * cobertura (`nmDiasAnulNovIngDep`), no desde que se hizo la afiliación.
     */
    public function sePuedeAnular(): bool
    {
        if ($this->operacion !== self::OP_AFILIACION || $this->estado !== self::ESTADO_EXITOSA) {
            return false;
        }

        return $this->fecha_inicio_cobertura
            && $this->fecha_inicio_cobertura->diffInDays(now(), false) <= 30;
    }
}
