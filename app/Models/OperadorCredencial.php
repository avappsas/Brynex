<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperadorCredencial extends BaseModel
{
    use SoftDeletes;

    protected $table = 'operadores_credenciales';

    protected $fillable = [
        'aliado_id',
        'razon_social_id',
        'operador_planilla_id',
        'usuario',
        'contrasena',
        'clave_secreta',
        'clave_secreta_expira_at',
        'config'
    ];

    /** Nunca serializar los secretos hacia una respuesta JSON o una vista. */
    protected $hidden = ['contrasena', 'clave_secreta'];

    protected $casts = [
        'config'                  => 'array',
        'contrasena'              => 'encrypted',
        'clave_secreta'           => 'encrypted',
        'clave_secreta_expira_at' => 'date',
    ];

    /**
     * Credencial vigente de un operador para el aliado.
     * Prioriza la credencial específica de la razón social; si no existe cae
     * a la credencial general del aliado (razon_social_id NULL), que es el
     * caso normal en Enlace: una sola cuenta autorizada sobre todos los NIT.
     */
    public function scopeParaOperador($query, int $aliadoId, int $operadorPlanillaId, ?int $razonSocialId = null)
    {
        return $query
            ->where('aliado_id', $aliadoId)
            ->where('operador_planilla_id', $operadorPlanillaId)
            ->where(function ($q) use ($razonSocialId) {
                $q->whereNull('razon_social_id');
                if ($razonSocialId) {
                    $q->orWhere('razon_social_id', $razonSocialId);
                }
            })
            ->orderByRaw('CASE WHEN razon_social_id IS NULL THEN 1 ELSE 0 END');
    }

    /** ¿La clave secreta ya venció? Enlace la caduca al año de generada. */
    public function claveSecretaVencida(): bool
    {
        return $this->clave_secreta_expira_at !== null
            && $this->clave_secreta_expira_at->isPast();
    }

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function operadorPlanilla()
    {
        return $this->belongsTo(OperadorPlanilla::class, 'operador_planilla_id');
    }
}
