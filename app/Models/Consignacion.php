<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Consignacion extends BaseModel
{
    protected $table    = 'consignaciones';
    protected $fillable = [
        'aliado_id', 'factura_id', 'anticipo_id', 'banco_cuenta_id',
        'fecha', 'valor', 'tipo', 'referencia', 'imagen_path',
        'confirmado', 'observacion', 'usuario_id',
        // Cuadre diario - traslados internos
        'cuadre_id', 'gasto_id',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'confirmado' => 'boolean',
        'valor'      => 'integer',
    ];

    // ── Tipos de consignación ────────────────────────────────────────
    /** Pago de cliente por factura (seguridad social, afiliación, etc.) */
    const TIPO_CLIENTE          = 'cliente';
    /** Traslado de efectivo del cuadre a una cuenta bancaria */
    const TIPO_TRASLADO_EFECTIVO = 'traslado_efectivo';
    /** Entrada por transferencia banco→banco */
    const TIPO_BANCO_RECIBIDO   = 'banco_recibido';
    /** Pago anticipado de cliente (antes de la factura) */
    const TIPO_ANTICIPO         = 'anticipo';

    // ── Relaciones ───────────────────────────────────────────────────
    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    public function bancoCuenta()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Anticipo asociado (cuando tipo = 'anticipo') */
    public function anticipo()
    {
        return $this->belongsTo(Anticipo::class, 'anticipo_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────
    public function scopeSinConfirmar($query)
    {
        return $query->where('confirmado', false);
    }

    public function scopeConfirmadas($query)
    {
        return $query->where('confirmado', true);
    }

    public function scopeClientes($query)
    {
        return $query->where('tipo', self::TIPO_CLIENTE);
    }

    // ── Helper: saldo actual de una cuenta bancaria ──────────────────
    /**
     * Calcula el saldo real de una cuenta bancaria combinando:
     *  + consignaciones confirmadas (clientes + traslados efectivo + banco recibido)
     *  – gastos cuyo banco_origen_id = $bancoCuentaId (pagos y transferencias salientes)
     */
    public static function saldoBanco(int $aliadoId, int $bancoCuentaId): int
    {
        // ── Estrategia: si existe un saldo_inicial en saldos_banco, úsalo
        // como punto de partida y solo cuenta movimientos POSTERIORES a esa fecha.
        // Si no existe, recalcula desde el principio (comportamiento original).
        $ledger = DB::table('saldos_banco')
            ->where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->where('tipo', 'saldo_inicial')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first(['fecha', 'saldo_acumulado']);

        if ($ledger) {
            $baseDate  = $ledger->fecha;
            $baseSaldo = (int) $ledger->saldo_acumulado; // normalmente 0

            $entradas = (int) static::where('aliado_id', $aliadoId)
                ->where('banco_cuenta_id', $bancoCuentaId)
                ->where('fecha', '>', $baseDate)
                ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
                ->value('total');

            $salidas = (int) DB::table('gastos')
                ->where('aliado_id', $aliadoId)
                ->where('banco_origen_id', $bancoCuentaId)
                ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
                ->where('tipo', '!=', 'ajuste_apertura')
                ->where('fecha', '>', $baseDate)
                ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
                ->value('total');

            return $baseSaldo + $entradas - $salidas;
        }

        // ── Fallback: recalcular todo el historial ────────────────────────
        // Usa selectRaw + alias para compatibilidad con SQL Server (no value(DB::raw()))
        $entradas = (int) static::where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
            ->value('total');

        $salidas = (int) DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('banco_origen_id', $bancoCuentaId)
            ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
            ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
            ->value('total');

        return $entradas - $salidas;
    }

    /**
     * Retorna un array con [banco_cuenta_id => saldo] para todas las cuentas activas.
     */
    public static function saldosTodos(int $aliadoId): array
    {
        $bancos = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();
        $result = [];
        foreach ($bancos as $bc) {
            $result[$bc->id] = static::saldoBanco($aliadoId, $bc->id);
        }
        return $result;
    }

    /**
     * Calcula el saldo de un banco al CIERRE de un mes/año específico.
     * Usado en el informe financiero cuando el filtro es un mes pasado.
     *
     * Lógica:
     *  - Si existe saldo_inicial anterior o igual al último día del mes → parte desde él
     *  - Suma entradas y resta salidas hasta el último día del mes (inclusive)
     */
    public static function saldoBancoAlFin(int $aliadoId, int $bancoCuentaId, int $mes, int $anio): int
    {
        // Último día del mes filtrado
        $fechaFin = \Carbon\Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString();

        // Buscar saldo_inicial que aplique (anterior o igual al cierre del mes)
        $ledger = DB::table('saldos_banco')
            ->where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->where('tipo', 'saldo_inicial')
            ->where('fecha', '<=', $fechaFin)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first(['fecha', 'saldo_acumulado']);

        if ($ledger) {
            $baseDate  = $ledger->fecha;
            $baseSaldo = (int) $ledger->saldo_acumulado;

            $entradas = (int) static::where('aliado_id', $aliadoId)
                ->where('banco_cuenta_id', $bancoCuentaId)
                ->where('fecha', '>', $baseDate)
                ->where('fecha', '<=', $fechaFin)
                ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
                ->value('total');

            $salidas = (int) DB::table('gastos')
                ->where('aliado_id', $aliadoId)
                ->where('banco_origen_id', $bancoCuentaId)
                ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
                ->where('tipo', '!=', 'ajuste_apertura')
                ->where('fecha', '>', $baseDate)
                ->where('fecha', '<=', $fechaFin)
                ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
                ->value('total');

            return $baseSaldo + $entradas - $salidas;
        }

        // Fallback: sumar todo hasta el cierre del mes
        $entradas = (int) static::where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->where('fecha', '<=', $fechaFin)
            ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
            ->value('total');

        $salidas = (int) DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('banco_origen_id', $bancoCuentaId)
            ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
            ->where('fecha', '<=', $fechaFin)
            ->selectRaw('ISNULL(SUM(CAST(valor AS BIGINT)), 0) AS total')
            ->value('total');

        return $entradas - $salidas;
    }
}
