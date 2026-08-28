<?php

namespace App\Models\Finanzas;

use App\Models\User;
use Carbon\Carbon;

/**
 * App\Models\Finanzas\Prestamo
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre_deudor
 * @property string|null $cedula_deudor
 * @property string|null $telefono_deudor
 * @property float $monto_original
 * @property float $tasa_interes_mensual
 * @property string $fecha_desembolso
 * @property string|null $ultimo_corte
 * @property float $saldo_actual
 * @property string $estado
 * @property int $dias_mora_alerta
 * @property bool $alertas_activas
 * @property \Carbon\Carbon|null $aviso_previo_enviado_para
 * @property \Carbon\Carbon|null $cobro_enviado_para
 * @property string|null $soporte_path
 * @property string|null $descripcion
 * @property string|null $observaciones
 * @property bool $es_cuenta_corriente
 * @property string|null $cuenta_corriente_grupo
 * @property int|null $cc_cliente_id
 * @property bool $sin_interes
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Prestamo extends BaseFinanzasModel
{
    protected $table = 'finanzas_prestamos';

    /**
     * La lista de teléfonos de deudores se cachea para no consultarla en cada
     * sondeo del badge de WhatsApp. Sin esto, un préstamo nuevo tardaría hasta
     * `finanzas.cache_deudores_segundos` en ocultarse del panel de los demás
     * usuarios — y esa demora es justo una fuga de privacidad.
     */
    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\Finanzas\TelefonosDeudores::olvidar());
        static::deleted(fn () => \App\Services\Finanzas\TelefonosDeudores::olvidar());
    }

    protected $fillable = [
        'user_id',
        'nombre_deudor',
        'cedula_deudor',
        'telefono_deudor',
        'monto_original',
        'tasa_interes_mensual',
        'fecha_desembolso',
        'ultimo_corte',
        'dia_cobro',
        'saldo_actual',
        'estado', // activo | pagado | mora | castigado
        'dias_mora_alerta',
        'alertas_activas',
        'aviso_previo_enviado_para',
        'cobro_enviado_para',
        'soporte_path',
        'descripcion',
        'observaciones',
        'es_cuenta_corriente',
        'cuenta_corriente_grupo',
        'cc_cliente_id',
        'sin_interes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'monto_original' => 'float',
        'tasa_interes_mensual' => 'float',
        'saldo_actual' => 'float',
        'dia_cobro' => 'integer',
        'dias_mora_alerta' => 'integer',
        'alertas_activas' => 'boolean',
        'aviso_previo_enviado_para' => 'date',
        'cobro_enviado_para' => 'date',
        'es_cuenta_corriente' => 'boolean',
        'cc_cliente_id' => 'integer',
        'sin_interes' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los movimientos del préstamo
     */
    public function movimientos()
    {
        return $this->hasMany(PrestamoMovimiento::class, 'prestamo_id');
    }

    /**
     * Scope para préstamos activos
     */
    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['activo', 'mora']);
    }

    /**
     * Scope para préstamos en mora
     */
    public function scopeEnMora($query)
    {
        return $query->where('estado', 'mora');
    }

    /**
     * Scope para préstamos de tipo cuenta corriente
     */
    public function scopeCuentaCorriente($query)
    {
        return $query->where('es_cuenta_corriente', true);
    }

    /**
     * Cliente de cuenta corriente al que pertenece este trabajo.
     */
    public function ccCliente()
    {
        return $this->belongsTo(CuentaCorrienteCliente::class, 'cc_cliente_id');
    }

    /**
     * Desglose del trabajo (cámaras, DVR, mano de obra...).
     */
    public function items()
    {
        return $this->hasMany(CuentaCorrienteItem::class, 'prestamo_id')->orderBy('orden')->orderBy('id');
    }

    /**
     * Tasa que realmente se aplica. Un trabajo marcado "sin interés" conserva su
     * tasa guardada — así se puede volver a activar sin tener que recordarla —
     * pero no causa nada mientras el marcador esté puesto.
     */
    public function getTasaEfectivaAttribute(): float
    {
        return $this->sin_interes ? 0.00 : (float) $this->tasa_interes_mensual;
    }

    /**
     * Total del trabajo según su desglose. Si no hay ítems, vale el monto registrado.
     */
    public function getTotalItemsAttribute(): float
    {
        if ($this->items->isEmpty()) {
            return (float) $this->monto_original;
        }

        return round($this->items->sum(fn ($i) => $i->cantidad * $i->valor_unitario), 2);
    }

    /**
     * Lo que costó hacer el trabajo: la suma de los costos de sus líneas. Es el
     * valor que respalda el gasto enlazado por `cc_trabajo_id`.
     */
    public function getCostoItemsAttribute(): float
    {
        return round($this->items->sum(fn ($i) => $i->cantidad * $i->costo_unitario), 2);
    }

    /**
     * Utilidad bruta del trabajo: lo cobrado menos lo gastado. No descuenta los
     * intereses, que son rendimiento de la mora y no parte del negocio.
     */
    public function getUtilidadAttribute(): float
    {
        return round($this->total_items - $this->costo_items, 2);
    }

    /**
     * Gasto de finanzas que materializa el costo de este trabajo.
     */
    public function gastoCosto()
    {
        return $this->hasOne(Gasto::class, 'cc_trabajo_id');
    }

    /**
     * Accessor para obtener los días de mora a partir del último corte o la fecha de desembolso
     */
    public function getDiasMoraAttribute(): int
    {
        if ($this->estado === 'pagado') {
            return 0;
        }

        // Buscar el último movimiento de tipo abono o pago total
        $ultimoAbono = $this->movimientos()
            ->whereIn('tipo', ['abono_capital', 'abono_interes', 'pago_total'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $referencia = $ultimoAbono ? Carbon::parse($ultimoAbono->fecha) : Carbon::parse($this->fecha_desembolso);
        $dias = $referencia->diffInDays(Carbon::now(), false);

        return $dias > 0 ? (int) $dias : 0;
    }

    /**
     * Accessor para obtener el total de intereses que se han acumulado y no pagado
     */
    public function getInteresesAcumuladosAttribute(): float
    {
        return max(0.00, $this->saldo_actual - $this->monto_original);
    }

    /**
     * Fecha del próximo corte: un ciclo después del último corte causado, el mismo
     * día del mes del desembolso. Se delega en el servicio de liquidación para que
     * el recordatorio anuncie exactamente el día en que el cron cobra el interés.
     */
    public function getFechaCorteAttribute(): Carbon
    {
        return app(\App\Services\Finanzas\PrestamoLiquidacionService::class)->proximoCorte($this);
    }

    /**
     * Días que faltan para el próximo corte. Negativo si el corte ya pasó.
     */
    public function getDiasParaCorteAttribute(): int
    {
        return (int) Carbon::now()->startOfDay()->diffInDays($this->fecha_corte->copy()->startOfDay(), false);
    }

    /**
     * Piso en pesos por debajo del cual un interés pendiente se considera residuo
     * de redondeo y no cuenta como deuda vencida.
     */
    public const MINIMO_VENCIDO = 1000;

    /**
     * ¿Hay interés liquidado sin pagar? Es el criterio para cobrar en vez de
     * recordar, y para pintar el semáforo en rojo.
     */
    public function getEstaVencidoAttribute(): bool
    {
        return $this->intereses_acumulados >= self::MINIMO_VENCIDO;
    }

    /**
     * Días transcurridos desde el último corte causado. Es el vencimiento real de
     * lo que está pendiente, a diferencia de `dias_mora`, que cuenta desde el
     * último abono y por eso se dispara aunque el deudor esté al día.
     */
    public function getDiasVencidosAttribute(): int
    {
        if (! $this->esta_vencido) {
            return 0;
        }

        $corte = $this->ultimo_corte
            ? Carbon::parse($this->ultimo_corte)
            : Carbon::parse($this->fecha_desembolso);

        return max(1, (int) $corte->startOfDay()->diffInDays(Carbon::now()->startOfDay(), false));
    }

    /**
     * Interés que se causará en el próximo corte: la tasa mensual sobre el saldo
     * actual. NO es lo mismo que `intereses_acumulados`, que es todo el interés
     * histórico impago.
     */
    public function getInteresCicloAttribute(): float
    {
        // Sobre la tasa efectiva: un trabajo marcado "sin interés" conserva su tasa
        // guardada, y anunciarla en el recordatorio cobraría algo que no se causa.
        if ($this->tasa_efectiva <= 0) {
            return 0.00;
        }

        return round($this->saldo_actual * ($this->tasa_efectiva / 100), 0);
    }

    /**
     * Accessor para obtener el último mensaje de cobro (saliente) enviado por WhatsApp
     */
    public function getUltimoMensajeCobroAttribute(): ?\App\Models\WhatsappMensaje
    {
        if (! $this->telefono_deudor) {
            return null;
        }

        $numeroNormalizado = preg_replace('/[^0-9]/', '', $this->telefono_deudor);
        if (strlen($numeroNormalizado) === 10) {
            $numeroNormalizado = '57'.$numeroNormalizado;
        }

        $aliadoId = null;
        if ($this->user_id) {
            $user = \App\Models\User::find($this->user_id);
            $aliadoId = $user ? $user->aliado_id : null;
        }

        if (! $aliadoId && auth()->check()) {
            $aliadoId = auth()->user()->aliado_id;
        }

        if (! $aliadoId) {
            return null;
        }

        $conversacion = \App\Models\WhatsappConversacion::where('aliado_id', $aliadoId)
            ->where('wa_contact_id', $numeroNormalizado)
            ->first();

        if (! $conversacion) {
            return null;
        }

        return \App\Models\WhatsappMensaje::where('conversacion_id', $conversacion->id)
            ->where('direccion', 'saliente')
            ->where(function ($q) {
                $q->where('contenido', 'like', '%préstamo%')
                    ->orWhere('contenido', 'like', '%prestamo%')
                    ->orWhere('contenido', 'like', '%interes%')
                    ->orWhere('contenido', 'like', '%corte%')
                    ->orWhere('contenido', 'like', '%cobro%');
            })
            ->latest('id')
            ->first();
    }
}
