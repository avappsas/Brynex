<?php

namespace App\Console\Commands;

use App\Services\NuevaEps\NuevaEpsPortalService;
use Illuminate\Console\Command;

/**
 * Prueba la salida hacia Nueva EPS: si el túnel de la oficina está conectado
 * (NUEVA_EPS_TUNEL), con qué IP sale el servidor (por PROXY_COLOMBIA, si hay) y
 * si el portal muestra el formulario de ingreso. No usa claves del portal.
 */
class NuevaEpsConexion extends Command
{
    protected $signature = 'eps:nueva-eps-conexion';

    protected $description = 'Muestra por dónde sale el servidor hacia Nueva EPS y si el portal deja entrar';

    public function handle(): int
    {
        $tunel = config('services.nueva_eps.tunel');

        $this->line(match (true) {
            (bool) $tunel                                => "Probando por el túnel de la oficina ({$tunel})…",
            (bool) config('services.proxy_colombia.url') => 'Probando por el proxy de PROXY_COLOMBIA…',
            default                                      => 'Sin túnel ni proxy: probando con la IP directa del servidor…',
        });

        if (NuevaEpsPortalService::tunelConectado() === false) {
            $this->error("El túnel no está escuchando en {$tunel}: el PC de la oficina no está conectado.");

            return self::FAILURE;
        }

        $r = NuevaEpsPortalService::probarConexion();

        $this->table(['Dato', 'Valor'], [
            ['Túnel', $r['tunel'] ?? 'no'],
            ['Proxy', ($r['proxy'] ?? false) ? 'sí' : 'no'],
            // Con túnel esta IP es la del servidor: ipinfo no pasa por el túnel.
            ['IP del servidor / proxy', $r['ip'] ?? ($r['ip_error'] ?? '—')],
            ['País / ciudad', trim(($r['pais'] ?? '—').' / '.($r['ciudad'] ?? '—'))],
            ['Red', $r['red'] ?? '—'],
            ['Nueva EPS respondió desde', $r['nueva_eps_remoto'] ?? '—'],
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
