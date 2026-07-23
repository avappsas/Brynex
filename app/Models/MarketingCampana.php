<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampana extends BaseModel
{
    protected $table = 'marketing_campanas';

    protected $fillable = [
        'aliado_id',
        'plantilla_id',
        'nombre',
        'descripcion_ia',
        'objetivo',
        'guia_botones',
        'incluir_clientes_vigentes',
        'estado',
        'creado_por',
    ];

    protected $casts = [
        'guia_botones'               => 'array',
        'incluir_clientes_vigentes'  => 'boolean',
    ];

    // Espejo de los DEFAULT de la migración: create() no refresca el modelo con lo que la
    // BD aplicó, así que sin esto un modelo recién creado queda con estado=null en memoria
    // hasta un ->fresh() explícito (etiquetaEstado() ya se rompió una vez por esto).
    protected $attributes = [
        'estado'                     => 'activa',
        'incluir_clientes_vigentes'  => false,
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(WhatsappPlantilla::class, 'plantilla_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(WhatsappEnvioMasivo::class, 'campana_id');
    }

    public function conversaciones(): HasMany
    {
        return $this->hasMany(WhatsappConversacion::class, 'origen_campana_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeDelAliado($query, int $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'activa'     => '🟢 Activa',
            'pausada'    => '⏸️ Pausada',
            'finalizada' => '⚪ Finalizada',
            default      => $this->estado ?? '—',
        };
    }

    /** Instrucción para la IA sobre qué responder si el prospecto tocó un botón específico. */
    public function instruccionParaBoton(string $textoBoton): ?string
    {
        return $this->guia_botones[$textoBoton] ?? null;
    }

    /**
     * Métricas agregadas de la campaña: envío/entrega/lectura (desde el detalle de las
     * tandas) y respuestas/interacciones/bloqueos (desde las conversaciones que se
     * originaron en esta campaña, ver origen_campana_id).
     *
     * @return array{enviados:int, entregados:int, leidos:int, fallidos:int, pendientes:int,
     *   respuestas:int, interacciones:int, bloqueados:int, tasa_entrega:float,
     *   tasa_lectura:float, tasa_respuesta:float}
     */
    public function metricas(): array
    {
        $envioIds = WhatsappEnvioMasivo::where('campana_id', $this->id)->pluck('id');

        $porEstado = WhatsappEnvioMasivoDetalle::whereIn('envio_id', $envioIds)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $enviados   = ($porEstado['enviado'] ?? 0) + ($porEstado['entregado'] ?? 0) + ($porEstado['leido'] ?? 0);
        $entregados = ($porEstado['entregado'] ?? 0) + ($porEstado['leido'] ?? 0);
        $leidos     = $porEstado['leido'] ?? 0;
        $fallidos   = $porEstado['fallido'] ?? 0;
        $pendientes = $porEstado['pendiente'] ?? 0;

        $conversacionIds = WhatsappConversacion::where('origen_campana_id', $this->id)->pluck('id');

        $respuestas = WhatsappMensaje::whereIn('conversacion_id', $conversacionIds)
            ->where('direccion', 'entrante')
            ->distinct()
            ->count('conversacion_id');

        $interacciones = WhatsappMensaje::whereIn('conversacion_id', $conversacionIds)
            ->where('direccion', 'entrante')
            ->where('tipo', 'button')
            ->distinct()
            ->count('conversacion_id');

        $bloqueados = MarketingBloqueado::whereIn('conversacion_id', $conversacionIds)->count();

        return [
            'enviados'       => (int) $enviados,
            'entregados'     => (int) $entregados,
            'leidos'         => (int) $leidos,
            'fallidos'       => (int) $fallidos,
            'pendientes'     => (int) $pendientes,
            'respuestas'     => $respuestas,
            'interacciones'  => $interacciones,
            'bloqueados'     => $bloqueados,
            'tasa_entrega'   => $enviados > 0 ? round($entregados / $enviados * 100, 1) : 0.0,
            'tasa_lectura'   => $enviados > 0 ? round($leidos / $enviados * 100, 1) : 0.0,
            'tasa_respuesta' => $enviados > 0 ? round($respuestas / $enviados * 100, 1) : 0.0,
        ];
    }
}
