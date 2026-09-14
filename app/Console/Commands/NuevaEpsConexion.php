<?php

namespace App\Console\Commands;

use App\Services\NuevaEps\NuevaEpsPortalService;
use Illuminate\Console\Command;

/**
 * Prueba la salida hacia Nueva EPS: con qué IP y país sale el servidor (por el
 * proxy de PROXY_COLOMBIA, si hay) y si el portal muestra el formulario de
 * ingreso. No usa claves del portal.
 */
class NuevaEpsConexion extends Command
{
    protected $signature = 'eps:nueva-eps-conexion';

    protected $description = 'Muestra con qué IP sale el servidor hacia Nueva EPS y si el portal deja entrar';

    public function handle(): int
    {
        $this->line(config('services.proxy_colombia.url')
            ? 'Probando por el proxy de PROXY_COLOMBIA…'
            : 'Sin PROXY_COLOMBIA: probando con la IP directa del servidor…');

        $r = NuevaEpsPortalService::probarConexion();

        $this->table(['Dato', 'Valor'], [
            ['Proxy', ($r['proxy'] ?? false) ? 'sí' : 'no'],
            ['IP de salida', $r['ip'] ?? ($r['ip_error'] ?? '—')],
            ['País / ciudad', trim(($r['pais'] ?? '—').' / '.($r['ciudad'] ?? '—'))],
            ['Red', $r['red'] ?? '—'],
            ['Nueva EPS HTTP', $r['nueva_eps_http'] ?? '—'],
            ['Título', $r['nueva_eps_titulo'] ?? '—'],
            ['Formulario de ingreso', ($r['formulario_login'] ?? false) ? '✅ sí' : '❌ no'],
        ]);

        if ($r['ok'] ?? false) {
            $this->info('Nueva EPS deja entrar desde esta conexión.');

            return self::SUCCESS;
        }

        $this->error($r['error'] ?? 'No se pudo probar la conexión.');

        return self::FAILURE;
    }
}
