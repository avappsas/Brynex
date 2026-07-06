<?php

namespace App\Services\Finanzas;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CriptoApiService
{
    /**
     * Obtiene el precio actual del USDT en pesos colombianos (COP) y dólares (USD).
     * Utiliza la API pública de CoinGecko con una caché de 15 minutos.
     *
     * @return array
     */
    public function getPrecioUsdt(): array
    {
        return Cache::remember('finanzas_precio_usdt', 900, function () { // 900 segundos = 15 minutos
            try {
                // Hacer petición a CoinGecko
                $response = Http::withOptions([
                    'connect_timeout' => 1.5, // Tiempo de conexión máximo
                    'timeout' => 2.0,         // Tiempo de ejecución máximo
                ])->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'tether',
                    'vs_currencies' => 'cop,usd',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'precio_cop' => (float) ($data['tether']['cop'] ?? 4000.00),
                        'precio_usd' => (float) ($data['tether']['usd'] ?? 1.00),
                        'actualizado' => now()->toDateTimeString(),
                        'fallback' => false,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Error consultando precio USDT en CoinGecko: ' . $e->getMessage());
            }

            // Fallback si falla la API
            return [
                'precio_cop' => 4150.00, // Precio aproximado fallback
                'precio_usd' => 1.00,
                'actualizado' => now()->toDateTimeString(),
                'fallback' => true,
            ];
        });
    }
}
