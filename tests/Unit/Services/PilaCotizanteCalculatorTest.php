<?php

namespace Tests\Unit\Services;

use App\Services\PilaCotizanteCalculator;
use PHPUnit\Framework\TestCase;

/**
 * roundPila() es la regla de redondeo PILA (múltiplo de 100 hacia arriba).
 * Si esto falla, el archivo PILA generado puede quedar con valores que el
 * operador de planilla rechaza — es la única función pura de la clase,
 * el resto de calcular() depende de catálogos en BD (Caja).
 */
class PilaCotizanteCalculatorTest extends TestCase
{
    /**
     * @dataProvider valoresParaRedondear
     */
    public function test_round_pila_redondea_al_multiplo_de_100_hacia_arriba(float $entrada, int $esperado): void
    {
        $this->assertSame($esperado, PilaCotizanteCalculator::roundPila($entrada));
    }

    public static function valoresParaRedondear(): array
    {
        return [
            'exacto en multiplo de 100'      => [100000.0, 100000],
            'cero'                           => [0.0, 0],
            'un peso sobre el multiplo'      => [100000.01, 100100],
            'justo bajo el siguiente multiplo' => [100099.99, 100100],
            'valor tipico de IBC parcial'    => [123456.78, 123500],
            'valor pequeño'                  => [1.0, 100],
        ];
    }
}
