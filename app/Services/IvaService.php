<?php

namespace App\Services;

use App\Models\ConfiguracionBrynex;
use App\Models\Contrato;
use Illuminate\Support\Facades\DB;

/**
 * Regla única de IVA para la facturación.
 *
 * ¿Quién causa IVA?
 *   • El cliente independiente, cuando `clientes.iva = 'SI'`.
 *   • Todos los clientes de una empresa marcada con `empresas.iva = 'SI'`,
 *     aunque el cliente no lo tenga marcado individualmente.
 *
 * ¿Sobre qué?
 *   • Planilla    → administración (admon empresa + admon asesor).
 *   • Afiliación  → costo de afiliación (el seguro no es gravado).
 *   • I ACT primer mes (afiliación + planilla en la misma factura)
 *                 → administración + costo de afiliación.
 *
 * Antes de agosto de 2026 la afiliación nunca generaba IVA porque la base
 * siempre era la administración, que en afiliación pura vale 0.
 */
class IvaService
{
    /** cedula => bool, para no repetir la consulta por cada contrato del mismo request */
    private static array $cache = [];

    /** ¿Un valor de la columna `iva` (clientes/empresas) significa "sí"? */
    public static function bandera($valor): bool
    {
        return strtoupper(trim((string) ($valor ?? ''))) === 'SI';
    }

    /**
     * ¿El contrato causa IVA? Mira el cliente y, si pertenece a una empresa,
     * también la empresa.
     */
    public static function aplicaContrato(?Contrato $contrato): bool
    {
        if (! $contrato || ! $contrato->cedula) {
            return false;
        }

        $cedula = $contrato->cedula;
        if (array_key_exists($cedula, self::$cache)) {
            return self::$cache[$cedula];
        }

        $cliente = $contrato->relationLoaded('cliente')
            ? $contrato->cliente
            : $contrato->cliente()->first();

        $aplica = $cliente && self::bandera($cliente->iva);

        $empresaId = $cliente->cod_empresa ?? null;
        if (! $aplica && $empresaId) {
            $aplica = self::bandera(
                DB::table('empresas')->where('id', $empresaId)->value('iva')
            );
        }

        return self::$cache[$cedula] = $aplica;
    }

    /**
     * Mapa cedula => bool para un lote de contratos, en 2 queries.
     * Evita el N+1 de aplicaContrato() dentro de un foreach.
     *
     * @param  iterable<int|string>  $cedulas
     * @return array<int|string, bool>
     */
    public static function mapaPorCedulas(iterable $cedulas): array
    {
        $cedulas = collect($cedulas)->filter()->unique()->values();
        if ($cedulas->isEmpty()) {
            return [];
        }

        $clientes = DB::table('clientes')
            ->whereIn('cedula', $cedulas)
            ->select('cedula', 'iva', 'cod_empresa')
            ->get();

        $empresasIva = [];
        $empresaIds = $clientes->pluck('cod_empresa')->filter()->unique()->values();
        if ($empresaIds->isNotEmpty()) {
            $empresasIva = DB::table('empresas')
                ->whereIn('id', $empresaIds)
                ->pluck('iva', 'id')
                ->map(fn ($v) => self::bandera($v))
                ->toArray();
        }

        $mapa = [];
        foreach ($clientes as $cli) {
            $mapa[$cli->cedula] = self::bandera($cli->iva)
                || (bool) ($empresasIva[$cli->cod_empresa] ?? false);
        }

        self::$cache += $mapa;

        return $mapa;
    }

    /** Solo para pruebas: olvida las banderas ya consultadas. */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }

    /** IVA sobre una base gravada. Redondeo igual que calcularCotizacion(): round. */
    public static function calcular($base, bool $tieneIva = true): int
    {
        if (! $tieneIva || $base <= 0) {
            return 0;
        }

        return (int) round($base * ConfiguracionBrynex::porcentajeIva() / 100);
    }

    /**
     * IVA de una factura completa.
     *
     * @param  int  $admon  administración empresa
     * @param  int  $admonAsesor  administración asesor
     * @param  int  $afiliacion  costo de afiliación cobrado en esta factura
     */
    public static function deFactura(bool $tieneIva, int $admon, int $admonAsesor, int $afiliacion): int
    {
        return self::calcular($admon + $admonAsesor + $afiliacion, $tieneIva);
    }
}
