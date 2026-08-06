<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Una entrega de datos a un aliado: el reto por WhatsApp, la bitácora y la
 * traza del archivo, todo en el mismo registro.
 *
 * Ver la migración `create_exportaciones_aliado_table` para el porqué de que
 * el reto viva en BD y no en caché.
 */
class ExportacionAliado extends BaseModel
{
    protected $table = 'exportaciones_aliado';

    protected $fillable = [
        'aliado_id', 'solicitado_por', 'estado', 'codigo_hash', 'codigo_expira_at',
        'intentos', 'confirmado_at', 'archivo', 'archivo_hash', 'archivo_bytes',
        'zip_password', 'filas_total', 'resumen', 'traza_token', 'ip',
        'descargas', 'ultima_descarga_at', 'purgado_at', 'error',
    ];

    protected $casts = [
        'codigo_expira_at' => 'datetime',
        'confirmado_at' => 'datetime',
        'ultima_descarga_at' => 'datetime',
        'purgado_at' => 'datetime',
        'archivo_bytes' => 'integer',
        'filas_total' => 'integer',
        'intentos' => 'integer',
        'descargas' => 'integer',
    ];

    public function aliado()
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    // ── Reto por WhatsApp ────────────────────────────────────────────────

    /** Genera el código, lo guarda hasheado y devuelve el texto plano. */
    public function generarCodigo(): string
    {
        $codigo = (string) random_int(100000, 999999);

        $this->update([
            'codigo_hash' => Hash::make($codigo),
            'codigo_expira_at' => now()->addMinutes((int) config('exportacion.codigo_minutos')),
            'intentos' => 0,
        ]);

        return $codigo;
    }

    public function codigoVencido(): bool
    {
        return ! $this->codigo_expira_at || $this->codigo_expira_at->isPast();
    }

    /**
     * Comprueba el código. Consume un intento siempre que falle, para que
     * probar los 900.000 códigos no sea una opción.
     */
    public function codigoValido(string $codigo): bool
    {
        if ($this->estado !== 'pendiente' || $this->codigoVencido() || ! $this->codigo_hash) {
            return false;
        }

        if (Hash::check(trim($codigo), $this->codigo_hash)) {
            return true;
        }

        $this->increment('intentos');

        if ($this->intentos >= (int) config('exportacion.codigo_intentos')) {
            $this->update(['estado' => 'cancelado', 'error' => 'Código incorrecto demasiadas veces.']);
        }

        return false;
    }

    // ── Contraseña del ZIP ───────────────────────────────────────────────

    public function guardarPassword(string $password): void
    {
        $this->update(['zip_password' => Crypt::encryptString($password)]);
    }

    public function passwordPlano(): ?string
    {
        if (! $this->zip_password) {
            return null;
        }

        try {
            return Crypt::decryptString($this->zip_password);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Estado del archivo ───────────────────────────────────────────────

    public function disponible(): bool
    {
        return $this->estado === 'generado'
            && $this->archivo
            && ! $this->purgado_at;
    }

    /** @return array<string,int> filas por archivo */
    public function resumenArchivos(): array
    {
        return json_decode((string) $this->resumen, true) ?: [];
    }

    public function tamanoLegible(): string
    {
        $valor = (float) $this->archivo_bytes;

        foreach (['B', 'KB', 'MB', 'GB'] as $unidad) {
            if ($valor < 1024 || $unidad === 'GB') {
                return round($valor, $unidad === 'B' ? 0 : 1).' '.$unidad;
            }

            $valor /= 1024;
        }

        return $this->archivo_bytes.' B';
    }
}
