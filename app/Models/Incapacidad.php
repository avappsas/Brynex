<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Incapacidad extends BaseModel
{
    use SoftDeletes;

    protected $table = 'incapacidades';

    protected $fillable = [
        'aliado_id',
        'incapacidad_padre_id',
        'numero_proroga',
        'contrato_id',
        'cedula_usuario',
        'quien_remite',
        'quien_recibe_id',
        'tipo_incapacidad',
        'dias_incapacidad',
        'fecha_inicio',
        'fecha_terminacion',
        'fecha_recibido',
        'prorroga',
        'tipo_entidad',
        'entidad_responsable_id',
        'entidad_nombre',
        'razon_social_id',
        'razon_social_nombre',
        'numero_radicado',
        'fecha_radicado',
        'transcripcion_requerida',
        'transcripcion_completada',
        'estado_pago',
        'fecha_pago',
        'valor_pago',
        'valor_esperado',
        'detalle_pago',
        'pagado_a',
        'ruta_soporte_pago',
        'diagnostico',
        'concepto_rehabilitacion',
        'observacion',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'fecha_inicio'             => 'date',
        'fecha_terminacion'        => 'date',
        'fecha_recibido'           => 'date',
        'fecha_radicado'           => 'date',
        'fecha_pago'               => 'date',
        'prorroga'                 => 'boolean',
        'transcripcion_requerida'  => 'boolean',
        'transcripcion_completada' => 'boolean',
        'valor_pago'               => 'decimal:2',
        'valor_esperado'           => 'decimal:2',
    ];

    // ── Tipos de incapacidad ─────────────────────────────────────────────────
    const TIPOS_INCAPACIDAD = [
        'enfermedad_general'   => '🤒 Enfermedad General',
        'licencia_maternidad'  => '🤱 Licencia Maternidad',
        'licencia_paternidad'  => '👶 Licencia Paternidad',
        'accidente_transito'   => '🚗 Accidente Tránsito',
        'accidente_laboral'    => '⚠️ Accidente Laboral',
    ];

    // ── Tipos de entidad ─────────────────────────────────────────────────────
    const TIPOS_ENTIDAD = [
        'eps' => 'EPS',
        'arl' => 'ARL',
        'afp' => 'AFP / Pensión',
    ];

    // ── Estados de pago ──────────────────────────────────────────────────────
    const ESTADOS_PAGO = [
        'pendiente'       => '⏳ Pendiente',
        'autorizado'      => '✅ Autorizado',
        'liquidado'       => '💰 Liquidado',
        'pagado_afiliado' => '🏦 Pagado al Afiliado',
        'rechazado'       => '❌ Rechazado',
    ];

    // ── Estados generales ────────────────────────────────────────────────────
    const ESTADOS = [
        'recibido'        => '📬 Recibido',
        'radicado'        => '📄 Radicado',
        'en_tramite'      => '🔵 En Trámite',
        'autorizado'      => '✅ Autorizado',
        'liquidado'       => '💰 Liquidado',
        'pagado_afiliado' => '🏦 Pagado al Afiliado',
        'rechazado'       => '❌ Rechazado',
        'cerrado'         => '⚫ Cerrado',
    ];

    // ── Tipos de gestión ─────────────────────────────────────────────────────
    const TIPOS_GESTION = [
        'llamada'            => '📞 Llamada',
        'correo'             => '📧 Correo',
        'whatsapp'           => '💬 WhatsApp',
        'portal'             => '🌐 Portal',
        'radico'             => '📋 Radicó en Entidad',
        'tutela'             => '⚖️ Tutela',
        'transcripcion_ips'  => '🏥 Transcripción IPS',
        'respuesta_entidad'  => '📩 Respuesta Entidad',
        'autorizacion'       => '✅ Autorización',
        'liquidacion'        => '💰 Liquidación',
        'pago_afiliado'      => '🏦 Pago al Afiliado',
        'otro'               => '📝 Otro',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Incapacidad::class, 'incapacidad_padre_id');
    }

    public function prorrogas(): HasMany
    {
        return $this->hasMany(Incapacidad::class, 'incapacidad_padre_id')->orderBy('numero_proroga');
    }

    public function gestiones(): HasMany
    {
        return $this->hasMany(GestionIncapacidad::class)->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        // Reutilizamos tabla radicados filtrando por incapacidad_id
        return $this->hasMany(Radicado::class, 'incapacidad_id')->orderByDesc('id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function quienRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quien_recibe_id');
    }

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers del cliente ──────────────────────────────────────────────────

    public function getClienteAttribute()
    {
        return DB::table('clientes')
            ->where('cedula', $this->cedula_usuario)
            ->select('id', 'cedula', 'primer_nombre', 'segundo_nombre',
                     'primer_apellido', 'segundo_apellido', 'celular', 'correo', 'cod_empresa')
            ->first();
    }

    public function getNombreClienteAttribute(): string
    {
        $c = $this->cliente;
        if (!$c) return $this->cedula_usuario;
        return trim(($c->primer_nombre ?? '') . ' ' . ($c->segundo_nombre ?? '') . ' ' .
                    ($c->primer_apellido ?? '') . ' ' . ($c->segundo_apellido ?? ''));
    }

    // ── Familia (padre + prórrogas) ──────────────────────────────────────────

    /**
     * Retorna el total de días de toda la familia (padre + prórrogas).
     * Si $this es una prórroga, sube al padre primero.
     */
    public function totalDiasFamilia(): int
    {
        $padreId = $this->incapacidad_padre_id ?? $this->id;
        return (int) DB::table('incapacidades')
            ->where(function ($q) use ($padreId) {
                $q->where('id', $padreId)
                  ->orWhere('incapacidad_padre_id', $padreId);
            })
            ->whereNull('deleted_at')
            ->sum('dias_incapacidad');
    }

    /**
     * Cantidad de prórrogas de esta incapacidad.
     */
    public function numeroProrrogas(): int
    {
        $padreId = $this->incapacidad_padre_id ?? $this->id;
        return (int) DB::table('incapacidades')
            ->where('incapacidad_padre_id', $padreId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * ¿La familia supera los 180 días de EPS?
     * Si es así se debe radicar al AFP/Pensión.
     */
    public function alertaDias180(): bool
    {
        if ($this->tipo_entidad !== 'eps') return false;
        return $this->totalDiasFamilia() >= 180;
    }

    // ── Semáforo (basado en días desde la última gestión) ────────────────────

    public function ultimaGestion(): ?GestionIncapacidad
    {
        return $this->gestiones()->first();
    }

    public function diasDesdeUltimaGestion(): int
    {
        $ultima = $this->ultimaGestion();
        if (!$ultima) {
            // Sin gestiones: usar días desde que se recibió
            return max(0, (int) now()->diffInDays($this->created_at));
        }
        return max(0, (int) now()->diffInDays($ultima->created_at));
    }

    /**
     * Color semáforo:
     *  verde   → <5 días sin gestión
     *  amarillo→ 5–10 días
     *  rojo    → >10 días
     *  gris    → cerrado / pagado al afiliado
     */
    public function colorSemaforo(): string
    {
        if (in_array($this->estado, ['cerrado', 'pagado_afiliado', 'rechazado'])) {
            return 'gris';
        }

        $dias = $this->diasDesdeUltimaGestion();

        if ($dias <= 4) return 'verde';
        if ($dias <= 10) return 'amarillo';
        return 'rojo';
    }

    public function iconoSemaforo(): string
    {
        return match($this->colorSemaforo()) {
            'verde'   => '🟢',
            'amarillo'=> '🟡',
            'rojo'    => '🔴',
            default   => '⚫',
        };
    }

    // ── Cálculo de valor esperado ────────────────────────────────────────────

    /**
     * Calcula el valor esperado según la entidad y los días.
     *
     * EPS:
     *   - Solo paga si la incapacidad es >= 3 días
     *   - Si NO es prórroga: descuenta los 2 primeros días
     *   - Si ES prórroga: paga todos los días
     *   - Base: salario mínimo mensual → diario = smmlv / 30
     *
     * ARL:
     *   - Paga desde el día 1, todos los días
     *   - Base: salario mínimo mensual / 30
     *
     * AFP:
     *   - El sistema solo genera alerta; no calcula pago directamente
     *
     * @param float $smmlv  Salario Mínimo Mensual Legal Vigente
     */
    public function calcularValorEsperado(float $smmlv = 1423500): float
    {
        $dias = (int) $this->dias_incapacidad;

        if ($dias <= 0) return 0;

        $valorDiario = $smmlv / 30;

        switch ($this->tipo_entidad) {
            case 'eps':
                if ($dias < 3) return 0; // EPS no paga menos de 3 días
                $diasPagados = $this->prorroga ? $dias : ($dias - 2);
                return round(max(0, $diasPagados) * $valorDiario, 2);

            case 'arl':
                return round($dias * $valorDiario, 2);

            case 'afp':
                // El afp continúa pagando; aquí se muestra el valor referencial
                return round($dias * $valorDiario, 2);

            default:
                return 0;
        }
    }

    // ── Labels ───────────────────────────────────────────────────────────────

    public function tipoIncapacidadLabel(): string
    {
        return self::TIPOS_INCAPACIDAD[$this->tipo_incapacidad] ?? ucfirst($this->tipo_incapacidad);
    }

    public function tipoEntidadLabel(): string
    {
        return self::TIPOS_ENTIDAD[$this->tipo_entidad] ?? strtoupper($this->tipo_entidad);
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst($this->estado);
    }

    public function estadoPagoLabel(): string
    {
        return self::ESTADOS_PAGO[$this->estado_pago] ?? ucfirst($this->estado_pago);
    }

    public function estadoPagoColor(): string
    {
        return match($this->estado_pago) {
            'pendiente'       => 'warning',
            'autorizado'      => 'info',
            'liquidado'       => 'primary',
            'pagado_afiliado' => 'success',
            'rechazado'       => 'danger',
            default           => 'secondary',
        };
    }
}
