<?php

namespace App\Services\Dataico;

use App\Models\ConfiguracionBrynex;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta los datos de un tercero en la DIAN a través del portal de Dataico.
 *
 * ── Por qué esto no va por el API normal ──────────────────────────────────
 *
 * El API de integración (`ApiClient`, token `Auth-token`) NO expone la consulta
 * a la DIAN: de `direct/dataico_api/v2` solo responden `invoices` y
 * `customers`; `third_parties`, `contacts`, `dian`, `rut` y compañía dan 404.
 * El botón «Consulta DIAN» que se ve en su portal es una mutación interna de
 * su aplicación web —Fulcro/Pathom— que viaja por `POST /api` en transit-json
 * y se autentica con la COOKIE de sesión de un usuario, no con el token.
 *
 * O sea: esto es una puerta que no está documentada ni prometida. Funciona hoy
 * y puede dejar de funcionar el día que Dataico redespliegue su front, sin
 * aviso y sin que sea un error nuestro. Está aislado en este servicio para que
 * el día que se rompa se rompa solo aquí, y para que si algún día lo exponen
 * en el API de verdad, cambiar de camino sea reescribir un método.
 *
 * ── El captcha, que es la razón de tanto cuidado ──────────────────────────
 *
 * El login del portal acepta un tercer campo, `:user/captcha`, y la aplicación
 * tiene un `ui/captcha-required?` que se enciende con `/captcha.png?attempt=N`.
 * Traducido: el captcha aparece DESPUÉS de fallar. Por eso aquí un login
 * rechazado no se reintenta nunca —se marca el bloqueo y se espera a que
 * alguien corrija la clave a mano—. Reintentar una contraseña mala en un bucle
 * es la forma exacta de dejar la cuenta pidiendo captcha y tumbar de paso la
 * emisión de facturas, que usa la misma cuenta.
 */
class PortalDianService
{
    private const BASE = 'https://app.dataico.com';

    /**
     * El portal tiene DOS puertas, y confundirlas cuesta una tarde.
     *
     * `/api` es la de adentro: exige sesión y responde 490
     * `dataico.ui.user.auth/session-expired` a cualquiera que llegue sin
     * cookie —incluido, claro, el que va a entrar—. `/public-api` es la de
     * afuera y es por donde entra el login.
     *
     * Y no llevan la misma forma: la de adentro espera la transacción completa
     * (la mutación unida a lo que se quiere de vuelta), mientras que la de
     * afuera solo acepta la mutación suelta. Mandarle a `/public-api` una
     * transacción con `join` responde 500 «Parser threw an exception».
     */
    private const RUTA_PRIVADA = '/api';

    private const RUTA_PUBLICA = '/public-api';

    private const MUTACION_CONSULTA = 'dataico.mutations.party/search-dian-for-party';

    private const MUTACION_LOGIN = 'dataico/login';

    /** Dónde viven las credenciales. Son de BryNex, no de un aliado. */
    public const CLAVE_EMAIL = 'dataico_portal_email';

    public const CLAVE_CLAVE = 'dataico_portal_clave';

    public const CLAVE_COMPANY = 'dataico_portal_company_id';

    public const CLAVE_USER = 'dataico_portal_user_id';

    private const CACHE_COOKIES = 'dataico.portal.cookies';

    private const CACHE_BLOQUEO = 'dataico.portal.bloqueo';

    /** La sesión del portal dura bastante más, pero renovarla es barato. */
    private const HORAS_SESION = 6;

    /**
     * Códigos de tipo de documento de la DIAN, tal como los usa el portal.
     * Salieron de su propia tabla, no de suponerlos.
     */
    public const TIPOS_DIAN = [
        'RC' => '11',
        'TI' => '12',
        'CC' => '13',
        'TE' => '21',
        'CE' => '22',
        'NIT' => '31',
        'PAS' => '41',
        'DOC-IE' => '42',
        'PEP' => '47',
        'PPT' => '48',
        'NUIP' => '91',
    ];

