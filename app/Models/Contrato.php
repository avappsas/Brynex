<?php

namespace App\Models;

use App\Services\UpcAdicionalService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends BaseModel
{
    // ID auto-incremental (IDENTITY en SQL Server)
    public $incrementing = true;

    protected $keyType = 'int';

    protected $table = 'contratos';

    protected $fillable = [
        'aliado_id', 'cedula', 'estado',
        'razon_social_id', 'razon_social_bloqueada',
        'plan_id', 'tipo_modalidad_id',
        'eps_id', 'pension_id', 'arl_id', 'n_arl', 'arl_modo', 'arl_nit_cotizante', 'caja_id',
        'cargo', 'fecha_ingreso', 'fecha_retiro', 'fecha_retiro_pendiente', 'retiro_pendiente_cobrar_admon', 'actividad_economica_id',
        'salario', 'ibc', 'porcentaje_caja',
        'administracion', 'admon_asesor', 'costo_afiliacion', 'afiliacion_asesor', 'seguro',
        'asesor_id', 'encargado_id',
        'motivo_afiliacion_id', 'motivo_retiro_id',
        'fecha_arl', 'envio_planilla', 'fecha_probable_pago', 'modo_probable_pago',
        'observacion', 'observacion_afiliacion', 'observacion_llamada', 'np',
        'fecha_created', 'cobra_planilla_primer_mes',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_retiro' => 'date',
        'fecha_retiro_pendiente' => 'date',
        'retiro_pendiente_cobrar_admon' => 'boolean',
        'fecha_arl' => 'date',
        'razon_social_bloqueada' => 'boolean',
        'salario' => 'decimal:2',
        'ibc' => 'decimal:2',
        'administracion' => 'decimal:2',
        'admon_asesor' => 'decimal:2',
        'costo_afiliacion' => 'decimal:2',
        'afiliacion_asesor' => 'decimal:2',
        'seguro' => 'decimal:2',
        'porcentaje_caja' => 'decimal:2',
        'cobra_planilla_primer_mes' => 'boolean',
    ];

    // ── Relaciones ──

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function cliente(): BelongsTo
    {
        $relation = $this->belongsTo(Cliente::class, 'cedula', 'cedula');

        $aliadoId = $this->aliado_id ?? session('aliado_id_activo');
        if ($aliadoId) {
            $relation->where('clientes.aliado_id', $aliadoId);
        }

        return $relation;
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

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'contrato_id');
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

    /**
     * Cargo fijo de $100 cuando el cliente es dependiente E o Ingreso-Retiro
     * y su plan NO incluye Caja de Compensación (sin CCF).
     * Estos $100 se pagan a la caja y van en v_caja de la factura.
     * Solo aplica en planilla (no en afiliación pura).
     */
    const CARGO_SIN_CCF = 100;

    /** IDs de modalidades que generan el cargo sin-CCF (dependiente, no independiente) */
    const IDS_SIN_CCF = [0, 12]; // 0 = Dependiente E, 12 = Ingreso-Retiro

    /** Modalidad UPC: afiliar a alguien fuera del núcleo familiar del cotizante. Solo EPS. */
    const MODALIDAD_UPC = 13;

    public function aplicaCargoSinCcf(): bool
    {
        $esDependienteTarget = in_array((int) $this->tipo_modalidad_id, self::IDS_SIN_CCF);
        $sinCaja = $this->plan && ! $this->plan->incluye_caja;

        return $esDependienteTarget && $sinCaja;
    }

    // ── COTIZADOR ──

    /**
     * Calcula el resumen de aportes según la modalidad del contrato.
     * Retorna un array con: eps, arl, pension, caja, ss, seguro, admon,
     * iva, total, y ibc (base de cotización).
     *
     * Para modalidades de Tiempo Parcial:
     *   - Cada entidad (ARL, AFP, CAJA) tiene sus propios días fijos.
     *   - No se usa $dias global para SS; se usan days del tipo_modalidad.
     *
     * @param  int  $dias  Días cotizados en el mes (1-30). Ignorado en Tiempo Parcial.
     */
    public function calcularCotizacion(int $dias = 30, ?bool $ivaCliente = null): array
    {
        $ibcRaw = (float) ($this->ibc ?? 0);
        $salRaw = (float) ($this->salario ?? 0);
        $alidoId = $this->aliado_id;
        $nivelArl = (int) ($this->n_arl ?? 1);
        $esIndep = $this->esIndependiente();
        $mod = $this->tipoModalidad;
        $esTP = $mod && $mod->esTiempoParcial();

        // Independientes cotizan sobre IBC; dependientes (razón social) sobre salario.
        // Si el campo principal es 0 (legacy), usar el otro como fallback.
        if ($esIndep) {
            $ibc = $ibcRaw > 0 ? $ibcRaw : $salRaw;
        } else {
            $ibc = $salRaw > 0 ? $salRaw : $ibcRaw;
        }

        // Obtener porcentajes
        if ($esIndep) {
            $pctEps = ConfiguracionBrynex::pctSaludIndependiente();
            $pctPen = ConfiguracionBrynex::pctPensionIndependiente();
            $pctCaja = (float) ($this->porcentaje_caja ?? ConfiguracionBrynex::pctCajaIndependienteAlto());
        } else {
            $pctEps = ConfiguracionBrynex::pctSaludDependiente();
            $pctPen = ConfiguracionBrynex::pctPensionDependiente();
            $pctCaja = ConfiguracionBrynex::pctCajaDependiente();
        }

        $pctArl = ArlTarifa::porcentajePara($nivelArl, $alidoId);
        $plan = $this->plan;
        $r = fn ($v) => (int) (ceil($v / 100) * 100);

        if ($esTP) {
            // ── Tiempo Parcial: IBC diferente por entidad, sin EPS ─────────
            // Regla confirmada:
            //   ARL  = SM_completo × tasaArl  (cotiza mes completo)
            //   AFP  = SM × factor_afp × pctPen  (factor = dias_afp/28 aprox)
            //   CAJA = SM × factor_caja × pctCaja (puede diferir de AFP)
            $diasP = $mod->diasPorEntidad(); // ['arl'=>30, 'afp'=>7, 'caja'=>14, ...]
            $factorMap = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];
            $factorAfp = $factorMap[$diasP['afp']] ?? 1.0;
            $factorCaja = $factorMap[$diasP['caja']] ?? 1.0;

            // SM desde ConfiguracionBrynex (fuente correcta del sistema)
            $sm = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);

            $ibcArl = $sm;                       // ARL siempre SM completo
            $ibcAfp = round($sm * $factorAfp);   // AFP según factor dias_afp
            $ibcCaja = round($sm * $factorCaja);  // CAJA según factor dias_caja

            $eps = 0;
            $arl = ($plan && $plan->incluye_arl) ? $r($ibcArl * $pctArl / 100) : 0;
            $pen = ($plan && $plan->incluye_pension) ? $r($ibcAfp * $pctPen / 100) : 0;
            $caja = ($plan && $plan->incluye_caja) ? $r($ibcCaja * $pctCaja / 100) : 0;
        } elseif ((int) $this->tipo_modalidad_id === self::MODALIDAD_UPC) {
            // ── UPC adicional: EPS no es % de IBC, es la tarifa fija por edad/
            //    sexo/zona del beneficiario (Resolución 2764/2025). Siempre es
            //    solo salud: nunca lleva ARL, pensión ni caja. Se prorratea por
            //    días igual que las demás modalidades (confirmado con Brayan:
            //    el mes de arrastre tras el ingreso cobra proporcional, no el
            //    mes completo).
            $cliente = $this->cliente;
            $eps = 0;
            if ($cliente) {
                $upc = UpcAdicionalService::valorParaCliente($cliente);
                $eps = (int) ($upc['valor'] ?? 0);
            }
            $arl = 0;
            $pen = 0;
            $caja = 0;

            if ($dias < 30) {
                $eps = (int) (ceil($eps * $dias / 30 / 100) * 100);
            }
        } else {
            // ── Normal: calcular por mes completo y prorratear si $dias < 30 ─
            // Mes completo: ceil al centena superior para garantizar múltiplos de 100.
            $eps = ($plan && $plan->incluye_eps) ? $r($ibc * $pctEps / 100) : 0;
            $arl = ($plan && $plan->incluye_arl) ? $r($ibc * $pctArl / 100) : 0;
            $pen = ($plan && $plan->incluye_pension) ? $r($ibc * $pctPen / 100) : 0;
            $caja = ($plan && $plan->incluye_caja) ? $r($ibc * $pctCaja / 100) : 0;

            if ($dias < 30) {
                // Mes parcial: se prorratea el IBC y se redondea UNA sola vez,
                // igual que PilaCotizanteCalculator — que es de donde sale el
                // archivo plano y, por tanto, lo que el operador cobra de
                // verdad. Prorratear el aporte ya redondeado y volver a subirlo
                // al centenar (como se hacía antes) le sumaba hasta $100 por
                // subsistema y por persona, y la factura terminaba por encima
                // de la planilla sin que nadie pudiera explicar la diferencia.
                $ibcProp = (int) round($ibc * $dias / 30);

                $eps = ($plan && $plan->incluye_eps) ? $r($ibcProp * $pctEps / 100) : 0;
                $arl = ($plan && $plan->incluye_arl) ? $r($ibcProp * $pctArl / 100) : 0;
                $pen = ($plan && $plan->incluye_pension) ? $r($ibcProp * $pctPen / 100) : 0;
                $caja = ($plan && $plan->incluye_caja) ? $r($ibcProp * $pctCaja / 100) : 0;
            }

            // ── Cargo sin-CCF: dependiente E o Ingreso-Retiro sin caja ─────
            // Se cobra $100 fijos a la caja cuando el plan no incluye CCF.
            // Solo aplica en planilla (dias > 0), no en afiliación pura.
            if ($caja === 0 && $dias > 0 && $this->aplicaCargoSinCcf()) {
                $caja = self::CARGO_SIN_CCF;
            }
        }

        $ss = $eps + $arl + $pen + $caja;

        $seguro = (float) ($this->seguro ?? 0);
        $admon = (float) ($this->administracion ?? 0);

        // IVA solo sobre la administración (la afiliación se grava aparte, en el
        // controlador: este método solo cotiza planilla).
        // Admon, seguro e IVA son cargos fijos mensuales: NO se prorratean
        // $ivaCliente: pre-cargado por el controller (evita N+1).
        // Si viene null, se resuelve con la regla completa (cliente o su empresa).
        $tieneIva = $ivaCliente ?? \App\Services\IvaService::aplicaContrato($this);
        $iva = \App\Services\IvaService::calcular($admon, $tieneIva);

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
        if (! $this->plan) {
            return;
        }

        foreach ($this->plan->tiposRadicado() as $tipo) {
            // Solo si no existe ya
            if (! $this->radicados()->where('tipo', $tipo)->exists()) {
                $this->radicados()->create([
                    'aliado_id' => $this->aliado_id,
                    'tipo' => $tipo,
                    'estado' => Radicado::ESTADO_PENDIENTE,
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
        if (! $cfg) {
            return [];
        }

        return [
            'administracion' => $cfg->administracion,
            'admon_asesor' => $cfg->admon_asesor,
            'costo_afiliacion' => $cfg->costo_afiliacion,
            'seguro' => $cfg->seguro_valor,
            'encargado_id' => $cfg->encargado_default_id,
        ];
    }
}
