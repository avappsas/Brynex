<?php

namespace App\Models;

use App\Models\BaseModel;

class ClaveAcceso extends BaseModel
{
    protected $table = 'clave_accesos';

    protected $fillable = [
        'aliado_id',
        'cedula',
        'razon_social_id',
        'empresa_id',
        'tipo',
        'entidad',
        'usuario',
        'contrasena',
        'link_acceso',
        'correo_entidad',
        'observacion',
        'activo',
    ];

    protected $casts = [
        'activo'   => 'boolean',
        'cedula'   => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cedula', 'cedula');
    }

    public function razonSocial()
    {
        return $this->belongsTo(\App\Models\RazonSocial::class, 'razon_social_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeAliado($query, int $aliadoId)
    {
        return $query->where('aliado_id', $aliadoId);
    }

    public function scopeDeCliente($query, $cedula)
    {
        return $query->where('cedula', $cedula);
    }

    public function scopeDeRazonSocial($query, int $razonSocialId)
    {
        return $query->where('razon_social_id', $razonSocialId);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