    /** Lo que se ofrece en la pantalla, con el nombre que usa BryNex. */
    public const TIPOS_VISIBLES = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'NIT' => 'NIT',
        'PPT' => 'Permiso por Protección Temporal',
        'PEP' => 'Permiso Especial de Permanencia',
        'PAS' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
    ];

    /**
     * Datos del tercero según la DIAN.
     *
     * @return array{
     *     encontrado: bool, mensaje: string, tipo_doc: string, identificacion: string,
     *     tipo_persona: string, primer_nombre: string, otros_nombres: string,
     *     primer_apellido: string, segundo_apellido: string, nombre_completo: string,
     *     nombre_comercial: string, correo: string
     * }
     *
     * @throws RuntimeException si no hay credenciales, están bloqueadas o el portal no responde
     */
    public function consultar(string $tipoDoc, string $numero): array
    {
        $numero = preg_replace('/\D+/', '', $numero) ?? '';
        $tipoDoc = strtoupper(trim($tipoDoc)) ?: 'CC';

        if ($numero === '') {
            throw new RuntimeException('Falta el número de documento.');
        }

        if (! isset(self::TIPOS_DIAN[$tipoDoc])) {
            throw new RuntimeException("Tipo de documento no soportado: {$tipoDoc}.");
        }

        $codigo = self::TIPOS_DIAN[$tipoDoc];

        // El portal separa persona natural (2) de jurídica (1) por el tipo de
        // documento, igual que `Adquiriente`: NIT es sociedad, lo demás no.
        $tipoPersona = $tipoDoc === 'NIT' ? '1' : '2';

        $respuesta = $this->mutar(self::MUTACION_CONSULTA, [
            'id' => ['~#fulcro/tempid', '~u'.self::uuid()],
            'identification' => $numero,
            'identification-type' => $codigo,
            'party-type' => $tipoPersona,
        ], [
            '~:party/id', '~:party/identification-type', '~:party/identification',
            '~:party/type', '~:party/first-name', '~:party/other-names',
            '~:party/family-name', '~:party/second-last-name',
            '~:party/company-name', '~:party/email',
            '~:com.wsscode.pathom.core/errors', '~$*',
        ]);

        $datos = $respuesta[self::MUTACION_CONSULTA] ?? [];

        if (! is_array($datos)) {
            throw new RuntimeException('El portal respondió algo que no se entiende.');
        }

        $resultado = $datos['ui/search-dian-result'] ?? [];
        $estado = is_array($resultado) ? ($resultado['status'] ?? '') : '';
        $mensaje = is_array($resultado) ? (string) ($resultado['message'] ?? '') : '';

        // La DIAN puede responder «no existe» sin que eso sea una falla: se
        // distingue por el estado, no por la ausencia de campos.
        $encontrado = $estado === 'ok';

        $nombres = trim(($datos['party/first-name'] ?? '').' '.($datos['party/other-names'] ?? ''));
        $apellidos = trim(($datos['party/family-name'] ?? '').' '.($datos['party/second-last-name'] ?? ''));

        // Una sociedad no tiene nombre ni apellidos: su nombre viaja en
        // `company-name`. Sin esto, consultar un NIT devuelve la ficha con el
        // nombre en blanco aunque la DIAN sí respondió.
        $esJuridica = ($datos['party/type'] ?? $tipoPersona) === '1';
        $nombreCompleto = $esJuridica
            ? trim((string) ($datos['party/company-name'] ?? ''))
            : trim("$nombres $apellidos");

        Log::info('[dataico-dian] consulta', [
            'usuario_id' => auth()->id(),
            'tipo_doc' => $tipoDoc,
            'documento' => $numero,
            'estado' => $estado ?: 'sin-estado',
        ]);

        return [
            'encontrado' => $encontrado,
            'mensaje' => $mensaje !== '' ? $mensaje : ($encontrado ? '' : 'La DIAN no devolvió datos para ese documento.'),
            'tipo_doc' => $tipoDoc,
            'identificacion' => (string) ($datos['party/identification'] ?? $numero),
            'tipo_persona' => $esJuridica ? 'PERSONA_JURIDICA' : 'PERSONA_NATURAL',
            'primer_nombre' => (string) ($datos['party/first-name'] ?? ''),
            'otros_nombres' => (string) ($datos['party/other-names'] ?? ''),
            'primer_apellido' => (string) ($datos['party/family-name'] ?? ''),
            'segundo_apellido' => (string) ($datos['party/second-last-name'] ?? ''),
            'nombre_completo' => $nombreCompleto,
            'nombre_comercial' => (string) ($datos['party/company-name'] ?? ''),
            'correo' => (string) ($datos['party/email'] ?? ''),
        ];
    }

    /** Para la pantalla: si se puede consultar y por qué no, si no se puede. */
    public function estado(): array
    {
        $bloqueo = Cache::get(self::CACHE_BLOQUEO);

        return [
            'configurado' => $this->email() !== '' && $this->clave() !== '',
            'correo' => $this->email(),
            'sesion_activa' => Cache::has(self::CACHE_COOKIES),
            'bloqueo' => $bloqueo,
        ];
    }

    /**
     * Guarda las credenciales del portal y suelta el bloqueo.
     *
     * La contraseña se cifra con la llave de la app, igual que el `auth_token`
     * de `DataicoConfiguracion`. Guardar credenciales nuevas es justamente la
     * señal de «ya se corrigió», así que se limpian bloqueo y sesión vieja.
     */
    public function guardarCredenciales(string $email, ?string $clave): void
    {
        ConfiguracionBrynex::establecer(self::CLAVE_EMAIL, trim($email));

        if (filled($clave)) {
            ConfiguracionBrynex::establecer(self::CLAVE_CLAVE, Crypt::encryptString($clave));
        }

        Cache::forget(self::CACHE_BLOQUEO);
        Cache::forget(self::CACHE_COOKIES);
    }

    // ─── El transporte ────────────────────────────────────────────────

    /**
     * Ejecuta una mutación del portal y devuelve su respuesta ya decodificada.
     *
     * Si la sesión guardada ya no sirve, entra una vez y reintenta. Una sola
     * vez: un segundo fallo es un problema de credenciales, no de sesión.
     */
    private function mutar(string $mutacion, array $params, array $query): array
    {
        $cuerpo = self::armarTransaccion($mutacion, $params, $query);

        $respuesta = $this->enviar($cuerpo, $this->sesion());

        if ($this->pareceSesionCaida($respuesta, $mutacion)) {
            Cache::forget(self::CACHE_COOKIES);
            $respuesta = $this->enviar($cuerpo, $this->sesion());
        }

        return $respuesta;
    }

    /** @return array<string, mixed> */
    private function enviar(string $cuerpo, CookieJar $galletas): array
    {
        $peticion = Http::withHeaders(array_filter([
            'Content-Type' => 'application/transit+json',
            'Accept' => 'application/transit+json',
            'x-dataico-company-id' => ConfiguracionBrynex::obtener(self::CLAVE_COMPANY) ?: null,
            'x-dataico-user-id' => ConfiguracionBrynex::obtener(self::CLAVE_USER) ?: null,
        ]))
            ->withOptions(['cookies' => $galletas])
            ->timeout(45)
            ->connectTimeout(10);

        try {
            $r = $peticion->withBody($cuerpo, 'application/transit+json')->post(self::BASE.self::RUTA_PRIVADA);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo contactar el portal de Dataico: '.$e->getMessage());
        }

        // Las cookies se renuevan en cada respuesta; guardarlas evita entrar
        // otra vez en la siguiente consulta.
        $this->recordarSesion($galletas);

        if (! $r->successful()) {
            throw new RuntimeException("El portal de Dataico respondió HTTP {$r->status()}.");
        }

        $decodificado = Transit::decodificar($r->body());

        return is_array($decodificado) ? $decodificado : [];
    }

    /**
     * ¿La respuesta huele a sesión vencida?
     *
     * El portal no devuelve 401: responde 200 con la mutación vacía o con un
     * error de Pathom, y es el navegador el que muestra «Su sesión ha
     * expirado». Desde acá lo que se ve es que la mutación no trajo su
     * resultado.
     */
    private function pareceSesionCaida(array $respuesta, string $mutacion): bool
    {
        $datos = $respuesta[$mutacion] ?? null;

        return ! is_array($datos) || $datos === [];
    }

    /** Cookies de una sesión válida, entrando al portal si hace falta. */
    private function sesion(): CookieJar
    {
        if ($guardadas = Cache::get(self::CACHE_COOKIES)) {
            return $this->galletasDesde($guardadas);
        }

        return $this->entrar();
    }

    /**
     * Inicia sesión en el portal. Solo se llama cuando no hay sesión guardada.
     *
     * @throws RuntimeException si falta configuración, hay bloqueo o el portal rechaza
     */
    private function entrar(): CookieJar
    {
        if ($bloqueo = Cache::get(self::CACHE_BLOQUEO)) {
            throw new RuntimeException($bloqueo);
        }

        $email = $this->email();
        $clave = $this->clave();

        if ($email === '' || $clave === '') {
            throw new RuntimeException('Falta configurar el usuario y la contraseña del portal de Dataico.');
        }

        $galletas = new CookieJar;

        try {
            $r = Http::withHeaders([
                'Content-Type' => 'application/transit+json',
                'Accept' => 'application/transit+json',
            ])
                ->withOptions(['cookies' => $galletas])
                ->timeout(45)
                ->connectTimeout(10)
                ->withBody(self::armarMutacionSuelta(self::MUTACION_LOGIN, [
                    'user/email' => $email,
                    'user/password' => $clave,
                    // Va explícito en null: sin la llave el parser revienta.
                    // El portal lo llena solo cuando enciende el captcha, y si
                    // llegamos a ese punto ya hay que ir a arreglarlo a mano.
                    'user/captcha' => null,
                ]), 'application/transit+json')
                ->post(self::BASE.self::RUTA_PUBLICA);
        } catch (\Throwable $e) {
            // Una caída de red no es culpa de la contraseña: no se bloquea.
            throw new RuntimeException('No se pudo contactar el portal de Dataico: '.$e->getMessage());
        }

        // Un HTTP raro NO es una contraseña mala: el portal responde 200 aunque
        // rechace las credenciales, y devuelve otros códigos cuando lo que no
        // le gusta es la forma de la petición. Bloquear aquí dejaría el módulo
        // detenido siete días por un problema que no tiene que ver con la
        // clave, así que esto sale como una falla normal.
        if (! $r->successful()) {
            Log::error('[dataico-dian] el portal rechazó la petición de ingreso', [
                'status' => $r->status(),
                'cuerpo' => mb_substr($r->body(), 0, 500),
            ]);

            throw new RuntimeException("El portal de Dataico rechazó el ingreso (HTTP {$r->status()}). "
                .'No es la contraseña: revisa el log, es la petición.');
        }

        $cuerpo = Transit::decodificar($r->body());
        $cuerpo = is_array($cuerpo) ? $cuerpo : [];
        $resultado = $cuerpo[self::MUTACION_LOGIN] ?? [];
        $resultado = is_array($resultado) ? $resultado : [];

        // Cuando la clave está mala el portal responde 200 con
        // `{:user/id :no-user, :token "Invalid Credentials"}`. Ese es el único
        // caso que merece bloqueo: es el que se repite y enciende el captcha.
        $usuario = (string) ($resultado['user/id'] ?? '');

        if ($usuario === '' || $usuario === 'no-user') {
            $this->bloquear('El portal no aceptó el usuario y la contraseña de Dataico. '
                .'Corrígelos en la pantalla de Consulta DIAN: no se volverá a intentar hasta entonces, '
                .'porque fallar varias veces activa el captcha de Dataico y deja la cuenta sin ingreso.');
        }

        if ($galletas->count() === 0) {
            throw new RuntimeException('El portal aceptó el ingreso pero no entregó sesión. '
                .'Es un cambio del lado de Dataico: revisa el log.');
        }

        $this->recordarIdentidad($resultado);
        $this->recordarSesion($galletas);

        Log::info('[dataico-dian] sesión iniciada en el portal', ['usuario_id' => auth()->id()]);

        return $galletas;
    }

    /**
     * Los headers `x-dataico-*` que el portal manda en cada llamada. Se toman
     * de la respuesta del login si viene, y si no se quedan los configurados.
     */
    private function recordarIdentidad(array $resultado): void
    {
        // El login los devuelve con el prefijo `user/`: `user/company-id` es
        // la empresa dueña de la cuenta, no hay que sembrarla a mano.
        $company = $resultado['user/company-id'] ?? null;
        $usuario = $resultado['user/id'] ?? null;

        if (is_string($company) && $company !== '') {
            ConfiguracionBrynex::establecer(self::CLAVE_COMPANY, $company);
        }

        if (is_string($usuario) && $usuario !== '') {
            ConfiguracionBrynex::establecer(self::CLAVE_USER, $usuario);
        }
    }

    private function bloquear(string $mensaje): never
    {
        Cache::put(self::CACHE_BLOQUEO, $mensaje, now()->addDays(7));
        Cache::forget(self::CACHE_COOKIES);

        Log::warning('[dataico-dian] ingreso bloqueado', ['motivo' => $mensaje]);

        throw new RuntimeException($mensaje);
    }

    private function recordarSesion(CookieJar $galletas): void
    {
        if ($galletas->count() === 0) {
            return;
        }

        Cache::put(self::CACHE_COOKIES, $galletas->toArray(), now()->addHours(self::HORAS_SESION));
    }

    private function galletasDesde(array $guardadas): CookieJar
    {
        $galletas = new CookieJar;

        foreach ($guardadas as $cookie) {
            $galletas->setCookie(new SetCookie($cookie));
        }

        return $galletas;
    }

    private function email(): string
    {
        return trim((string) ConfiguracionBrynex::obtener(self::CLAVE_EMAIL, ''));
    }

    private function clave(): string
    {
        $guardada = (string) ConfiguracionBrynex::obtener(self::CLAVE_CLAVE, '');

        if ($guardada === '') {
            return '';
        }

        try {
            return Crypt::decryptString($guardada);
        } catch (\Throwable $e) {
            Log::error('[dataico-dian] la contraseña guardada no se pudo descifrar');

            return '';
        }
    }

    // ─── transit ──────────────────────────────────────────────────────

    /**
     * Una transacción EQL con una sola mutación, en transit-json.
     *
     * La forma la copia del portal tal cual: un vector con un `cmap` (mapa de
     * claves compuestas) cuya única clave es la lista `(mutación params)` y
     * cuyo valor es lo que se quiere de vuelta.
     */
    private static function armarTransaccion(string $mutacion, array $params, array $query): string
    {
        $plano = ['^ '];

        foreach ($params as $clave => $valor) {
            $plano[] = '~:'.$clave;
            $plano[] = $valor;
        }

        // El `cmap` va PLANO —clave, valor, clave, valor—, no como lista de
        // pares. Un nivel de más de corchetes se parsea sin quejarse y falla
        // después, adentro, con un 500 «Error del servidor interno» que no
        // dice nada. Aquí solo va un par: la mutación y lo que se pide.
        return json_encode([[
            '~#cmap',
            [
                ['~#list', ['~$'.$mutacion, $plano]],
                $query,
            ],
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Una mutación sola, sin decir qué se quiere de vuelta.
     *
     * Es la forma que acepta `/public-api`, y la única: unirle un `join` como
     * el que lleva la transacción de `/api` le saca un 500 al parser.
     */
    private static function armarMutacionSuelta(string $mutacion, array $params): string
    {
        $plano = ['^ '];

        foreach ($params as $clave => $valor) {
            $plano[] = '~:'.$clave;
            $plano[] = $valor;
        }

        return json_encode([
            ['~#list', ['~$'.$mutacion, $plano]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function uuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
