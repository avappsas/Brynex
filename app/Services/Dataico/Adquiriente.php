<?php

namespace App\Services\Dataico;

/**
 * Quién es el adquiriente de una factura, en una sola forma canónica.
 *
 * Existe para que los dos caminos hacia Dataico —el Excel de importación
 * manual y la emisión por API— no se separen. Son flujos distintos escritos
 * con meses de diferencia, y clasificar al adquiriente de dos maneras es
 * exactamente el tipo de divergencia que termina emitiendo la misma factura
 * con datos distintos según por dónde salga.
 *
 * La regla que importa: `empresas` NO son todas personas jurídicas. En BRYGAR,
 * de 227 registros solo 15 tienen NIT de verdad; 75 llevan una cédula en el
 * campo `nit` y 137 lo tienen vacío. Son empleadores persona natural
 * —contratistas, hogares—, no sociedades. Se clasifica por la FORMA del
 * documento, nunca por estar en esa tabla.
 */
class Adquiriente
{
    /** Identificación con la que la DIAN recibe una venta a consumidor final. */
    public const CONSUMIDOR_FINAL_ID = '222222222222';

    /**
     * Adquiriente de un lote empresarial: el empleador, no el trabajador.
     *
     * @param  bool  $consumidorFinal  si es falso y la empresa no tiene
     *                                 documento, el adquiriente queda marcado
     *                                 con `sin_documento` y sin identificación
     */
    public static function deEmpresa(object $e, bool $consumidorFinal): array
    {
        $doc = self::soloDigitos($e->nit ?? '');

        // La empresa puede tener el interruptor de facturación electrónica
        // apagado: pasa cuando el documento guardado no es suyo. CHOMPAS y
        // TORQUE son establecimientos a nombre de su dueño; DARIO CRUZ tiene
        // la cédula de otra persona. Facturar a ese número sería emitirle a un
        // tercero, así que se trata como si no hubiera documento.
        if (isset($e->factura_electronica) && ! $e->factura_electronica) {
            $doc = '';
        }

        // El nombre que viaja a la DIAN es el del documento, no el del
        // establecimiento: una factura a la cédula de ANCIZAR GARCIA no puede
        // salir a nombre de MAXIDROGAS. `empresa` sigue siendo el nombre con
        // el que se reconoce al cliente en el resto de Brynex.
        $nombre = trim(($e->nombre_legal ?? '') ?: ($e->empresa ?? ''));

        if ($doc === '') {
            return self::sinDocumento($nombre, trim($e->correo ?? ''), $consumidorFinal);
        }

        // El tipo capturado manda sobre la forma del número. La heurística
        // solo cubre las empresas viejas que todavía no tienen `tipo_documento`.
        $tipo = strtoupper(trim((string) ($e->tipo_documento ?? '')));
        $esNit = $tipo !== '' ? $tipo === 'NIT' : self::pareceNitEmpresa($doc);

        return [
            'tipo_persona' => $esNit ? 'PERSONA_JURIDICA' : 'PERSONA_NATURAL',
            'tipo_documento' => $esNit ? 'NIT' : ($tipo !== '' && $tipo !== 'NIT' ? $tipo : 'CC'),
            'identificacion' => $doc,
            'nombre_completo' => $nombre,
            'primer_nombre' => $esNit ? $nombre : self::nombresDe($nombre),
            'apellido' => $esNit ? '' : self::apellidosDe($nombre),
            'direccion' => trim($e->direccion ?? ''),
            'telefono' => trim($e->celular ?: ($e->telefono ?? '')),
            'ciudad' => '',
            'departamento' => '',
            'correo' => trim($e->correo ?? ''),
            'sin_documento' => false,
        ];
    }

