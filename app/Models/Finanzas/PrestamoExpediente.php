<?php

namespace App\Models\Finanzas;

use Carbon\Carbon;

/**
 * App\Models\Finanzas\PrestamoExpediente
 *
 * Préstamo formal antes (y después) de la firma: guarda los datos legales con
 * los que se llenan el contrato de mutuo, el pagaré, la carta de instrucciones
 * y —cuando hay garantía— el contrato de prenda.
 *
 * Mientras el expediente no se desembolsa no existe préstamo: no suma cartera,
 * no liquida intereses y no dispara recordatorios. Al registrar el desembolso
 * se crea el `Prestamo` y queda enlazado por `prestamo_id`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $prestamista_id
 * @property int|null $prestamo_id
 * @property string $estado
 * @property string $pagare_numero
 * @property string $ciudad
 * @property float $monto
 * @property float $tasa_interes_mensual
 * @property int $plazo_meses
 * @property float $pagare_tope
 */
class PrestamoExpediente extends BaseFinanzasModel
{
    protected $table = 'finanzas_prestamo_expedientes';

    public const ESTADO_PENDIENTE = 'pendiente_firma';

    public const ESTADO_FIRMADO = 'firmado';

    public const ESTADO_DESEMBOLSADO = 'desembolsado';

    public const ESTADO_ANULADO = 'anulado';

    protected $fillable = [
        'user_id', 'prestamista_id', 'prestamo_id', 'estado', 'pagare_numero', 'ciudad',
        'deudor_nombre', 'deudor_cedula', 'deudor_expedida_en', 'deudor_direccion',
        'deudor_ciudad', 'deudor_telefono', 'deudor_correo', 'deudor_ocupacion',
        'tiene_codeudor', 'codeudor_nombre', 'codeudor_cedula', 'codeudor_expedida_en',
        'codeudor_direccion', 'codeudor_ciudad', 'codeudor_telefono', 'codeudor_correo',
        'codeudor_ocupacion',
        'tiene_prenda', 'prenda_placa', 'prenda_clase', 'prenda_marca', 'prenda_linea',
        'prenda_modelo', 'prenda_color', 'prenda_motor', 'prenda_chasis', 'prenda_matricula',
        'prenda_avaluo', 'prenda_propietario', 'prenda_propietario_cedula',
        'monto', 'tasa_interes_mensual', 'plazo_meses', 'fecha_desembolso', 'fecha_vencimiento',
        'dia_cobro', 'dias_mora_alerta', 'pagare_factor', 'pagare_tope',
        'fecha_firma', 'documentos_path', 'descripcion', 'observaciones',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'prestamista_id' => 'integer',
        'prestamo_id' => 'integer',
        'tiene_codeudor' => 'boolean',
        'tiene_prenda' => 'boolean',
        'monto' => 'float',
        'tasa_interes_mensual' => 'float',
        'plazo_meses' => 'integer',
        'prenda_avaluo' => 'float',
        'pagare_tope' => 'float',
        'pagare_factor' => 'integer',
        'dia_cobro' => 'integer',
        'dias_mora_alerta' => 'integer',
        'fecha_desembolso' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_firma' => 'date',
    ];

    public function prestamista()
    {
        return $this->belongsTo(Prestamista::class, 'prestamista_id');
    }

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function scopeEnTramite($query)
    {
        return $query->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_FIRMADO]);
    }

    /**
     * Consecutivo del pagaré del año en curso: PG-2026-0001.
     */
    public static function siguienteNumeroPagare(int $userId): string
    {
        $anio = (int) date('Y');

        $ultimo = static::where('user_id', $userId)
            ->where('pagare_numero', 'like', "PG-{$anio}-%")
            ->orderByDesc('pagare_numero')
            ->value('pagare_numero');

        $consecutivo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('PG-%d-%04d', $anio, $consecutivo);
    }

    public function getEstaFirmadoAttribute(): bool
    {
        return in_array($this->estado, [self::ESTADO_FIRMADO, self::ESTADO_DESEMBOLSADO], true);
    }

    public function getEstadoTextoAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_PENDIENTE => 'Pendiente de firma',
            self::ESTADO_FIRMADO => 'Firmado, sin desembolsar',
            self::ESTADO_DESEMBOLSADO => 'Desembolsado',
            self::ESTADO_ANULADO => 'Anulado',
            default => $this->estado,
        };
    }

    /**
     * Interés mensual que el deudor paga mientras no abone a capital. Es la
     * cifra que el contrato anuncia como cuota mínima mensual.
     */
    public function getInteresMensualAttribute(): float
    {
        return round($this->monto * ($this->tasa_interes_mensual / 100), 0);
    }

    /**
     * Fecha del primer corte: el mismo día calendario del mes siguiente al
     * desembolso, ajustado cuando ese día no existe (31 en un mes de 30).
     */
    public function getPrimerCorteAttribute(): Carbon
    {
        $base = $this->fecha_desembolso instanceof Carbon
            ? $this->fecha_desembolso->copy()
            : Carbon::parse($this->fecha_desembolso);

        $siguiente = $base->copy()->addMonthNoOverflow();

        return $siguiente->day(min($this->dia_cobro ?: $base->day, $siguiente->daysInMonth));
    }
}
