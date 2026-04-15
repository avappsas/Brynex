<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresas';
    protected $guarded = [];

    public function aliado()
    {
        return $this->belongsTo(Aliado::class);
    }

    public function asesor()
    {
        return $this->belongsTo(\App\Models\Asesor::class, 'asesor_id');
    }

    /**
     * Etiqueta para mostrar — si es Id=1 devuelve "Individual"
     */
    public function getLabelAttribute(): string
    {
        return (int) $this->id === 1
            ? 'Individual'
            : ($this->empresa ?: "Empresa #{$this->id}");
    }

    /**
     * Lista para selects: id => nombre
     */
    public static function listaParaSelect(?int $aliadoId = null)
    {
        return static::when($aliadoId, fn($q) => $q->where('aliado_id', $aliadoId))
            ->orderBy('empresa')
            ->get(['id', 'empresa'])
            ->mapWithKeys(fn($e) => [
                $e->id => (int)$e->id === 1 ? '— Individual —' : $e->empresa
            ]);
    }
}
