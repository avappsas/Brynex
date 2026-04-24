<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Consignacion extends BaseModel
{
    protected $table    = 'consignaciones';
    protected $fillable = [
        'aliado_id', 'factura_id', 'banco_cuenta_id',
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
        // Entradas: TODAS las consignaciones (confirmado = solo verificación de recibo, no afecta saldo)
        $entradas = (int) static::where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->sum('valor');

        // Salidas: gastos bancarios registrados en el cuadre
        $salidas = (int) DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('banco_origen_id', $bancoCuentaId)
            ->whereIn('forma_pago', ['transferencia_bancaria', 'banco_banco'])
            ->sum('valor');

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
}
