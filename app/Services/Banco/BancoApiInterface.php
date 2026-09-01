<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use Carbon\CarbonInterface;

/**
 * Contrato que debe cumplir cualquier adaptador de API bancaria.
 *
 * Mientras el banco no active el producto, el único adaptador real es el
 * falso. Cuando lleguen las credenciales de Bancolombia se agrega otra clase
 * que cumpla esto y se cambia una línea en config/banco.php: nada más del
 * sistema se entera.
 */
interface BancoApiInterface
{
    /** Nombre corto que queda guardado en banco_movimientos.proveedor */
    public function nombre(): string;

    /**
     * Movimientos de la cuenta entre dos fechas, ambas inclusive.
     *
     * @return MovimientoBanco[] En el orden en que los entrega el banco.
     */
    public function movimientos(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array;

    /**
     * Saldo actual de la cuenta.
     *
     * @return array{disponible: float, total: float, fecha: CarbonInterface}
     */
    public function saldo(BancoCuenta $cuenta): array;
}
