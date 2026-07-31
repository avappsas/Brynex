<?php

namespace App\Models;

use App\Models\BaseModel;

class Empresa extends BaseModel
{
    protected $table = 'empresas';

    /**
     * Lista blanca explícita. Antes era `$guarded = []`, que permitía escribir
     * cualquier columna vía asignación masiva. `id` y `aliado_id` van incluidos
     * a propósito: la tabla es legacy sin IDENTITY y ambos se asignan de forma
     * deliberada al crear la empresa (ver FacturacionController::storeEmpresa).
     */
    protected $fillable = [
        'id',
        'nit',
        'empresa',
        'contacto',
        'telefono',
        'celular',
        'direccion',
        'observacion',
        'cliente_de',
        'tipo_facturacion',
        'iva',
        'correo',
        'actividad_economica',
        'aliado_id',
        'encargado_id',
        'asesor_id',
    ];

    public function aliado()
    {
        return $this->belongsTo(Aliado::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'cod_empresa', 'id');
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
