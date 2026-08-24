<?php

namespace App\Models;

/**
 * Configuración de facturación electrónica por API para una razón social
 * emisora. Hoy solo existe la de BRYGAR SAS (aliado 2, razón social 42).
 *
 * El `auth_token` sigue el patrón de [[RazonSocialCredencial]]: cast
 * `encrypted` para que nunca toque disco en claro y `$hidden` para que no se
 * escape en un `toJson()` de una vista o una respuesta AJAX.
 */
class DataicoConfiguracion extends BaseModel
{
    protected $table = 'dataico_configuraciones';

    protected $fillable = [
        'aliado_id',
        'razon_social_id',
        'activo',
        'modo',
        'hora_cierre',
        'banco_cuenta_id',
        'fecha_inicio',
        'dataico_account_id',
        'auth_token',
        'numbering_range_id',
        'prefijo',
        'resolucion',
        'correo_fallback',
        'enviar_email',
        'consumidor_final',
        'observacion',
    ];

    protected $hidden = ['auth_token'];

    protected $casts = [
        'activo' => 'boolean',
        'enviar_email' => 'boolean',
        'consumidor_final' => 'boolean',
        'fecha_inicio' => 'date',
        'auth_token' => 'encrypted',
    ];

    /** Se emite al quedar pagada la factura. */
    public const MODO_FACTURA = 'factura';

    /** Se emite en el cierre del día, a `hora_cierre`. */
    public const MODO_DIARIO = 'diario';

    public const MODOS = [
        self::MODO_FACTURA => 'Automático por factura',
        self::MODO_DIARIO => 'Automático al cerrar el día',
    ];

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function bancoCuenta()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id');
    }

    /** Configuración activa y utilizable de un aliado, o null. */
    public static function activaDe(int $aliadoId): ?self
    {
        return static::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->first();
    }

    /** Todas las configuraciones activas con credenciales completas. */
    public static function operativas()
    {
        return static::where('activo', true)
            ->whereNotNull('dataico_account_id')
            ->whereNotNull('auth_token')
            ->get()
            ->filter(fn (self $c) => $c->estaCompleta());
    }

    /** ¿Tiene lo mínimo para llamar al API sin que rebote? */
    public function estaCompleta(): bool
    {
        return filled($this->dataico_account_id)
            && filled($this->auth_token)
            && $this->banco_cuenta_id !== null
            && $this->fecha_inicio !== null;
    }
}
