<?php

namespace App\Models;

use App\Models\BaseModel;

class RazonSocial extends BaseModel
{
    protected $table = 'razones_sociales';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'dv', 'razon_social', 'estado', 'plan',
        'direccion', 'telefonos', 'correos',
        'actividad_economica', 'objeto_social', 'observacion',
        'salario_minimo', 'arl_nit', 'caja_nit',
        'mes_pagos', 'anio_pagos', 'n_plano',
        'fecha_constitucion', 'fecha_limite_pago', 'dia_habil',
        'forma_presentacion', 'codigo_sucursal', 'nombre_sucursal',
        'notas_factura1', 'notas_factura2',
        'dir_formulario', 'tel_formulario', 'correo_formulario',
        'cedula_rep', 'nombre_rep',
        'es_independiente',  // true = solo modalidades independientes (I Act, I Venc, Ext)
        'aliado_id', 'encargado_id',
    ];

    protected $casts = [
        'es_independiente' => 'boolean',
    ];

    /** Scope: activas para el aliado */
    public function scopeActivas($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId)->where('estado', 'Activa');
    }
}

