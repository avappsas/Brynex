<?php

namespace App\Services\NuevaEps;

use App\Models\EpsPortalEmpresa;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Corre `scripts/nueva-eps-portal.mjs` con la clave de la empresa.
 *
 * La clave sale del módulo de claves de BryNex por el NIT de la empresa, igual
 * que en ARL Sura: cambiarla allí basta. Si el portal la rechaza se guarda su
 * huella y no se vuelve a probar hasta que cambie, porque los portales bloquean
 * al usuario tras varios intentos fallidos.
 */
class NuevaEpsPortalService
{
    public const ENTIDAD = 'nueva_eps';

    /** Códigos de Nueva EPS en la tabla `eps` (contributivo y movilidad). */
    public const CODIGOS_EPS = ['EPS037', 'EPS041'];

    /** Login, elegir empresa y abrir la SPA ya suman cerca de un minuto. */
    private const TIMEOUT_SEGUNDOS = 240;

    /**
     * La clave de la empresa, o por qué no se puede usar.
     *
     * @return array{usuario:string, contrasena:string, empresa:EpsPortalEmpresa}|array{error:string}
     */
    public static function credencial(string $nit): array
    {
        $nit     = preg_replace('/\D/', '', $nit);
        $empresa = EpsPortalEmpresa::de(self::ENTIDAD, $nit);

        if ($portal = $empresa->usuarioPortal) {
            $usuario = trim($portal->tipo_documento.' '.$portal->usuario);
            $clave   = (string) $portal->contrasena;
        } else {
            $fila = DB::table('clave_accesos as c')
                ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
                ->where('rs.nit', $nit)
                ->where('c.tipo', 'EPS')
                ->where('c.entidad', 'like', '%NUEVA%EPS%')
                ->where('c.activo', true)
                ->whereNotNull('c.usuario')->where('c.usuario', '<>', '')
                ->whereNotNull('c.contrasena')->where('c.contrasena', '<>', '')
                ->orderByDesc('c.updated_at')
                ->first(['c.usuario', 'c.contrasena']);

            if (! $fila) {
                return ['error' => 'La empresa no tiene la clave de Nueva EPS en el módulo de claves.'];
            }

            $usuario = trim($fila->usuario);
            $clave   = (string) $fila->contrasena;
        }

        if ($empresa->clave_fallida_hash && hash_equals($empresa->clave_fallida_hash, self::huella($usuario, $clave))) {
            return ['error' => 'Nueva EPS ya rechazó esta clave ('.($empresa->ultimo_error ?: 'sin detalle')
                .'). Actualízala en el módulo de claves para volver a intentar.'];
        }

        return ['usuario' => $usuario, 'contrasena' => $clave, 'empresa' => $empresa];
    }

    /**
     * @return array La salida del script; con `error` si no se pudo ni empezar.
     */
    public static function ejecutar(string $nit, array $datos): array
    {
        $cred = self::credencial($nit);

        if (isset($cred['error'])) {
            return ['ok' => false, 'paso' => 'credencial', 'error' => $cred['error']];
        }

        $resultado = Process::path(base_path())
            ->timeout(self::TIMEOUT_SEGUNDOS)
            ->input(json_encode($datos + [
                'usuario'    => $cred['usuario'],
                'contrasena' => $cred['contrasena'],
                'nitEmpresa' => preg_replace('/\D/', '', $nit),
            ], JSON_UNESCAPED_UNICODE))
            ->run(ArlSuraSesionService::binarioNode().' scripts/nueva-eps-portal.mjs');

        $salida = json_decode(trim($resultado->output()), true) ?: [
            'ok' => false, 'paso' => 'proceso',
            'error' => trim($resultado->errorOutput()) ?: 'El proceso del portal no devolvió respuesta.',
        ];

        $empresa = $cred['empresa'];

        if ($salida['ok'] ?? false) {
            $empresa->update(['ultima_sesion_at' => now(), 'ultimo_error' => null, 'clave_fallida_hash' => null]);
        } else {
            // El mensaje nunca trae la clave: el script no la imprime.
            Log::warning('Nueva EPS: el portal falló', ['nit' => $nit, 'paso' => $salida['paso'] ?? null, 'error' => $salida['error'] ?? null]);

            if (($salida['paso'] ?? null) === 'login') {
                $empresa->update([
                    'clave_fallida_hash' => self::huella($cred['usuario'], $cred['contrasena']),
                    'ultimo_error'       => mb_substr((string) ($salida['error'] ?? ''), 0, 300),
                ]);
            }
        }

        return $salida;
    }

    private static function huella(string $usuario, string $clave): string
    {
        return hash('sha256', $usuario.'|'.$clave);
    }
}
