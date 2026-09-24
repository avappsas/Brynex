<?php

namespace App\Models;

/**
 * Una línea de la bitácora de una clave de portal: qué se cambió y quién.
 *
 * Solo se escribe; nadie la edita. Ver ClaveAcceso::booted(), que la llena
 * sola en cualquier sitio que actualice una clave —el módulo, un comando o
 * tinker—, para que no dependa de acordarse.
 */
class ClaveAccesoCambio extends BaseModel
{
    protected $table = 'clave_acceso_cambios';

    public $timestamps = false;

    protected $fillable = [
        'clave_acceso_id', 'aliado_id', 'user_id',
        'usuario_anterior', 'contrasena_anterior',
        'usuario_nuevo', 'contrasena_nueva',
        'otros_cambios', 'motivo', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function clave()
    {
        return $this->belongsTo(ClaveAcceso::class, 'clave_acceso_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aliado()
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    /** Lo que cambió, en una línea, para listarlo sin abrir nada. */
    public function resumen(): string
    {
        $partes = [];

        if ($this->contrasena_anterior !== $this->contrasena_nueva) {
            $partes[] = 'contraseña';
        }

        if ($this->usuario_anterior !== $this->usuario_nuevo) {
            $partes[] = "usuario ({$this->usuario_anterior} → {$this->usuario_nuevo})";
        }

        foreach (json_decode((string) $this->otros_cambios, true) ?: [] as $campo => $valores) {
            $partes[] = $campo;
        }

        return $partes ? implode(', ', $partes) : 'sin cambios';
    }
}
