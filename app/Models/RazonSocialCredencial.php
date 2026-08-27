<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clave de un portal crítico de la razón social (DIAN, banco, cámara de
 * comercio). La contraseña se guarda cifrada y nunca sale en el listado:
 * se revela una a una por un endpoint que deja rastro en la bitácora.
 */
class RazonSocialCredencial extends BaseModel
{
    use SoftDeletes;

    protected $table = 'razon_social_credenciales';

    protected $fillable = [
        'aliado_id',
        'razon_social_id',
        // Llave real desde el módulo de BryNex: la clave es del NIT, no de la
        // fila de razón social de un aliado. Eso es lo que hace que si un
        // aliado la cambia, el otro vea el cambio.
        'ficha_id',
        'tipo',
        'entidad',
        'link_acceso',
        'usuario',
        'contrasena',
        'observacion',
        'activo',
        'creado_por',
        'actualizado_por',
    ];

    /** Nunca serializar el secreto hacia una respuesta JSON o una vista. */
    protected $hidden = ['contrasena'];

    protected $casts = [
        'contrasena' => 'encrypted',
        'activo' => 'boolean',
    ];

    /** Tipos válidos y su etiqueta. El nombre del portal va en `entidad`. */
    public const TIPOS = [
        'DIAN' => '🏛️ DIAN',
        'BANCO' => '🏦 Banco',
        'CAMARA_COMERCIO' => '📜 Cámara de Comercio',
        'OTRO' => '🔗 Otro portal',
    ];

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function ficha()
    {
        return $this->belongsTo(BrynexRazonSocial::class, 'ficha_id');
    }

    public function scopeDeFicha($query, int $fichaId)
    {
        return $query->where('ficha_id', $fichaId);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopeDeAliado($query, int $aliadoId)
    {
        return $query->where('aliado_id', $aliadoId);
    }

    public function scopeDeRazonSocial($query, int $razonSocialId)
    {
        return $query->where('razon_social_id', $razonSocialId);
    }

    public function tipoEtiqueta(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
