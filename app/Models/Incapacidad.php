<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        'salario_base',
        'detalle_pago',
        'pagado_a',
        'pagado_a_tipo',
        'pagado_a_cliente_id',
        'pagado_a_empresa_id',
        'ruta_soporte_pago',
        'diagnostico',
        'concepto_rehabilitacion',
        'observacion',
        'descripcion_cliente',
        'estado',
        'token_subida',
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
        'salario_base'             => 'decimal:2',
    ];

    // ════════════════════════════════════════════════════════════════════════
    // CATÁLOGOS
    // ════════════════════════════════════════════════════════════════════════

    const TIPOS_INCAPACIDAD = [
        'enfermedad_general'   => '🤒 Enfermedad General',
        'licencia_maternidad'  => '🤱 Licencia Maternidad',
        'licencia_paternidad'  => '👶 Licencia Paternidad',
        'accidente_transito'   => '🚗 Accidente Tránsito',
        'accidente_laboral'    => '⚠️ Accidente Laboral',
    ];

    const TIPOS_ENTIDAD = [
        'eps' => 'EPS',
        'arl' => 'ARL',
        'afp' => 'AFP / Pensión',
    ];

    /**
     * Estados del proceso de gestión de la incapacidad.
     * Flujo: recibido → transcripcion → radicada → liquidacion → pagada
     *                                      ↓
     *                                   negada → tutela → tutela_radicada → liquidacion → pagada
     *                                      ↓
     *                                  rechazado (final)
     *
     * Solo cambian estado las gestiones de tipo:
     *   radico, negada, tutela, tutela_radicada, liquidacion, pago, rechazado
     * El estado 'negada' se asigna manualmente desde la vista de detalle.
     */
    const ESTADOS = [
        // ── Ciclo Normal ─────────────────────────────────────────────────────
        'recibido'                  => ['label' => '📬 Recibido',                     'color' => 'secondary'],
        'transcripcion_ips'         => ['label' => '🏥 Transcripción IPS',            'color' => 'info'],
        'radicada'                  => ['label' => '📋 Radicada',                     'color' => 'primary'],
        // ── Ciclo Negado ──────────────────────────────────────────────────────
        'negada'                    => ['label' => '🚫 Negada',                       'color' => 'danger'],
        'derecho_peticion'          => ['label' => '📄 Derecho de Petición',          'color' => 'warning'],
        'derecho_peticion_radicado' => ['label' => '📄 D. Petición Radicado',         'color' => 'warning'],
        'tutela'                    => ['label' => '⚖️ Tutela',                        'color' => 'warning'],
        'tutela_radicada'           => ['label' => '📜 Tutela Radicada',              'color' => 'warning'],
        'rechazado'                 => ['label' => '❌ Rechazado',                    'color' => 'danger'],
        // ── Ciclo de Pago ─────────────────────────────────────────────────────
        'en_liquidacion'            => ['label' => '💰 En Liquidación',              'color' => 'info'],
        'pagada_razon_social'       => ['label' => '🏢 Pagada a Razón Social',       'color' => 'info'],
        'pagada_afiliado'           => ['label' => '🏦 Pagada al Afiliado',          'color' => 'success'],
        'cierre_exitoso'            => ['label' => '✅ Cierre Exitoso',              'color' => 'success'],
        // ── Legacy (no mostrar en selector) ──────────────────────────────────
        'pagada'                    => ['label' => '✅ Pagada (legacy)',              'color' => 'success', 'legacy' => true],
        'liquidacion'               => ['label' => '💰 En Liquidación (legacy)',     'color' => 'info',    'legacy' => true],
        'transcripcion'             => ['label' => '🏥 Transcripción (legacy)',      'color' => 'info',    'legacy' => true],
    ];


    /**
     * Estados de pago del valor de la incapacidad.
     * Independiente del estado del proceso.
     */
    const ESTADOS_PAGO = [
        'pendiente'       => ['label' => '⏳ Pendiente',          'color' => 'warning'],
        'pagado_afiliado' => ['label' => '🏦 Pagado al Afiliado', 'color' => 'success'],
        'rechazado'       => ['label' => '❌ Rechazado',          'color' => 'danger'],
    ];

    /**
     * Tipos de gestión (canales de contacto).
     * Ninguno cambia el estado automáticamente — el estado se
     * actualiza manualmente desde el selector de estado en el modal.
     */
    const TIPOS_GESTION = [
        'llamada'  => ['label' => '📞 Llamada',         'cambia_estado' => false, 'nuevo_estado' => null],
        'correo'   => ['label' => '📧 Correo',          'cambia_estado' => false, 'nuevo_estado' => null],
        'whatsapp' => ['label' => '💬 WhatsApp',        'cambia_estado' => false, 'nuevo_estado' => null],
        'portal'   => ['label' => '🌐 Portal Web',       'cambia_estado' => false, 'nuevo_estado' => null],
        'otro'     => ['label' => '📝 Otro',            'cambia_estado' => false, 'nuevo_estado' => null],
    ];

    // ════════════════════════════════════════════════════════════════════════
    // RELACIONES
    // ════════════════════════════════════════════════════════════════════════

    /** Incapacidad padre (si esta es una prórroga) */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Incapacidad::class, 'incapacidad_padre_id');
    }

    /** Prórrogas de esta incapacidad (solo hijas directas) */
    public function prorrogas(): HasMany
    {
        return $this->hasMany(Incapacidad::class, 'incapacidad_padre_id')
                    ->orderBy('numero_proroga');
    }

    /** Todas las gestiones (con cambio de estado y seguimiento) */
    public function gestiones(): HasMany
    {
        return $this->hasMany(GestionIncapacidad::class)->orderByDesc('id');
    }

    /**
     * Solo la última gestión — eager-loadable para evitar N+1 en el index.
     * Usar: $query->with('latestGestion')
     */
    public function latestGestion(): HasOne
    {
        return $this->hasOne(GestionIncapacidad::class)->latestOfMany('id');
    }

    /** Documentos del cliente (radicados con incapacidad_id) */
    public function documentos(): HasMany
    {
        return $this->hasMany(Radicado::class, 'incapacidad_id')->orderByDesc('id');
    }

    /** Abonos, préstamos y pagos ligados a esta incapacidad */
    public function abonos(): HasMany
    {
        return $this->hasMany(AbonoIncapacidad::class)->orderBy('fecha')->orderBy('id');
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

    // ════════════════════════════════════════════════════════════════════════
    // CÁLCULO DE VALOR ESPERADO
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Calcula y persiste el valor_esperado usando el salario_base guardado.
     *
     * Reglas:
     *  - EPS original (prorroga=false): (salario/30) × (días - 2)  — mínimo 3 días para pagar
     *  - EPS prórroga  (prorroga=true) : (salario/30) × días       — sin descuento
     *  - ARL / AFP                     : (salario/30) × días       — 100%, desde día 1
     *
     * El salario_base se guarda al crear la incapacidad (de contratos.salario).
     * Si no hay salario_base se retorna null sin persistir.
     *
     * @param bool $persistir  Si true guarda el resultado en valor_esperado.
     * @return float|null
     */
    public function calcularValorEsperado(bool $persistir = false): ?float
    {
        if ($this->salario_base === null || (float)$this->salario_base <= 0) {
            $this->resolverYGuardarSalario();
        }

        $salario = (float) ($this->salario_base ?? 0);
        $dias    = (int)   ($this->dias_incapacidad ?? 0);

        if ($salario <= 0 || $dias <= 0) {
            return null;
        }

        $valorDiario = $salario / 30;
        $esProrroga  = (bool) $this->prorroga || ($this->incapacidad_padre_id !== null);

        $valor = match($this->tipo_entidad) {
            'eps' => $dias < 3 ? 0.0
                               : round(max(0, ($esProrroga ? $dias : $dias - 2)) * $valorDiario, 2),
            'arl',
            'afp' => round($dias * $valorDiario, 2),
            default => 0.0,
        };

        if ($persistir) {
            $this->valor_esperado = $valor;
            $this->saveQuietly();
        }

        return $valor;
    }

    /**
     * Retorna el salario del contrato activo al momento de la fecha_inicio.
     * Lo guarda en salario_base si aún está vacío.
     */
    public function resolverYGuardarSalario(): ?float
    {
        if ($this->salario_base > 0) {
            return (float) $this->salario_base;
        }

        if (!$this->contrato_id) return null;

        $salario = DB::table('contratos')
            ->where('id', $this->contrato_id)
            ->value('salario');

        if (!is_numeric($salario) || $salario <= 0) return null;

        $this->salario_base = (float) $salario;
        $this->saveQuietly();

        return (float) $salario;
    }

    // ════════════════════════════════════════════════════════════════════════
    // FINANCIERO (ABONOS)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Saldo pendiente de cobro a la EPS/ARL/AFP.
     * saldo = valor_esperado - SUM(entrada_incapacidad) - SUM(pago_cliente)
     *
     * Los préstamos del aliado (tipo='abono') NO descuentan este saldo,
     * son solo informativos para que el aliado sepa cuánto recuperar.
     *
     * Requiere: $this->abonos ya cargado (eager-load) para evitar N+1.
     */
    public function getSaldoPendienteAttribute(): float
    {
        $esperado = (float) ($this->valor_esperado ?? 0);

        if ($this->relationLoaded('abonos')) {
            $pagado = $this->abonos
                ->whereIn('tipo', ['entrada_incapacidad', 'pago_cliente'])
                ->sum('valor');
        } else {
            $pagado = DB::table('abonos_incapacidades')
                ->where('incapacidad_id', $this->id)
                ->whereIn('tipo', ['entrada_incapacidad', 'pago_cliente'])
                ->sum('valor');
        }

        return max(0, $esperado - (float) $pagado);
    }

    /**
     * Total prestado/adelantado por el aliado al cliente.
     * Es informativo: le recuerda al aliado cuánto dinero personal tiene comprometido
     * y cuánto debe recuperar cuando la EPS pague.
     */
    public function getTotalPrestadoAttribute(): float
    {
        if ($this->relationLoaded('abonos')) {
            return (float) $this->abonos->where('tipo', 'abono')->sum('valor');
        }
        return (float) DB::table('abonos_incapacidades')
            ->where('incapacidad_id', $this->id)
            ->where('tipo', 'abono')
            ->sum('valor');
    }

    /**
     * Total recibido de incapacidad (entradas de EPS/ARL/AFP).
     */
    public function getTotalPagoEpsAttribute(): float
    {
        if ($this->relationLoaded('abonos')) {
            return (float) $this->abonos->where('tipo', 'entrada_incapacidad')->sum('valor');
        }
        return (float) DB::table('abonos_incapacidades')
            ->where('incapacidad_id', $this->id)
            ->where('tipo', 'entrada_incapacidad')
            ->sum('valor');
    }

    /**
     * Total pagado al cliente/empresa.
     */
    public function getTotalPagoClienteAttribute(): float
    {
        if ($this->relationLoaded('abonos')) {
            return (float) $this->abonos->where('tipo', 'pago_cliente')->sum('valor');
        }
        return (float) DB::table('abonos_incapacidades')
            ->where('incapacidad_id', $this->id)
            ->where('tipo', 'pago_cliente')
            ->sum('valor');
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS DEL CLIENTE
    // ════════════════════════════════════════════════════════════════════════

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

    // ════════════════════════════════════════════════════════════════════════
    // FAMILIA (PADRE + PRÓRROGAS)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Retorna el total de días de toda la familia (original + prórrogas).
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
     * Cantidad de prórrogas de este grupo.
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
     * Estado del grupo: siempre refleja el estado de la incapacidad más reciente
     * (la última prórroga, o la original si no hay prórrogas).
     * Útil para el encabezado de la familia en la vista agrupada.
     */
    public function getEstadoGrupoAttribute(): string
    {
        $padreId = $this->incapacidad_padre_id ?? $this->id;
        $ultimaEstado = DB::table('incapacidades')
            ->where(function ($q) use ($padreId) {
                $q->where('id', $padreId)
                  ->orWhere('incapacidad_padre_id', $padreId);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('numero_proroga')
            ->value('estado');
        return $ultimaEstado ?? $this->estado;
    }

    /**
     * Entidad del grupo: la de la incapacidad más reciente (puede haber pasado de EPS a AFP).
     */
    public function getEntidadGrupoAttribute(): string
    {
        $padreId = $this->incapacidad_padre_id ?? $this->id;
        $ultimaEntidad = DB::table('incapacidades')
            ->where(function ($q) use ($padreId) {
                $q->where('id', $padreId)
                  ->orWhere('incapacidad_padre_id', $padreId);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('numero_proroga')
            ->value('tipo_entidad');
        return $ultimaEntidad ?? $this->tipo_entidad;
    }

    /**
     * ¿La familia supera los 180 días de EPS?
     * Alerta para indicar que se debe trasladar a AFP.
     */
    public function alertaDias180(): bool
    {
        return $this->tipo_entidad === 'eps' && $this->totalDiasFamilia() >= 180;
    }

    /**
     * Porcentaje de progreso hacia los 180 días de EPS (para barra visual).
     */
    public function progreso180(): int
    {
        return min(100, (int) round(($this->totalDiasFamilia() / 180) * 100));
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEMÁFORO (basado en días desde la última gestión del grupo)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Días desde la última gestión (en cualquier miembro de la familia).
     * Usa eager-load 'latestGestion' si está disponible (evita N+1 en index).
     */
    public function diasDesdeUltimaGestion(): int
    {
        // Buscar la gestión más reciente propia
        if ($this->relationLoaded('latestGestion')) {
            $ultima = $this->latestGestion;
        } else {
            $ultima = $this->gestiones()->first();
        }

        // También buscar gestiones de familia del padre (aplica_a_familia=true)
        if ($this->incapacidad_padre_id) {
            $ultimaFamilia = \App\Models\GestionIncapacidad::where('incapacidad_id', $this->incapacidad_padre_id)
                ->where('aplica_a_familia', true)
                ->orderByDesc('created_at')
                ->first();
            // Tomar la más reciente entre la propia y la de familia
            if ($ultimaFamilia && (!$ultima || $ultimaFamilia->created_at->gt($ultima->created_at))) {
                $ultima = $ultimaFamilia;
            }
        }

        if (!$ultima) {
            return max(0, (int) now()->diffInDays($this->created_at));
        }
        return max(0, (int) now()->diffInDays($ultima->created_at));
    }

    /**
     * Color del semáforo.
     * 🟢 verde    < 7 días sin gestión
     * 🟡 amarillo  7–14 días
     * 🔴 rojo     > 14 días
     * ⚫ gris      pagada / rechazada (ya no requiere gestión)
     */
    public function colorSemaforo(): string
    {
        // Estados finales — ya no requieren gestión.
        // 'negada' incluida: la entidad ya resolvió, no hay más trámite que hacer.
        if (in_array($this->estado, \App\Http\Controllers\Admin\IncapacidadController::ESTADOS_FINALES)) {
            return 'gris';
        }

        $dias = $this->diasDesdeUltimaGestion();

        if ($dias < 7)   return 'verde';
        if ($dias <= 14) return 'amarillo';
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

    // ════════════════════════════════════════════════════════════════════════
    // LINK DE SUBIDA DE DOCUMENTOS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Genera (o retorna el existente) token de subida para el cliente.
     * El token es un UUID único guardado en incapacidades.token_subida.
     */
    public function generarTokenSubida(): string
    {
        if (!$this->token_subida) {
            $this->token_subida = Str::uuid()->toString();
            $this->saveQuietly();
        }
        return $this->token_subida;
    }

    /**
     * URL pública para que el cliente suba sus documentos.
     */
    public function getLinkSubidaAttribute(): string
    {
        return route('incapacidades.subir', ['token' => $this->generarTokenSubida()]);
    }

    /**
     * Texto pre-armado para WhatsApp.
     * Incluye saludo personalizado y el link de subida.
     */
    public function getMensajeWhatsappSubidaAttribute(): string
    {
        $nombre = $this->getNombreClienteAttribute();
        $aliado = DB::table('aliados')->where('id', $this->aliado_id)->value('nombre') ?? 'nuestra empresa';
        $link   = $this->link_subida;

        $texto = "Hola {$nombre}, desde {$aliado} le informamos que necesitamos que suba "
               . "los documentos requeridos para gestionar su incapacidad. "
               . "Por favor ingrese al siguiente link y cargue los archivos: {$link}";

        return 'https://wa.me/' . '?text=' . urlencode($texto);
    }

    // ════════════════════════════════════════════════════════════════════════
    // LABELS Y HELPERS UI
    // ════════════════════════════════════════════════════════════════════════

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
        return self::ESTADOS[$this->estado]['label'] ?? ucfirst($this->estado);
    }

    public function estadoColor(): string
    {
        return self::ESTADOS[$this->estado]['color'] ?? 'secondary';
    }

    public function estadoPagoLabel(): string
    {
        return self::ESTADOS_PAGO[$this->estado_pago]['label'] ?? ucfirst($this->estado_pago);
    }

    public function estadoPagoColor(): string
    {
        return self::ESTADOS_PAGO[$this->estado_pago]['color'] ?? 'secondary';
    }

    /**
     * Número de serie en el grupo: "Original", "Prórroga 1", "Prórroga 2"...
     */
    public function getLabelFamiliaAttribute(): string
    {
        if (!$this->incapacidad_padre_id) return 'Original';
        return 'Prórroga ' . $this->numero_proroga;
    }
}
