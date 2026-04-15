<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Contrato extends Model
{
    // ID auto-incremental (IDENTITY en SQL Server)
    public $incrementing = true;
    protected $keyType   = 'int';
    protected $table     = 'contratos';

    protected $fillable = [
        'aliado_id', 'cedula', 'estado',
        'razon_social_id', 'razon_social_bloqueada',
        'plan_id', 'tipo_modalidad_id',
        'eps_id', 'pension_id', 'arl_id', 'n_arl', 'arl_modo', 'arl_nit_cotizante', 'caja_id',
        'cargo', 'fecha_ingreso', 'fecha_retiro', 'actividad_economica_id',
        'salario', 'ibc', 'porcentaje_caja',
        'administracion', 'admon_asesor', 'costo_afiliacion', 'seguro',
        'asesor_id', 'encargado_id',
        'motivo_afiliacion_id', 'motivo_retiro_id',
        'fecha_arl', 'envio_planilla', 'fecha_probable_pago', 'modo_probable_pago',
        'observacion', 'observacion_afiliacion', 'observacion_llamada', 'np',
        'fecha_created', 'cobra_planilla_primer_mes',
    ];

    protected $casts = [
        'fecha_ingreso'           => 'date',
        'fecha_retiro'            => 'date',
        'fecha_arl'               => 'date',
        'razon_social_bloqueada'  => 'boolean',
        'salario'                 => 'decimal:2',
        'ibc'                     => 'decimal:2',
        'administracion'          => 'decimal:2',
        'admon_asesor'            => 'decimal:2',
        'costo_afiliacion'        => 'decimal:2',
        'seguro'                  => 'decimal:2',
        'porcentaje_caja'         => 'decimal:2',
        'cobra_planilla_primer_mes' => 'boolean',
    ];

    // ── Relaciones ──

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cedula', 'cedula');
    }

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanContrato::class, 'plan_id');
    }

    public function tipoModalidad(): BelongsTo
    {
        return $this->belongsTo(TipoModalidad::class, 'tipo_modalidad_id');
    }

    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    public function pension(): BelongsTo
    {
        return $this->belongsTo(Pension::class);
    }

    public function arl(): BelongsTo
    {
        return $this->belongsTo(Arl::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function actividadEconomica(): BelongsTo
    {
        return $this->belongsTo(ActividadEconomica::class, 'actividad_economica_id');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class);
    }

    public function encargado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_id');
    }

    public function motivoAfiliacion(): BelongsTo
    {
        return $this->belongsTo(MotivoAfiliacion::class, 'motivo_afiliacion_id');
    }

    public function motivoRetiro(): BelongsTo
    {
        return $this->belongsTo(MotivoRetiro::class, 'motivo_retiro_id');
    }

    public function radicados(): HasMany
    {
        return $this->hasMany(Radicado::class);
    }

    // ── Helpers de estado ──

    public function estaVigente(): bool
    {
        return $this->estado === 'vigente';
    }

    public function estaRetirado(): bool
    {
        return $this->estado === 'retirado';
    }

    /** ¿Es modalidad independiente? */
    public function esIndependiente(): bool
    {
        return $this->tipoModalidad && $this->tipoModalidad->esIndependiente();
    }

    // ── COTIZADOR ──

    /**
     * Calcula el resumen de aportes según la modalidad del contrato.
     * Retorna un array con: eps, arl, pension, caja, ss, seguro, admon,
     * iva, total, y ibc (base de cotización).
     *
     * @param int $dias Días cotizados en el mes (1-30). Si < 30, proratea la SS.
     */
    public function calcularCotizacion(int $dias = 30): array
    {
        $ibc        = (float) ($this->ibc ?? $this->salario ?? 0);
        $alidoId    = $this->aliado_id;
        $nivelArl   = (int) ($this->n_arl ?? 1);
        $esIndep    = $this->esIndependiente();

        // Obtener porcentajes
        if ($esIndep) {
            $pctEps   = ConfiguracionBrynex::pctSaludIndependiente();
            $pctPen   = ConfiguracionBrynex::pctPensionIndependiente();
            $pctCaja  = (float) ($this->porcentaje_caja ?? ConfiguracionBrynex::pctCajaIndependienteAlto());
        } else {
            $pctEps   = ConfiguracionBrynex::pctSaludDependiente();
            $pctPen   = ConfiguracionBrynex::pctPensionDependiente();
            $pctCaja  = ConfiguracionBrynex::pctCajaDependiente();
        }

        $pctArl = ArlTarifa::porcentajePara($nivelArl, $alidoId);

        // Calcular aportes según plan (base: 30 días)
        $plan   = $this->plan;
        $eps    = ($plan && $plan->incluye_eps)     ? round($ibc * $pctEps / 100)  : 0;
        $arl    = ($plan && $plan->incluye_arl)     ? round($ibc * $pctArl / 100)  : 0;
        $pen    = ($plan && $plan->incluye_pension)  ? round($ibc * $pctPen / 100)  : 0;
        $caja   = ($plan && $plan->incluye_caja)    ? round($ibc * $pctCaja / 100) : 0;

        // Prorratear SS si el trabajador no tiene mes completo
        if ($dias < 30) {
            $r    = fn($v) => (int)(ceil($v * $dias / 30 / 100) * 100);
            $eps  = $r($eps);
            $arl  = $r($arl);
            $pen  = $r($pen);
            $caja = $r($caja);
        }

        $ss = $eps + $arl + $pen + $caja;

        $seguro = (float) ($this->seguro ?? 0);
        $admon  = (float) ($this->administracion ?? 0);

        // IVA solo sobre la administración, solo si el cliente tiene iva=SI
        // Admon, seguro e IVA son cargos fijos mensuales: NO se prorratean
        $tieneIva = false;
        if ($this->cedula) {
            $iva = DB::table('clientes')
                ->where('cedula', $this->cedula)
                ->value('iva');
            $tieneIva = strtoupper(trim($iva ?? '')) === 'SI';
        }
        $pctIva = $tieneIva ? ConfiguracionBrynex::porcentajeIva() : 0;
        $iva    = $tieneIva ? round($admon * $pctIva / 100) : 0;

        $total = $ss + $seguro + $admon + $iva;

        return compact('ibc', 'eps', 'arl', 'pen', 'caja', 'ss', 'seguro', 'admon', 'iva', 'total');
    }

    /**
     * Crea automáticamente los radicados en estado 'pendiente'
     * según los servicios que incluye el plan del contrato.
     * Se llama desde el observer/controller al crear el contrato.
     */
    public function crearRadicadosPendientes(): void
    {
        if (!$this->plan) return;

        foreach ($this->plan->tiposRadicado() as $tipo) {
            // Solo si no existe ya
            if (!$this->radicados()->where('tipo', $tipo)->exists()) {
                $this->radicados()->create([
                    'aliado_id' => $this->aliado_id,
                    'tipo'      => $tipo,
                    'estado'    => Radicado::ESTADO_PENDIENTE,
                ]);
            }
        }
    }

    /**
     * Precarga tarifas desde configuracion_aliado según el aliado y plan.
     * Se invoca al seleccionar el plan en el formulario (vía API).
     */
    public static function tarifasParaAliado(int $alidoId, ?int $planId): array
    {
        $cfg = ConfiguracionAliado::paraAliado($alidoId, $planId);
        if (!$cfg) return [];

        return [
            'administracion'        => $cfg->administracion,
            'admon_asesor'          => $cfg->admon_asesor,
            'costo_afiliacion'      => $cfg->costo_afiliacion,
            'seguro'                => $cfg->seguro_valor,
            'encargado_id'          => $cfg->encargado_default_id,
        ];
    }
}
