<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Anticipo extends BaseModel
{
    protected $table    = 'anticipos';
    protected $fillable = [
        'aliado_id', 'cedula', 'contrato_id', 'empresa_id',
        'fecha_pago', 'valor', 'valor_aplicado',
        'forma_pago', 'banco_cuenta_id', 'referencia', 'observacion',
        'estado', 'factura_id', 'usuario_id',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    // ── Estados ────────────────────────────────────────────────────────
    const ESTADO_DISPONIBLE = 'disponible';
    const ESTADO_PARCIAL    = 'parcial';
    const ESTADO_APLICADO   = 'aplicado';
    const ESTADO_DEVUELTO   = 'devuelto';

    // ── Formas de pago ─────────────────────────────────────────────────
    const FORMAS_PAGO = [
        'efectivo'      => 'Efectivo',
        'nequi'         => 'Nequi',
        'consignacion'  => 'Consignación',
        'transferencia' => 'Transferencia',
    ];

    // ── Relaciones ────────────────────────────────────────────────────
    public function contrato()    { return $this->belongsTo(Contrato::class); }
    public function empresa()     { return $this->belongsTo(Empresa::class); }
    public function factura()     { return $this->belongsTo(Factura::class); }
    public function usuario()     { return $this->belongsTo(User::class, 'usuario_id'); }
    public function bancoCuenta() { return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id'); }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Anticipos con saldo disponible (no totalmente aplicados) */
    public function scopeConSaldo($q)
    {
        return $q->whereIn('estado', [self::ESTADO_DISPONIBLE, self::ESTADO_PARCIAL]);
    }

    /** Anticipos de un aliado */
    public function scopeAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    /** Anticipos de un contrato específico */
    public function scopePorContrato($q, int $contratoId)
    {
        return $q->where('contrato_id', $contratoId);
    }

    /** Anticipos de una empresa específica */
    public function scopePorEmpresa($q, int $empresaId)
    {
        return $q->where('empresa_id', $empresaId);
    }

    // ── Helpers / Atributos calculados ────────────────────────────────

    /** Cuánto dinero queda disponible de este anticipo */
    public function getValorDisponibleAttribute(): int
    {
        return max(0, (int)$this->valor - (int)$this->valor_aplicado);
    }

    /** ¿Tiene aún saldo disponible? */
    public function tieneSaldo(): bool
    {
        return $this->valor_disponible > 0;
    }

    /**
     * Descripción legible del anticipo para el recibo/PDF.
     * Ejemplo: "Anticipo (Nequi, 15-abr-2026, ref: 3421xxx)"
     */
    public function getDescripcionPagoAttribute(): string
    {
        $fecha = $this->fecha_pago->format('d-M-Y');
        $forma = self::FORMAS_PAGO[$this->forma_pago] ?? ucfirst($this->forma_pago);
        $ref   = $this->referencia ? ", ref: {$this->referencia}" : '';
        return "Anticipo ({$forma}, {$fecha}{$ref})";
    }

    /** Etiqueta del estado para UI */
    public function getEtiquetaEstadoAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_DISPONIBLE => 'Disponible',
            self::ESTADO_PARCIAL    => 'Parcial',
            self::ESTADO_APLICADO   => 'Aplicado',
            self::ESTADO_DEVUELTO   => 'Devuelto',
            default                 => ucfirst($this->estado),
        };
    }

    /** Color del badge de estado */
    public function getBadgeColorAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_DISPONIBLE => '#16a34a', // verde
            self::ESTADO_PARCIAL    => '#d97706', // ámbar
            self::ESTADO_APLICADO   => '#64748b', // gris
            self::ESTADO_DEVUELTO   => '#dc2626', // rojo
            default                 => '#64748b',
        };
    }

    // ── Métodos estáticos de consulta ─────────────────────────────────

    /**
     * Suma total disponible para un contrato o empresa.
     * Usa SELECT SUM(valor - valor_aplicado) para evitar cargar todos los registros.
     */
    public static function saldoDisponible(
        int $aliadoId,
        ?int $contratoId = null,
        ?int $empresaId  = null
    ): int {
        $q = static::aliado($aliadoId)->conSaldo();
        if ($contratoId) $q->porContrato($contratoId);
        if ($empresaId)  $q->porEmpresa($empresaId);
        return (int) $q->sum(DB::raw('valor - valor_aplicado'));
    }

    /**
     * Anticipos disponibles para un contrato (lista completa para el modal facturar).
     */
    public static function disponiblesParaContrato(int $aliadoId, int $contratoId): \Illuminate\Support\Collection
    {
        return static::aliado($aliadoId)
            ->porContrato($contratoId)
            ->conSaldo()
            ->orderBy('fecha_pago')
            ->get();
    }

    /**
     * Anticipos disponibles para una empresa (lista completa para el modal facturar).
     */
    public static function disponiblesParaEmpresa(int $aliadoId, int $empresaId): \Illuminate\Support\Collection
    {
        return static::aliado($aliadoId)
            ->porEmpresa($empresaId)
            ->conSaldo()
            ->orderBy('fecha_pago')
            ->get();
    }

    /**
     * Todos los anticipos aplicados a una factura (para el recibo/PDF).
     */
    public static function paraFactura(int $facturaId): \Illuminate\Support\Collection
    {
        return static::where('factura_id', $facturaId)
            ->orderBy('fecha_pago')
            ->get();
    }

    /**
     * Aplica este anticipo (parcial o total) a una factura.
     * Devuelve cuánto se aplicó realmente.
     *
     * @param  int $facturaId
     * @param  int $montoAplicar  Monto a aplicar (máx = valor_disponible)
     * @return int Monto efectivamente aplicado
     */
    public function aplicarAFactura(int $facturaId, int $montoAplicar): int
    {
        $aplicar       = min($this->valor_disponible, max(0, $montoAplicar));
        if ($aplicar <= 0) return 0;

        $nuevoAplicado = (int)$this->valor_aplicado + $aplicar;
        $nuevoEstado   = $nuevoAplicado >= (int)$this->valor
            ? self::ESTADO_APLICADO
            : self::ESTADO_PARCIAL;

        $this->update([
            'valor_aplicado' => $nuevoAplicado,
            'estado'         => $nuevoEstado,
            'factura_id'     => $facturaId,
        ]);

        return $aplicar;
    }
}
