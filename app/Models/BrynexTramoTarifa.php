<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BrynexTramoTarifa extends BaseModel
{
    protected $table = 'brynex_tramos_tarifa';

    protected $fillable = [
        'modulo_id', 'aliado_id',
        'desde_cant', 'hasta_cant',
        'tarifa_unidad', 'tarifa_minima',
        'vigente_desde', 'vigente_hasta',
    ];

    protected $casts = [
        'desde_cant'    => 'integer',
        'hasta_cant'    => 'integer',
        'tarifa_unidad' => 'decimal:2',
        'tarifa_minima' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(BrynexModulo::class, 'modulo_id');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    // ── Lógica de negocio central ────────────────────────────────────────────

    /**
     * Calcula el cobro para un módulo dado una cantidad de unidades.
     *
     * Algoritmo:
     *  1. Busca tramos personalizados del aliado para el módulo en la fecha de referencia.
     *     Si no hay → usa los tramos globales (aliado_id IS NULL).
     *  2. Encuentra el tramo donde desde_cant <= cantidad <= hasta_cant.
     *  3. Calcula: si el tramo no tiene tarifa_unidad (solo mínimo) → cobrar mínimo.
     *     Si tiene tarifa_unidad → cobrar MAX(mínimo, cantidad × tarifa_unidad).
     *
     * @param int      $moduloId   ID del módulo (ej: 1 = administracion)
     * @param int      $cantidad   Unidades consumidas en el mes
     * @param int|null $alidoId    ID del aliado (para buscar tramos personalizados)
     * @param string   $fecha      Fecha de referencia para vigencia (Y-m-d)
     *
     * @return array{
     *   cant_unidades: int,
     *   tarifa_unidad: float|null,
     *   tarifa_minima: float|null,
     *   subtotal: float,
     *   tramo_aplicado: string
     * }
     */
    public static function calcularCobro(
        int $moduloId,
        int $cantidad,
        ?int $alidoId = null,
        string $fecha = ''
    ): array {
        $fecha = $fecha ?: now()->toDateString();

        // 1. Buscar tramos: primero personalizados del aliado, luego globales
        $tramos = self::where('modulo_id', $moduloId)
            ->where(function ($q) use ($alidoId) {
                if ($alidoId) {
                    $q->where('aliado_id', $alidoId);
                } else {
                    $q->whereNull('aliado_id');
                }
            })
            ->where('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')
                  ->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderBy('desde_cant')
            ->get();

        // Si no hay tramos personalizados para el aliado, usar globales
        if ($tramos->isEmpty() && $alidoId) {
            $tramos = self::where('modulo_id', $moduloId)
                ->whereNull('aliado_id')
                ->where('vigente_desde', '<=', $fecha)
                ->where(function ($q) use ($fecha) {
                    $q->whereNull('vigente_hasta')
                      ->orWhere('vigente_hasta', '>=', $fecha);
                })
                ->orderBy('desde_cant')
                ->get();
        }

        // 2. Encontrar el tramo aplicable
        $tramo = $tramos->first(function ($t) use ($cantidad) {
            $desdeOk = $cantidad >= $t->desde_cant;
            $hastaOk = is_null($t->hasta_cant) || $cantidad <= $t->hasta_cant;
            return $desdeOk && $hastaOk;
        });

        if (!$tramo) {
            return [
                'cant_unidades'   => $cantidad,
                'tarifa_unidad'   => null,
                'tarifa_minima'   => null,
                'subtotal'        => 0.0,
                'tramo_aplicado'  => 'Sin tramo configurado',
            ];
        }

        // 3. Calcular el subtotal
        $tarifaUnidad = $tramo->tarifa_unidad ? (float) $tramo->tarifa_unidad : null;
        $tarifaMinima = $tramo->tarifa_minima ? (float) $tramo->tarifa_minima : 0.0;

        if ($tarifaUnidad !== null) {
            $calculado = $cantidad * $tarifaUnidad;
            $subtotal  = max($tarifaMinima, $calculado);
        } else {
            // Solo mínimo, sin tarifa por unidad
            $subtotal = $tarifaMinima;
        }

        $tramoDesc = $tramo->hasta_cant
            ? "{$tramo->desde_cant} – {$tramo->hasta_cant} unidades"
            : "{$tramo->desde_cant}+ unidades";

        return [
            'cant_unidades'   => $cantidad,
            'tarifa_unidad'   => $tarifaUnidad,
            'tarifa_minima'   => $tarifaMinima,
            'subtotal'        => $subtotal,
            'tramo_aplicado'  => $tramoDesc,
        ];
    }
}
