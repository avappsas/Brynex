<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Aliado extends BaseModel
{
    use SoftDeletes;

    protected $table = 'aliados';

    protected $fillable = [
        'nombre',
        'nit',
        'razon_social',
        'contacto',
        'telefono',
        'celular',
        'whatsapp',
        'correo',
        'direccion',
        'ciudad',
        'logo',
        'color_primario',
        'activo',
        'afiliaciones_brynex',
        'encargado_afil_id',
    ];

    protected $casts = [
        'activo'              => 'boolean',
        'afiliaciones_brynex' => 'boolean',
    ];

    // Usuario BryNex asignado por defecto como encargado de afiliación
    public function encargadoAfiliacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_afil_id');
    }

    // Usuarios que tienen este aliado como empresa principal
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'aliado_id');
    }

    // Usuarios BryNex con acceso a este aliado (pivot)
    public function usuariosBrynex(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'aliado_user', 'aliado_id', 'user_id')
                    ->withPivot('rol', 'activo')
                    ->withTimestamps();
    }

    // Scope: solo aliados activos
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // Configuración WhatsApp Business API de este aliado
    public function whatsappConfig(): HasOne
    {
        return $this->hasOne(WhatsappConfig::class, 'aliado_id');
    }

    // Módulos de Brynex contratados por este aliado
    public function modulosBrynex(): HasMany
    {
        return $this->hasMany(BrynexModuloAliado::class, 'aliado_id');
    }

    // Cobros mensuales de Brynex hacia este aliado
    public function cobrosBrynex(): HasMany
    {
        return $this->hasMany(BrynexCobroAliado::class, 'aliado_id')->orderByDesc('anio')->orderByDesc('mes');
    }
}
