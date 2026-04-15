<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadicadoMovimiento extends Model
{
    public $timestamps = false;

    protected $table = 'radicado_movimientos';

    protected $fillable = [
        'radicado_id',
        'contrato_id',
        'tipo_proceso',
        'entidad',
        'user_id',
        'estado_anterior',
        'estado_nuevo',
        'observacion',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ── Tipos de proceso ──
    const TIPO_AFILIACION            = 'afiliacion';
    const TIPO_INCAPACIDAD           = 'incapacidad';
    const TIPO_TUTELA                = 'tutela';
    const TIPO_DERECHO_PETICION      = 'derecho_peticion';
    const TIPO_INCLUSION_BENEFICIARIO= 'inclusion_beneficiario';
    const TIPO_TRASLADO_EPS          = 'traslado_eps';
    const TIPO_OTRO                  = 'otro';

    public static function tiposProceso(): array
    {
        return [
            self::TIPO_AFILIACION             => 'Afiliación',
            self::TIPO_INCAPACIDAD            => 'Incapacidad',
            self::TIPO_TUTELA                 => 'Tutela',
            self::TIPO_DERECHO_PETICION       => 'Derecho de Petición',
            self::TIPO_INCLUSION_BENEFICIARIO => 'Inclusión Beneficiario',
            self::TIPO_TRASLADO_EPS           => 'Traslado EPS',
            self::TIPO_OTRO                   => 'Otro',
        ];
    }

    // ── Relaciones ──
    public function radicado(): BelongsTo
    {
        return $this->belongsTo(Radicado::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──
    public function tipoLabel(): string
    {
        return self::tiposProceso()[$this->tipo_proceso] ?? ucfirst($this->tipo_proceso);
    }

    public function entidadLabel(): string
    {
        return match($this->entidad) {
            'eps'     => 'EPS',
            'arl'     => 'ARL',
            'caja'    => 'Caja',
            'pension' => 'Pensión',
            default   => strtoupper($this->entidad ?? ''),
        };
    }
}
