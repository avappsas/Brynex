<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionGestion extends BaseModel
{
    protected $table = 'cotizacion_gestiones';

    protected $fillable = [
        'cotizacion_id',
        'user_id',
        'tipo_gestion',
        'descripcion',
        'resultado',
        'proxima_llamada',
    ];

    protected $casts = [
        'proxima_llamada' => 'date',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(CotizacionProspecto::class, 'cotizacion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
