<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un portal esperando a que una persona entre.
 *
 * Existe porque hay portales donde el servidor no puede entrar solo —S.O.S. pide
 * reCAPTCHA en el login— y el trabajo lo hace la extensión dentro de la sesión
 * que abre una persona. La petición es lo que convierte ese "alguien tiene que
 * entrar" en algo que se puede avisar, medir y cerrar.
 *
 * Se abre cuando hay trabajo, se avisa una vez por WhatsApp y se cierra cuando
 * la revisión se hace. Si nadie entra, vence: la siguiente corrida abre otra en
 * vez de insistir sobre la misma.
 */
class PortalPeticion extends BaseModel
{
    protected $table = 'portal_peticiones';

    public const ABIERTA = 'abierta';

    public const ATENDIDA = 'atendida';

    public const VENCIDA = 'vencida';

    /** Días que espera una petición antes de darse por vencida. */
    public const DIAS_DE_VIDA = 6;

    protected $fillable = [
        'aliado_id', 'entidad', 'nit', 'empresa', 'motivo', 'pendientes',
        'estado', 'aviso_enviado_at', 'avisos', 'atendida_at', 'atendida_por', 'resultado',
    ];

    protected $casts = [
        'pendientes' => 'integer',
        'avisos' => 'integer',
        'aviso_enviado_at' => 'datetime',
        'atendida_at' => 'datetime',
    ];

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    /**
     * La petición abierta de esa entidad, si hay alguna.
     *
     * Por entidad y no por empresa: quien entra al portal entra una vez y
     * aprovecha para todas las empresas que pueda, así que pedir una por empresa
     * serían siete avisos para un solo viaje.
     */
    public static function abiertaDe(string $entidad, ?string $nit = null): ?self
    {
        return static::where('entidad', $entidad)
            ->where('estado', self::ABIERTA)
            ->when($nit, fn ($q) => $q->where(fn ($w) => $w->whereNull('nit')->orWhere('nit', $nit)))
            ->latest('id')
            ->first();
    }

    /** Cierra las que llevan demasiado esperando; devuelve cuántas. */
    public static function vencerViejas(string $entidad): int
    {
        return static::where('entidad', $entidad)
            ->where('estado', self::ABIERTA)
            ->where('created_at', '<', now()->subDays(self::DIAS_DE_VIDA))
            ->update(['estado' => self::VENCIDA, 'resultado' => 'Nadie entró al portal en '.self::DIAS_DE_VIDA.' días.']);
    }

    public function atender(?int $usuarioId, string $resultado): bool
    {
        return $this->update([
            'estado' => self::ATENDIDA,
            'atendida_at' => now(),
            'atendida_por' => $usuarioId,
            'resultado' => mb_substr($resultado, 0, 400),
        ]);
    }
}