    /** Adquiriente de una factura individual: el propio cliente. */
    public static function deCliente(object $cl): array
    {
        $nombres = trim(($cl->primer_nombre ?? '').' '.($cl->segundo_nombre ?? ''));
        $apellidos = trim(($cl->primer_apellido ?? '').' '.($cl->segundo_apellido ?? ''));

        $mapaDoc = ['CC' => 'CC', 'NIT' => 'NIT', 'CE' => 'CE', 'PAS' => 'PASAPORTE', 'TI' => 'TI'];
        $tipoDoc = strtoupper(trim($cl->tipo_doc ?? 'CC'));

        return [
            'tipo_persona' => $tipoDoc === 'NIT' ? 'PERSONA_JURIDICA' : 'PERSONA_NATURAL',
            'tipo_documento' => $mapaDoc[$tipoDoc] ?? 'CC',
            'identificacion' => (string) ($cl->cedula ?? ''),
            'nombre_completo' => trim("$nombres $apellidos"),
            'primer_nombre' => $nombres,
            'apellido' => $apellidos,
            'direccion' => trim($cl->direccion_vivienda ?? ''),
            'telefono' => trim($cl->celular ?: ($cl->telefono ?? '')),
            'ciudad' => trim($cl->ciudad_nombre ?? ''),
            'departamento' => trim($cl->departamento_nombre ?? ''),
            'correo' => trim($cl->correo ?? ''),
            'sin_documento' => false,
        ];
    }

    /**
     * Adquiriente sin documento utilizable.
     *
     * Se conserva el nombre real aunque la identificación sea la genérica: así
     * la factura sigue diciendo de quién es. Emitir a consumidor final es una
     * decisión del dueño (24-ago-2026) para las empresas cuyo documento no se
     * pudo conseguir; con el interruptor apagado el grupo se retiene.
     */
    public static function sinDocumento(string $nombre, string $correo, bool $consumidorFinal): array
    {
        return [
            'tipo_persona' => 'PERSONA_NATURAL',
            'tipo_documento' => 'CC',
            'identificacion' => $consumidorFinal ? self::CONSUMIDOR_FINAL_ID : '',
            'nombre_completo' => $nombre !== '' ? $nombre : 'Consumidor final',
            'primer_nombre' => self::nombresDe($nombre),
            'apellido' => self::apellidosDe($nombre),
            'direccion' => '',
            'telefono' => '',
            'ciudad' => '',
            'departamento' => '',
            'correo' => $correo,
            'sin_documento' => true,
        ];
    }

    /**
     * ¿La empresa aporta un documento con el que facturarle?
     *
     * Sirve para decidir si el adquiriente es el empleador o el propio
     * cliente. Hay filas en `empresas` que son comodines, no empleadores:
     * la id 1 de BRYGAR se llama literalmente «Individual» y marca las
     * facturas de clientes sin empresa. Facturarle a eso como si fuera una
     * sociedad pierde la cédula real del cliente.
     */
    public static function empresaTieneDocumento(object $e): bool
    {
        return self::soloDigitos($e->nit ?? '') !== '';
    }

    /**
     * NIT de sociedad: 9 o 10 dígitos empezando en 8 o 9. Todo lo demás en el
     * campo `nit` de `empresas` es en la práctica una cédula.
     */
    public static function pareceNitEmpresa(string $doc): bool
    {
        return strlen($doc) >= 9
            && strlen($doc) <= 10
            && in_array($doc[0], ['8', '9'], true);
    }

    /** Reparto ingenuo de un nombre suelto: las 2 últimas palabras son apellidos. */
    public static function nombresDe(string $completo): string
    {
        $p = self::palabras($completo);

        return count($p) <= 2 ? ($p[0] ?? '') : implode(' ', array_slice($p, 0, count($p) - 2));
    }

    public static function apellidosDe(string $completo): string
    {
        $p = self::palabras($completo);

        return count($p) <= 2 ? ($p[1] ?? '') : implode(' ', array_slice($p, -2));
    }

    private static function palabras(string $s): array
    {
        return preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function soloDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }
}
