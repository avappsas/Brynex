<?php

namespace App\Services\Finanzas;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CriptoApiService
{
    /** Caché del precio vigente (15 minutos). */
    private const CACHE_KEY = 'finanzas_precio_usdt';

    /** Último precio bueno conocido, para cuando ninguna fuente responde. */
    private const CACHE_KEY_ULTIMO = 'finanzas_precio_usdt_ultimo';

    /**
     * Obtiene el precio actual del USDT en pesos colombianos (COP) y dólares (USD).
     *
     * El COP sale de Binance (par USDTCOP, el mismo que muestra TradingView) y, si
     * Binance no responde, de la TRM oficial de la Superfinanciera publicada en
     * datos.gov.co. CoinGecko ya NO sirve para el COP: sacó esa moneda de su API
     * pública (`supported_vs_currencies` no la incluye y `simple/price` responde 200
     * sin la clave `cop`), así que solo se usa para el precio en USD.
     *
     * @return array{precio_cop: float, precio_usd: float, fuente: string, actualizado: string, fallback: bool}
     */
    public function getPrecioUsdt(): array
    {
        return Cache::remember(self::CACHE_KEY, 900, function () { // 900 segundos = 15 minutos
            $cop = $this->precioCopBinance() ?? $this->precioCopTrmOficial();

            if ($cop !== null) {
                $datos = [
                    'precio_cop' => $cop['valor'],
                    'precio_usd' => $this->precioUsdCoingecko(),
                    'fuente' => $cop['fuente'],
                    'actualizado' => now()->toDateTimeString(),
                    'fallback' => false,
                ];

                // Se guarda aparte para poder reusarlo si algún día ninguna fuente responde.
                Cache::forever(self::CACHE_KEY_ULTIMO, $datos);

                return $datos;
            }

            Log::warning('No se pudo obtener el precio del USDT en COP de ninguna fuente.');

            // Sin fuentes: se reusa el último precio bueno, marcado como desactualizado.
            $ultimo = Cache::get(self::CACHE_KEY_ULTIMO);

            if (is_array($ultimo) && ! empty($ultimo['precio_cop'])) {
                return array_merge($ultimo, ['fallback' => true]);
            }

            return [
                'precio_cop' => 0.00,
                'precio_usd' => 1.00,
                'fuente' => 'sin datos',
                'actualizado' => now()->toDateTimeString(),
                'fallback' => true,
            ];
        });
    }

    /**
     * Borra la caché para forzar una consulta nueva.
     */
    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Precio del USDT en COP según el par USDTCOP de Binance (precio de mercado en vivo).
     *
     * @return array{valor: float, fuente: string}|null
     */
    private function precioCopBinance(): ?array
    {
        try {
            $response = Http::withOptions([
                'connect_timeout' => 1.5,
                'timeout' => 2.5,
            ])->get('https://api.binance.com/api/v3/ticker/price', [
                'symbol' => 'USDTCOP',
            ]);

            if ($response->successful()) {
                $valor = (float) ($response->json()['price'] ?? 0);

                if ($this->esPrecioRazonable($valor)) {
                    return ['valor' => $valor, 'fuente' => 'Binance USDTCOP'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error consultando precio USDTCOP en Binance: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * TRM oficial de la Superfinanciera (dataset 32sa-8pi3 de datos.gov.co).
     *
     * @return array{valor: float, fuente: string}|null
     */
    private function precioCopTrmOficial(): ?array
    {
        try {
            $response = Http::withOptions([
                'connect_timeout' => 2.0,
                'timeout' => 4.0,
            ])->get('https://www.datos.gov.co/resource/32sa-8pi3.json', [
                '$limit' => 1,
                '$order' => 'vigenciadesde DESC',
            ]);

            if ($response->successful()) {
                $valor = (float) ($response->json()[0]['valor'] ?? 0);

                if ($this->esPrecioRazonable($valor)) {
                    return ['valor' => $valor, 'fuente' => 'TRM oficial'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error consultando la TRM oficial en datos.gov.co: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Precio del USDT en dólares. Ronda 1.00, así que si CoinGecko falla se asume la paridad.
     */
    private function precioUsdCoingecko(): float
    {
        try {
            $response = Http::withOptions([
                'connect_timeout' => 1.5,
                'timeout' => 2.0,
            ])->get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => 'tether',
                'vs_currencies' => 'usd',
            ]);

            if ($response->successful()) {
                $valor = (float) ($response->json()['tether']['usd'] ?? 0);

                if ($valor > 0.5 && $valor < 1.5) {
                    return $valor;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error consultando precio USDT/USD en CoinGecko: ' . $e->getMessage());
        }

        return 1.00;
    }

    /**
     * Descarta respuestas vacías o absurdas antes de darlas por buenas.
     */
    private function esPrecioRazonable(float $valor): bool
    {
        return $valor > 500 && $valor < 20000;
    }
}
