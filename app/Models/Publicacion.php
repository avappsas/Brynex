<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publicacion extends BaseModel
{
    protected $table = 'publicaciones';

    public const ESTADO_BORRADOR  = 'borrador';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA  = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';
    public const ESTADO_PUBLICADA = 'publicada';

    public const DESTINOS_DISPONIBLES = ['web', 'facebook', 'instagram'];

    protected $fillable = [
        'aliado_id',
        'titulo',
        'copy',
        'imagen_path',
        'origen',
        'plantilla_usada',
        'tema',
        'estilo_imagen',
        'pauta_estado',
        'pauta_presupuesto_diario_cop',
        'pauta_gasto_total_cop',
        'meta_campana_id',
        'meta_adset_id',
        'meta_ad_id',
        'pauta_activada_at',
        'estado',
        'destinos',
        'programada_at',
        'publicada_at',
        'resultado_publicacion',
        'creado_por',
        'aprobado_por',
        'motivo_rechazo',
    ];

    protected $casts = [
        'destinos'                     => 'array',
        'resultado_publicacion'        => 'array',
        'programada_at'                => 'datetime',
        'publicada_at'                 => 'datetime',
        'pauta_presupuesto_diario_cop' => 'decimal:2',
        'pauta_gasto_total_cop'        => 'decimal:2',
        'pauta_activada_at'            => 'datetime',
    ];

    protected $attributes = [
        'origen' => 'plantilla',
        'estado' => 'borrador',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function metricas()
    {
        return $this->hasMany(PublicacionMetrica::class);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopePublicadas($query)
    {
        return $query->where('estado', self::ESTADO_PUBLICADA);
    }

    /** ¿Ya pasó (o no tiene) fecha de programación, es decir, se puede publicar ya? */
    public function listaParaPublicar(): bool
    {
        return !$this->programada_at || $this->programada_at->isPast();
    }

    /** ID del post real de Facebook ({page_id}_{post_id}) para poder ponerle pauta encima — null si no se publicó ahí. */
    public function idPostFacebook(): ?string
    {
        $id = data_get($this->resultado_publicacion, 'facebook.id');
        return (is_string($id) && str_contains($id, '_')) ? $id : null;
    }

    /**
     * Mensaje precargado con el código de referencia ("ref: P{id}") — el MISMO texto se usa
     * en el link wa.me de la publicación orgánica y en el botón nativo de la pauta pagada,
     * para que WhatsappWebhookService::buscarPublicacionOrigen atribuya igual sin importar
     * de dónde vino el clic.
     */
    public function mensajeWhatsappRastreado(): string
    {
        return "Hola, vi esta publicación de {$this->aliado->nombre} y quiero información. (ref: P{$this->id})";
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::ESTADO_BORRADOR  => 'Borrador',
            self::ESTADO_PENDIENTE => 'Pendiente de aprobación',
            self::ESTADO_APROBADA  => $this->programada_at ? 'Aprobada — programada' : 'Aprobada — publicando',
            self::ESTADO_RECHAZADA => 'Rechazada',
            self::ESTADO_PUBLICADA => 'Publicada',
            default => $this->estado,
        };
    }
}
