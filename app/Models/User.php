<?php

namespace App\Models;

use App\Traits\HasSqlServerDates;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasSqlServerDates;
    use Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'aliado_id',
        'nombre',
        'email',
        'password',
        'cedula',
        'telefono',
        'es_brynex',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'es_brynex'         => 'boolean',
        'activo'            => 'boolean',
    ];

    // Aliado principal del usuario
    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    // Aliados extra a los que tiene acceso (pivot - solo usuarios BryNex)
    public function aliados(): BelongsToMany
    {
        return $this->belongsToMany(Aliado::class, 'aliado_user', 'user_id', 'aliado_id')
                    ->withPivot('rol', 'activo')
                    ->withTimestamps();
    }

    // Verifica si el usuario puede acceder a un aliado dado
    public function puedeAccederAliado(int $alidoId): bool
    {
        if ($this->aliado_id === $alidoId) {
            return true;
        }
        if ($this->es_brynex) {
            return $this->aliados()
                        ->where('aliados.id', $alidoId)
                        ->where('aliados.activo', true)
                        ->wherePivot('activo', true)
                        ->exists();
        }
        return false;
    }

    // Obtiene el aliado activo en sesión (el principal o el seleccionado por BryNex)
    public function alidoActivo(): Aliado
    {
        $alidoIdSesion = session('aliado_id_activo');
        if ($this->es_brynex && $alidoIdSesion) {
            return Aliado::find($alidoIdSesion) ?? $this->aliado;
        }
        return $this->aliado;
    }
}
