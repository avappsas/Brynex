<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un pedazo de saldo a favor que el cliente ya no tiene.
 *
 * Nace cuando el aliado decide que ese crédito no corresponde —casi siempre
 * porque salió de un "Otros" que se escribió y nunca se cobró— y en vez de
 * reescribir la factura vieja se anota aquí. El saldo del cliente se calcula
 * restando estos ajustes, así que la factura original no se toca y queda el
 * rastro de quién lo quitó, cuándo y por qué.
 *
 * Anular el ajuste (soft delete) le devuelve el saldo al cliente.
 */
class SaldoAjuste extends BaseModel
{
    use SoftDeletes;

    protected $table = 'saldo_ajustes';

    protected $fillable = [
        'aliado_id', 'cedula', 'valor', 'motivo', 'usuario_id', 'detalle',
    ];

    protected $casts = [
        'valor' => 'integer',
        'detalle' => 'array',
    ];

    /** Motivo por defecto de la pantalla de saldos. */
    public const MOTIVO_ALIADO = 'Utilizado por el aliado';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Cuánto saldo se le ha quitado a un cliente. Lo consultan todos los
     * cálculos de saldo a favor, así que un ajuste vale igual en el modal de
     * facturación, en la vista de empresa y en el informe.
     */
    public static function totalDe(int $aliadoId, $cedula): int
    {
        return (int) static::where('aliado_id', $aliadoId)
            ->where('cedula', (string) $cedula)
            ->sum('valor');
    }

    /**
     * Lo ajustado a todos los clientes de una empresa. La empresa acumula el
     * saldo de sus trabajadores, así que sus ajustes también se suman.
     */
    public static function totalDeEmpresa(int $aliadoId, int $empresaId): int
    {
        $cedulas = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('cedula');

        if ($cedulas->isEmpty()) {
            return 0;
        }

        return (int) static::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulas->map(fn ($c) => (string) $c))
            ->sum('valor');
    }

    /**
     * Lo mismo para un grupo de cédulas, en una sola consulta.
     *
     * @return array<string,int> cedula => total ajustado
     */
    public static function mapaPorCedulas(int $aliadoId, iterable $cedulas): array
    {
        $cedulas = collect($cedulas)->filter()->map(fn ($c) => (string) $c)->unique();

        if ($cedulas->isEmpty()) {
            return [];
        }

        return static::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulas)
            ->selectRaw('cedula, SUM(valor) AS total')
            ->groupBy('cedula')
            ->pluck('total', 'cedula')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
