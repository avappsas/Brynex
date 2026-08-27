<?php

namespace App\Models;

/**
 * Soporte de una obligación: la declaración en PDF, el recibo de pago.
 *
 * Siempre en el disco `local` (storage/app), nunca en `public`. Una
 * declaración de renta con el NIT y las cifras de la empresa no puede quedar
 * accesible por URL — ver C-4 en docs/auditoria-seguridad.md.
 */
class BrynexObligacionDocumento extends BaseModel
{
    protected $table = 'brynex_obligacion_documentos';

    protected $fillable = [
        'obligacion_id', 'nombre_original', 'ruta', 'mime', 'tamano', 'subido_por',
    ];

    public function obligacion()
    {
        return $this->belongsTo(BrynexObligacion::class, 'obligacion_id');
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function tamanoLegible(): string
    {
        $bytes = (int) $this->tamano;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return max(1, round($bytes / 1024)).' KB';
    }
}
