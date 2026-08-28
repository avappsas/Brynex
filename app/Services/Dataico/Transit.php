<?php

namespace App\Services\Dataico;

/**
 * El dialecto con el que habla el portal de Dataico.
 *
 * El API de integración (`ApiClient`) usa JSON normal. Su portal web, no: es
 * una aplicación Fulcro/Pathom y todo pasa por un solo `POST /api` cuyo cuerpo
 * viaja en **transit-json**, la serialización de Clojure. No hay librería de
 * transit para PHP que valga la pena traer por dos llamadas, así que aquí vive
 * el subconjunto que se necesita —y solo ese—.
 *
 * Lo que hay que saber para leer el código:
 *
 * - `["^ ", k1, v1, k2, v2]` es un MAPA (el `^ ` es el marcador), no un vector.
 * - `"~:foo"` es un keyword, `"~$foo"` un símbolo, `"~ubb-cc"` un uuid.
 * - `["~#tag", valor]` es un valor etiquetado (`~#list`, `~#cmap`, `~#fulcro/tempid`).
 * - `"^0"`, `"^1"`, … son la CACHÉ: transit no repite un keyword largo, lo
 *   escribe una vez y después manda su posición. Por eso el lector arrastra
 *   `$cache` por toda la lectura; sin eso, una respuesta con dos apariciones
 *   del mismo campo se lee como basura. Los índices van en base 44 empezando
 *   en el carácter `0` (48 en ASCII), y con dos caracteres a partir del 44.
 *
 * Solo se implementa la LECTURA genérica. Para escribir alcanza con armar el
 * arreglo a mano y pasarlo por `json_encode`: las peticiones que se mandan son
 * dos y su forma es fija.
 */
class Transit
{
    /** Un string se guarda en la caché solo si vale la pena repetirlo. */
    private const PREFIJOS_CACHEABLES = ['~:', '~$', '~#'];

    /**
     * Convierte un cuerpo transit-json en arreglos y escalares de PHP.
     *
     * Los keywords quedan como string sin el `~:` (`:party/email` →
     * `'party/email'`), que es como se usan del otro lado.
     */
    public static function decodificar(string $json): mixed
    {
        $crudo = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $cache = [];

        return self::leer($crudo, $cache);
    }

    private static function leer(mixed $nodo, array &$cache): mixed
    {
        if (is_string($nodo)) {
            return self::leerCadena($nodo, $cache);
        }

        if (! is_array($nodo) || $nodo === []) {
            return $nodo;
        }

        // El primer elemento decide qué es esto, y puede venir cacheado.
        $cabeza = is_string($nodo[0]) ? $nodo[0] : null;

        if ($cabeza === '^ ') {
            return self::leerMapa(array_slice($nodo, 1), $cache);
        }

        $etiqueta = $cabeza !== null ? self::leerCadena($cabeza, $cache) : null;

        if (is_string($etiqueta) && count($nodo) === 2 && self::esEtiqueta($cabeza, $cache)) {
            return self::leerEtiquetado($etiqueta, $nodo[1], $cache);
        }

        return array_map(fn ($e) => self::leer($e, $cache), $nodo);
    }

    /**
     * ¿La cabeza del arreglo es un `~#tag` (o su referencia en caché)?
     *
     * Se mira el string ORIGINAL, no el decodificado: `leerCadena` ya le quitó
     * el `~#`, y un valor legítimo podría coincidir con el nombre de una
     * etiqueta.
     */
    private static function esEtiqueta(?string $original, array $cache): bool
    {
        if ($original === null) {
            return false;
        }

        if (str_starts_with($original, '~#')) {
            return true;
        }

        if (str_starts_with($original, '^') && $original !== '^ ') {
            $i = self::indiceCache(substr($original, 1));

            return isset($cache[$i]) && str_starts_with($cache[$i], '~#');
        }

        return false;
    }

    /** @param  array<int, mixed>  $pares  k1, v1, k2, v2, … */
    private static function leerMapa(array $pares, array &$cache): array
    {
        $mapa = [];

        for ($i = 0; $i + 1 < count($pares); $i += 2) {
            $clave = self::leer($pares[$i], $cache);
            $mapa[is_scalar($clave) ? (string) $clave : json_encode($clave)] = self::leer($pares[$i + 1], $cache);
        }

        return $mapa;
    }

    private static function leerEtiquetado(string $etiqueta, mixed $valor, array &$cache): mixed
    {
        return match ($etiqueta) {
            // Un mapa cuyas claves no son escalares: viaja plano (k1,v1,k2,v2)
            // y no puede volverse un array asociativo de PHP. Se devuelve la
            // lista de pares, que es lo único fiel.
            'cmap' => self::leerPares(is_array($valor) ? $valor : [], $cache),
            'list', 'set' => array_map(fn ($e) => self::leer($e, $cache), is_array($valor) ? $valor : [$valor]),
            default => self::leer($valor, $cache),
        };
    }

    /** @return array<int, array{0: mixed, 1: mixed}> */
    private static function leerPares(array $plano, array &$cache): array
    {
        $pares = [];

        for ($i = 0; $i + 1 < count($plano); $i += 2) {
            $pares[] = [self::leer($plano[$i], $cache), self::leer($plano[$i + 1], $cache)];
        }

        return $pares;
    }

    private static function leerCadena(string $s, array &$cache): mixed
    {
        // Referencia a algo ya visto.
        if (strlen($s) > 1 && $s[0] === '^' && $s !== '^ ') {
            $original = $cache[self::indiceCache(substr($s, 1))] ?? null;

            // Un índice que no está en la caché significa que el lector se
            // desincronizó. Devolver el crudo deja el dato a la vista en vez
            // de inventar uno.
            return $original === null ? $s : self::interpretar($original);
        }

        if (self::esCacheable($s)) {
            $cache[] = $s;
        }

        return self::interpretar($s);
    }

    /** Quita el prefijo de tipo. No cachea: el llamador ya decidió eso. */
    private static function interpretar(string $s): mixed
    {
        if ($s === '' || $s[0] !== '~') {
            return $s;
        }

        $resto = substr($s, 2);

        return match ($s[1] ?? '') {
            ':', '$', 'u', '#' => $resto,   // keyword, símbolo, uuid, etiqueta
            'i' => (int) $resto,
            'd' => (float) $resto,
            '?' => $resto === 't',
            '_' => null,
            '~', '^', '`' => substr($s, 1),  // escapes: el texto empezaba con ~ o ^
            default => $resto,
        };
    }

    private static function esCacheable(string $s): bool
    {
        if (strlen($s) <= 3) {
            return false;
        }

        foreach (self::PREFIJOS_CACHEABLES as $p) {
            if (str_starts_with($s, $p)) {
                return true;
            }
        }

        return false;
    }

    /** Índice en base 44 arrancando en el carácter `0`. */
    private static function indiceCache(string $codigo): int
    {
        if ($codigo === '') {
            return -1;
        }

        if (strlen($codigo) === 1) {
            return ord($codigo) - 48;
        }

        return (ord($codigo[0]) - 48) * 44 + (ord($codigo[1]) - 48);
    }
}
