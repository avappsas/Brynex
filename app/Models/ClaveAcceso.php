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

    /**
     * Las claves que ese aliado puede ver: las suyas y las de las empresas que
     * comparte con otro aliado.
     *
     * La clave es **de la empresa**, no de quien la factura: la misma razón
     * social existe como una fila por aliado, pero ante la entidad es una sola
     * y el usuario y la contraseña son los mismos. Los procesos automáticos ya
     * la buscaban así —por NIT, sin mirar el aliado—, de modo que un aliado
     * dependía de una clave que no podía ver ni corregir.
     *
     * Se comparte por NIT, que es lo que identifica a la empresa ante la
     * entidad. Las claves de persona (por cédula) no entran aquí.
     */
    public function scopeVisiblesPara($query, int $aliadoId)
    {
        return $query->where(function ($q) use ($aliadoId) {
            $q->where('aliado_id', $aliadoId)
                ->orWhereIn('razon_social_id', function ($sub) use ($aliadoId) {
                    $sub->select('suyas.id')
                        ->from('razones_sociales as suyas')
                        ->whereIn('suyas.nit', function ($nits) use ($aliadoId) {
                            $nits->select('nit')
                                ->from('razones_sociales')
                                ->where('aliado_id', $aliadoId)
                                ->whereNotNull('nit')
                                ->where('nit', '<>', '');
                        });
                });
        });
    }

    /** ¿Esta clave la cargó otro aliado? Se muestra para saber a quién avisar. */
    public function esDeOtroAliado(?int $aliadoId = null): bool
    {
        return (int) $this->aliado_id !== (int) ($aliadoId ?: session('aliado_id_activo'));
    }

    public function aliado()
    {
        return $this->belongsTo(\App\Models\Aliado::class, 'aliado_id');
    }

    // ─── Relaciones ───────────────────────────────────────────────────

    /**
     * La cédula se repite entre aliados: sin el aliado en la relación, un
     * with('cliente') trae el cliente de cualquiera. Ver BelongsToDelAliado.
     */
    public function cliente()
    {
        return $this->belongsToDelAliado(Cliente::class, 'cedula', 'cedula', 'cliente');
    }

    public function razonSocial()
    {
        return $this->belongsTo(\App\Models\RazonSocial::class, 'razon_social_id');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
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
