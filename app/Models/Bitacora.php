<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Bitacora extends Model
{
    public $timestamps = false; // Solo created_at manual

    protected $table = 'bitacora';

    protected $fillable = [
        'aliado_id',
        'user_id',
        'accion',
        'modelo',
        'registro_id',
        'descripcion',
        'detalle',
        'ip',
        'created_at',
    ];

    // ── Helper estático principal ───────────────────────────
    /**
     * Registra un evento en la bitácora.
     *
     * @param string     $accion      created | updated | deleted
     * @param string     $modelo      Cliente | Beneficiario | DocumentoCliente
     * @param int|null   $registroId  ID del registro afectado
     * @param string     $descripcion Resumen legible
     * @param array|null $detalle     Datos adicionales (diff, snapshot, etc.)
     * @param int|null   $alidoId     null = toma el del contexto de sesión
     */
    public static function registrar(
        string $accion,
        string $modelo,
        ?int $registroId,
        string $descripcion,
        ?array $detalle = null,
        ?int $alidoId = null
    ): void {
        try {
            $alidoId = $alidoId ?? session('aliado_id_activo');

            static::insert([
                'aliado_id'   => $alidoId,
                'user_id'     => Auth::id(),
                'accion'      => $accion,
                'modelo'      => $modelo,
                'registro_id' => $registroId,
                'descripcion' => $descripcion,
                'detalle'     => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
                'ip'          => Request::ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // La bitácora nunca debe romper el flujo principal
            \Log::warning('Bitacora::registrar falló: ' . $e->getMessage());
        }
    }

    // ── Relaciones ─────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeDeAliado($query, $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }

    public function scopeModelo($query, string $modelo)
    {
        return $query->where('modelo', $modelo);
    }

    // ── Accesor detalle ─────────────────────────────────────
    public function getDetalleArrayAttribute(): array
    {
        return $this->detalle ? json_decode($this->detalle, true) : [];
    }
}
