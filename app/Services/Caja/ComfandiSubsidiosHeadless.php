<?php

namespace App\Services\Caja;

use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Los bloqueos de subsidio de una empresa, leídos por un Chrome del servidor.
 *
 * Es la otra manera de hacer lo mismo que la extensión BryNex Portales: ella
 * usa el navegador de la persona y esto usa uno propio, para que la revisión
 * pueda correr de noche. El trabajo de pantalla está en
 * `scripts/comfandi-subsidios.mjs`, igual que en Colmena y ARL Sura; aquí solo
 * se buscan las claves y se traduce la respuesta.
 */
class ComfandiSubsidiosHeadless
{
    /** Diez segundos por trabajador más el login, con holgura. */
    private const SEGUNDOS_BASE = 180;

    private const SEGUNDOS_POR_TRABAJADOR = 30;

    /**
     * @param  array<string>  $documentos  cédulas de esa empresa
     * @return array{ok:bool, nit?:string, empresa?:?string, movimientos?:array, revisados?:array, errores?:array, error?:string}
     */
    public function bloqueos(string $nit, array $documentos, int $meses = 4): array
    {
        $documentos = array_values(array_filter(array_map(fn ($d) => preg_replace('/\D/', '', (string) $d), $documentos)));

        if (! $documentos) {
            return ['ok' => false, 'error' => 'No llegó ninguna cédula para consultar.'];
        }

        $clave = $this->credencial($nit);

        if (! $clave) {
            return ['ok' => false, 'error' => "La empresa {$nit} no tiene la clave de Comfandi en el módulo de claves."];
        }

        // El proxy va por stdin junto con la clave, para que no quede en `ps`.
        // Comfandi tiene Akamai delante y le niega el acceso a la IP del
        // servidor: sin él, el portal ni siquiera muestra el login.
        $entrada = json_encode([
            'usuario' => $clave['usuario'],
            'contrasena' => $clave['contrasena'],
            'documentos' => $documentos,
            'meses' => $meses,
            'proxy' => config('services.proxy_colombia.url'),
        ], JSON_UNESCAPED_UNICODE);

        $resultado = Process::path(base_path())
            ->timeout(self::SEGUNDOS_BASE + self::SEGUNDOS_POR_TRABAJADOR * count($documentos))
            ->input($entrada)
            ->run(ArlSuraSesionService::binarioNode().' scripts/comfandi-subsidios.mjs');

        $salida = json_decode(trim($resultado->output()), true) ?: [];

        if (! ($salida['ok'] ?? false)) {
            $error = $salida['error'] ?? trim($resultado->errorOutput()) ?: 'El portal no respondió.';

            // El script nunca imprime la clave: lo que llega es la pantalla en
            // la que se quedó, que es lo que hace falta para arreglarlo.
            Log::warning('Comfandi subsidios: no se pudo leer el portal', [
                'nit' => $nit,
                'error' => $error,
                'url' => $salida['url'] ?? null,
            ]);

            return ['ok' => false, 'error' => $error];
        }

        return $salida;
    }

    /**
     * La clave de Comfandi de esa empresa, en cualquier aliado.
     *
     * Es la misma empresa ante la caja, así que no se filtra por aliado: si la
     * clave está guardada una vez, sirve para todos.
     *
     * @return array{usuario:string, contrasena:string}|null
     */
    public function credencial(string $nit): ?array
    {
        $fila = DB::table('clave_accesos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('rs.nit', preg_replace('/\D/', '', $nit))
            ->where('c.tipo', 'CAJA')
            ->where('c.entidad', 'like', '%COMFANDI%')
            ->where('c.activo', true)
            ->whereNotNull('c.usuario')->where('c.usuario', '<>', '')
            ->orderByDesc('c.updated_at')
            ->first(['c.usuario', 'c.contrasena']);

        return $fila ? ['usuario' => trim($fila->usuario), 'contrasena' => (string) $fila->contrasena] : null;
    }
}
