<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Tarea extends Model
{
    use SoftDeletes;

    protected $table = 'tareas';

    protected $fillable = [
        'aliado_id', 'tipo', 'estado', 'resultado',
        'cedula', 'contrato_id', 'razon_social_id', 'entidad',
        'tarea', 'observacion',
        'encargado_id', 'creado_por',
        'fecha_limite', 'fecha_alerta',
        'fecha_radicado', 'numero_radicado', 'correo',
    ];

    protected $casts = [
        'fecha_limite'   => 'date',
        'fecha_alerta'   => 'date',
        'fecha_radicado' => 'date',
    ];

    // ── Constantes de Tipo ──────────────────────────────────────────────────
    const TIPOS = [
        'traslado_eps'           => '🔄 Traslado EPS',
        'inclusion_beneficiarios'=> '👥 Inclusión Beneficiarios',
        'exclusion'              => '❌ Exclusión',
        'subsidios'              => '💰 Subsidios',
        'actualizar_documentos'  => '📄 Actualizar Documentos',
        'devolucion_aportes'     => '💵 Devolución de Aportes',
        'solicitud_documentos'   => '📋 Solicitud Documentos',
        'otros'                  => '📝 Otros',
    ];

    // ── Constantes de Estado ────────────────────────────────────────────────
    const ESTADO_PENDIENTE  = 'pendiente';
    const ESTADO_EN_GESTION = 'en_gestion';
    const ESTADO_EN_ESPERA  = 'en_espera';
    const ESTADO_CERRADA    = 'cerrada';

    const ESTADOS = [
        'pendiente'  => '⏳ Pendiente',
        'en_gestion' => '🔵 En Gestión',
        'en_espera'  => '🟠 En Espera',
        'cerrada'    => '✅ Cerrada',
    ];

    const ESTADOS_ACTIVOS = ['pendiente', 'en_gestion', 'en_espera'];

    // ── Relaciones ──────────────────────────────────────────────────────────
    public function encargado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function gestiones(): HasMany
    {
        return $this->hasMany(TareaGestion::class)->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TareaDocumento::class)->orderByDesc('id');
    }

    // ── Helpers de Semáforo ─────────────────────────────────────────────────

    /**
     * Color del semáforo: verde / amarillo / rojo / azul / gris
     */
    public function colorSemaforo(): string
    {
        if ($this->estado === self::ESTADO_CERRADA) {
            return 'gris';
        }

        // Tarea en espera: si fecha_alerta llegó => azul (urgente consultar)
        if ($this->estado === self::ESTADO_EN_ESPERA) {
            if ($this->fecha_alerta && $this->fecha_alerta->isPast()) {
                return 'azul';
            }
            return 'naranja';
        }

        if (!$this->fecha_limite) {
            return 'verde';
        }

        $diasRestantes = now()->startOfDay()->diffInDays($this->fecha_limite->startOfDay(), false);

        if ($diasRestantes < 0) {
            return 'rojo';
        }

        // Obtener umbral amarillo para este tipo
        $config = TareaSemaforoConfig::configParaTipo($this->tipo, $this->aliado_id);
        $umbralAmarillo = $config ? $config->dias_alerta_amarilla : 5;

        return $diasRestantes <= $umbralAmarillo ? 'amarillo' : 'verde';
    }

    public function iconoSemaforo(): string
    {
        return match($this->colorSemaforo()) {
            'verde'   => '🟢',
            'amarillo'=> '🟡',
            'rojo'    => '🔴',
            'azul'    => '🔵',
            'naranja' => '🟠',
            default   => '⚫',
        };
    }

    public function diasRestantes(): int
    {
        if (!$this->fecha_limite) return 999;
        return (int) now()->startOfDay()->diffInDays($this->fecha_limite->startOfDay(), false);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst($this->estado);
    }

    // ── Helpers del cliente ─────────────────────────────────────────────────
    public function getClienteAttribute()
    {
        return DB::table('clientes')
            ->where('cedula', $this->cedula)
            ->select('id', 'cedula', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'celular', 'correo')
            ->first();
    }

    public function getNombreClienteAttribute(): string
    {
        $c = $this->cliente;
        if (!$c) return $this->cedula;
        return trim(($c->primer_nombre ?? '') . ' ' . ($c->segundo_nombre ?? '') . ' ' .
                    ($c->primer_apellido ?? '') . ' ' . ($c->segundo_apellido ?? ''));
    }
}
