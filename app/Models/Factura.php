<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Factura extends Model
{
    use SoftDeletes;

    protected $table    = 'facturas';
    protected $fillable = [
        'aliado_id','numero_factura','tipo','cedula','contrato_id',
        'empresa_id',
        'mes','anio','fecha_pago',
        'estado','es_prestamo','forma_pago',
        'valor_consignado','valor_efectivo','valor_prestamo',
        'dias_cotizados',
        'v_eps','v_arl','v_afp','v_caja','total_ss',
        'admon','admin_asesor','seguro','afiliacion','mensajeria','otros','iva','total',
        'saldo_a_favor','saldo_pendiente','saldo_proximo',
        'c_asesor','c_utilidad','retiro',
        'dist_admon','dist_asesor','dist_retiro','dist_utilidad',
        'np','n_plano','razon_social_id',
        'usuario_id','observacion','obs_factura',
        'motivo_anulacion','anulado_por',
        // ── Otro ingreso / trámite ─────────────────────────────
        'descripcion_tramite','admon_asesor_oi',
    ];

    protected $casts = [
        'fecha_pago'           => 'date',
        'es_prestamo'          => 'boolean',
    ];

    // ── Tipos ──────────────────────────────────────────────────
    const TIPO_PLANILLA      = 'planilla';
    const TIPO_AFILIACION    = 'afiliacion';
    const TIPO_OTRO_INGRESO  = 'otro_ingreso';

    // ── Estados ────────────────────────────────────────────────
    const ESTADO_PRE       = 'pre_factura';
    const ESTADO_ABONO     = 'abono';
    const ESTADO_PAGADA    = 'pagada';
    const ESTADO_PRESTAMO  = 'prestamo';
    // nota: 'anulada' se maneja con SoftDeletes (deleted_at), no como estado

    // ── Relaciones ───────────────────────────────────────────────────
    public function consignaciones(){ return $this->hasMany(Consignacion::class); }
    public function contrato()   { return $this->belongsTo(Contrato::class); }
    public function abonos()     { return $this->hasMany(Abono::class); }
    public function plano()      { return $this->hasOne(Plano::class); }
    public function usuario()    { return $this->belongsTo(User::class, 'usuario_id'); }
    public function razonSocial(){ return $this->belongsTo(RazonSocial::class, 'razon_social_id'); }
    /** Empresa que procesó el pago en lote (null = pago individual) */
    public function empresa()    { return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id'); }


    // ── Scopes ───────────────────────────────────────────────────────
    public function scopePeriodo(Builder $q, int $mes, int $anio): Builder
    {
        return $q->where('mes', $mes)->where('anio', $anio);
    }

    public function scopePorEmpresa(Builder $q, int $empresaId): Builder
    {
        $cedulas = DB::table('clientes')->where('cod_empresa', $empresaId)->pluck('cedula');
        return $q->whereIn('cedula', $cedulas);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->whereIn('estado', [self::ESTADO_PRE, self::ESTADO_ABONO, self::ESTADO_PRESTAMO]);
    }

    public function scopeAliado(Builder $q, int $aliadoId): Builder
    {
        return $q->where('aliado_id', $aliadoId);
    }

    // ── Helpers ──────────────────────────────────────────────────────
    /** Total ya abonado a esta factura */
    public function getTotalAbonadoAttribute(): int
    {
        return (int) $this->abonos()->sum('valor');
    }

    /** Saldo restante para completar el pago */
    public function getSaldoRestanteAttribute(): int
    {
        return max(0, (int)$this->total - $this->total_abonado);
    }

    /** ¿Está completamente pagada? */
    public function estaCompletamentePagada(): bool
    {
        return $this->total_abonado >= (int)$this->total;
    }

    /** Genera el siguiente número de factura para un aliado */
    public static function siguienteNumero(int $aliadoId): int
    {
        $seq = DB::table('factura_secuencias')
            ->where('aliado_id', $aliadoId)
            ->lockForUpdate()
            ->first();

        if ($seq) {
            $nuevo = $seq->ultimo_numero + 1;
            DB::table('factura_secuencias')
                ->where('aliado_id', $aliadoId)
                ->update(['ultimo_numero' => $nuevo]);
        } else {
            $nuevo = 1;
            DB::table('factura_secuencias')
                ->insert(['aliado_id' => $aliadoId, 'ultimo_numero' => 1]);
        }

        return $nuevo;
    }

    /** Saldo acumulativo del cliente (SUMA de todos los saldo_proximo anteriores).
     *  Formula: Abril +350k + Mayo -350k = 0 para Junio.
     *  Excluye: soft-deleted (anuladas), facturas sin saldo_proximo, pre_facturas.
     */
    public static function saldoClienteMesPrevio(int $aliadoId, int $cedula, int $mes, int $anio): array
    {
        $suma = (int) static::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->where(fn($q) => $q->where('anio', '<', $anio)
                ->orWhere(fn($q2) => $q2->where('anio', $anio)->where('mes', '<', $mes)))
            ->whereNotNull('saldo_proximo')
            ->whereIn('estado', ['pagada', 'prestamo', 'abono'])
            ->sum('saldo_proximo');

        return [
            'a_favor'   => $suma > 0 ? $suma : 0,
            'pendiente' => $suma < 0 ? abs($suma) : 0,
        ];
    }

    /** Etiqueta de estado para UI */
    public function getEtiquetaEstadoAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PRE      => 'Pre-factura',
            self::ESTADO_ABONO    => 'Abono',
            self::ESTADO_PAGADA   => 'Pagada',
            self::ESTADO_PRESTAMO => 'Préstamo',
            default               => ucfirst($this->estado),
        };
    }

    public function getBadgeColorAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PAGADA   => '#16a34a',
            self::ESTADO_ABONO    => '#d97706',
            self::ESTADO_PRESTAMO => '#7c3aed',
            default               => '#64748b',
        };
    }
}
