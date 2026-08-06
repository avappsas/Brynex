<?php

namespace App\Services;

use App\Models\AccesoUsuario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Deja constancia de cada entrada al panel y marca la que se sale de lo normal.
 *
 * Sobre identificar la máquina: **la MAC no se puede leer desde un navegador**,
 * no existe API web que la exponga. Lo que sí identifica un equipo entre
 * sesiones son dos señales que se complementan:
 *
 *  · `dispositivo_id` — UUID que pone el servidor en una cookie httpOnly de 5
 *    años. No depende de la red, así que sobrevive a un cambio de IP o a una
 *    VPN. Se pierde si borran cookies o entran en modo incógnito.
 *  · `huella` — hash de las características del navegador (pantalla, zona
 *    horaria, GPU, idiomas). Sobrevive justamente a lo que mata a la cookie.
 *
 * Ninguna de las dos es prueba por sí sola; juntas, un equipo que vuelve se
 * reconoce aunque cambie de red, y uno nuevo se nota aunque copie la IP.
 */
class RegistroAccesoService
{
    public const COOKIE_DISPOSITIVO = 'brynex_did';

    /** Cinco años: la cookie debe sobrevivir al equipo, no a la sesión. */
    private const MINUTOS_COOKIE = 60 * 24 * 365 * 5;

    /** Cuántos accesos previos se miran para decidir qué es "habitual". */
    private const HISTORIAL = 200;

    /** IPs distintas en 24h a partir de las cuales se sospecha rotación. */
    private const UMBRAL_IP_ROTATIVA = 5;

    /**
     * Registra el acceso. Nunca lanza: un fallo aquí no puede impedir entrar.
     */
    public function registrar(User $user, Request $request): ?AccesoUsuario
    {
        try {
            $dispositivoId = $this->dispositivoId($request);
            $huella = $this->huella($request);
            $ip = $request->ip();

            $anomalias = $this->detectarAnomalias($user, $ip, $dispositivoId, $huella);

            $acceso = AccesoUsuario::create([
                'aliado_id' => (int) $user->aliado_id,
                'user_id' => $user->id,
                'ip' => $ip,
                'dispositivo_id' => $dispositivoId,
                'huella' => $huella,
                'user_agent' => Str::limit((string) $request->userAgent(), 495, ''),
                'anomalias' => $anomalias ? implode(',', $anomalias) : null,
            ]);

            // forceFill porque estas columnas no están en $fillable a propósito:
            // las escribe el sistema, nunca un formulario.
            $user->forceFill([
                'ultimo_acceso' => now(),
                'ultima_ip' => $ip,
                'ultimo_dispositivo_id' => $dispositivoId,
            ])->saveQuietly();

            return $acceso;
        } catch (\Throwable $e) {
            Log::warning('RegistroAccesoService falló: '.$e->getMessage());

            return null;
        }
    }

    /**
     * UUID del equipo, desde la cookie. Si no existe o viene manipulada, se
     * emite una nueva — un valor inventado por el cliente no debe entrar a la BD.
     */
    private function dispositivoId(Request $request): string
    {
        $id = $request->cookie(self::COOKIE_DISPOSITIVO);

        if (is_string($id) && preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $id)) {
            return $id;
        }

        $id = (string) Str::uuid();

        // httpOnly: el JS de la página no puede leerla ni pisarla.
        Cookie::queue(cookie(
            name: self::COOKIE_DISPOSITIVO,
            value: $id,
            minutes: self::MINUTOS_COOKIE,
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $id;
    }

    /**
     * Huella que manda el formulario de login, normalizada a sha256. El valor
     * crudo nunca se guarda: solo interesa comparar si es el mismo de antes.
     */
    private function huella(Request $request): ?string
    {
        $cruda = $request->input('huella');

        if (! is_string($cruda) || trim($cruda) === '') {
            return null;
        }

        return hash('sha256', trim($cruda));
    }

    /**
     * @return string[] códigos de anomalía; vacío si el acceso es rutinario
     */
    private function detectarAnomalias(User $user, ?string $ip, string $dispositivoId, ?string $huella): array
    {
        $previos = AccesoUsuario::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(self::HISTORIAL)
            ->get(['ip', 'dispositivo_id', 'huella', 'created_at']);

        $anomalias = [];
        $esPrimerAcceso = $previos->isEmpty();

        // Ojo con salir temprano en el primer acceso: el caso que más importa
        // —una cuenta recién creada estrenándose desde un equipo que YA entró
        // con otra cuenta— ocurre precisamente en el primer acceso. Se marca
        // `primer_acceso` y se siguen evaluando el resto de señales.
        if ($esPrimerAcceso) {
            $anomalias[] = 'primer_acceso';
        }

        if (! $esPrimerAcceso && ! $previos->pluck('dispositivo_id')->contains($dispositivoId)) {
            $anomalias[] = 'dispositivo_nuevo';
        }

        if ($huella && ! $esPrimerAcceso && ! $previos->pluck('huella')->contains($huella)) {
            $anomalias[] = 'huella_nueva';
        }

        if ($ip && ! $esPrimerAcceso) {
            if (! $previos->pluck('ip')->contains($ip)) {
                $anomalias[] = 'ip_nueva';
            }

            // La IP suele ser dinámica, así que "IP nueva" sola es ruido. Lo
            // que informa de verdad es entrar desde otra RED.
            $redesPrevias = $previos->pluck('ip')->filter()->map(fn ($i) => $this->red($i))->unique();
            if (! $redesPrevias->contains($this->red($ip))) {
                $anomalias[] = 'red_nueva';
            }

            $desde = now()->subDay();
            $ipsRecientes = $previos
                ->filter(fn ($a) => $a->created_at && $a->created_at->greaterThanOrEqualTo($desde))
                ->pluck('ip')
                ->push($ip)
                ->filter()
                ->unique();

            if ($ipsRecientes->count() >= self::UMBRAL_IP_ROTATIVA) {
                $anomalias[] = 'ip_rotativa';
            }
        }

        // La señal más útil de todas: un equipo que ya entró con OTRA cuenta.
        // Es lo que delata una credencial prestada o a un tercero operando
        // varias cuentas del mismo aliado.
        $multicuenta = AccesoUsuario::where('dispositivo_id', $dispositivoId)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($multicuenta) {
            $anomalias[] = 'dispositivo_multicuenta';
        }

        return $anomalias;
    }

    /**
     * Red a la que pertenece una IP: /24 en IPv4, los primeros 4 grupos en IPv6.
     * Sirve para distinguir "le cambió la IP en su casa" de "entró desde otro lado".
     */
    private function red(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 4));
        }

        $octetos = explode('.', $ip);

        return count($octetos) === 4 ? implode('.', array_slice($octetos, 0, 3)) : $ip;
    }
}
