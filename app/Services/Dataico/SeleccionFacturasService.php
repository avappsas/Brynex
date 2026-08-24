<?php

namespace App\Services\Dataico;

use App\Models\DataicoConfiguracion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decide QUÉ se factura electrónicamente.
 *
 * La regla de negocio, decidida el 24-ago-2026 para BRYGAR:
 *   se emite lo que entró por la cuenta bancaria de la razón social emisora.
 *
 * Y no por la razón social de la factura. `facturas.razon_social_id` guarda la
 * razón social por la que está AFILIADO el cliente (la de la planilla PILA) —
 * en BRYGAR eso es ELITES CREACIONES, WORK AT HOME, CONSTRUTECH… Filtrar por
 * ahí dejaría fuera el 97% de lo que realmente cobra BRYGAR SAS.
 *
 * Consecuencia consciente: los pagos en efectivo puro no tienen consignación,
 * así que no caen en ninguna cuenta y NO se emiten. Son ~111 grupos y $11.6M
 * de admón+afiliación por trimestre. Está así por decisión del dueño.
 */
class SeleccionFacturasService
{
    /** Estados de factura que se consideran facturables. */
    private const ESTADOS = ['pagada', 'abono', 'prestamo'];

    /**
     * Grupos de `numero_factura` listos para emitir bajo esta configuración.
     *
     * @param  int|null  $numeroFactura  limita a un solo grupo (modo 'factura')
     */
    public function pendientes(DataicoConfiguracion $cfg, ?int $numeroFactura = null, ?int $limite = null): Collection
    {
        return $this->clasificar($cfg, $numeroFactura, $limite)['emitibles'];
    }

    /**
     * Separa lo que se puede emitir de lo que no.
     *
     * `sin_documento` son grupos cuyo adquiriente no tiene cédula ni NIT en
     * Brynex — en la práctica, empresas creadas solo con el nombre del
     * empleador.
     *
     * Qué se hace con ellos lo decide `consumidor_final` en la configuración.
     * Apagado (por defecto) quedan retenidos y listados, porque en las 1.128
     * facturas que BRYGAR ya tiene ante la DIAN no hay una sola con documento
     * `222222222222`. Encendido salen a consumidor final, que es la salida
     * elegida por el dueño para las empresas cuyo documento no se pudo
     * conseguir.
     *
     * @return array{emitibles: Collection, sin_documento: Collection}
     */
    public function clasificar(DataicoConfiguracion $cfg, ?int $numeroFactura = null, ?int $limite = null): array
    {
        $grupos = $this->queryGrupos($cfg, $numeroFactura, $limite)->get();

        if ($grupos->isEmpty()) {
            return ['emitibles' => collect(), 'sin_documento' => collect()];
        }

        $hidratados = $this->hydratarAdquirientes($cfg, $grupos);

        // Con el interruptor encendido, la falta de documento deja de ser
        // motivo de retención: el grupo sale a consumidor final.
        if ($cfg->consumidor_final) {
            return ['emitibles' => $hidratados->values(), 'sin_documento' => collect()];
        }

        return [
            'emitibles' => $hidratados->reject(fn ($g) => $g->adquiriente['sin_documento'])->values(),
            'sin_documento' => $hidratados->filter(fn ($g) => $g->adquiriente['sin_documento'])->values(),
        ];
    }

    /** Grupos retenidos por falta de documento del adquiriente. */
    public function sinDocumento(DataicoConfiguracion $cfg): Collection
    {
        return $this->clasificar($cfg)['sin_documento'];
    }

    /** ¿Este grupo concreto califica? Lo usa el disparo por factura. */
    public function calificaGrupo(DataicoConfiguracion $cfg, int $numeroFactura): bool
    {
        return $this->queryGrupos($cfg, $numeroFactura)->get()->isNotEmpty();
    }

    // ─── Query base ──────────────────────────────────────────────────────

    private function queryGrupos(DataicoConfiguracion $cfg, ?int $numeroFactura, ?int $limite = null)
    {
        $q = DB::table('facturas as f')
            ->where('f.aliado_id', $cfg->aliado_id)
            ->whereNull('f.deleted_at')
            ->whereIn('f.estado', self::ESTADOS)
            ->whereDate('f.fecha_pago', '>=', $cfg->fecha_inicio->toDateString())
            ->groupBy('f.numero_factura')
            ->orderBy('f.numero_factura')
            ->select([
                'f.numero_factura',
                DB::raw('COUNT(*)             AS num_clientes'),
                DB::raw('MIN(f.mes)           AS mes'),
                DB::raw('MIN(f.anio)          AS anio'),
                DB::raw('CONVERT(varchar(10), MIN(f.fecha_pago), 23) AS fecha_pago'),
                DB::raw('MIN(f.empresa_id)    AS empresa_id'),
                DB::raw('MIN(f.tipo)          AS tipo'),
                DB::raw('MIN(f.cedula)        AS cedula_muestra'),
                // El único valor que se factura: administración + afiliación.
                // Seguridad social, mora, seguro y mensajería quedan fuera por
                // decisión del dueño — BRYGAR solo factura su servicio.
                DB::raw('SUM(ISNULL(CAST(f.admon      AS BIGINT), 0)
                           + ISNULL(CAST(f.afiliacion AS BIGINT), 0)) AS base_admon'),
                DB::raw('MIN(f.razon_social_id) AS razon_social_afiliacion_id'),
            ])
            // Solo lo que entró por la cuenta de la razón social emisora.
            ->whereExists(function ($sub) use ($cfg) {
                $sub->select(DB::raw(1))
                    ->from('consignaciones as cs')
                    ->join('facturas as sf', 'sf.id', '=', 'cs.factura_id')
                    ->whereColumn('sf.numero_factura', 'f.numero_factura')
                    ->where('sf.aliado_id', $cfg->aliado_id)
                    ->where('cs.banco_cuenta_id', $cfg->banco_cuenta_id);
            })
            // Nunca pisar lo que ya se subió a mano con el Excel del módulo
            // viejo: `fe_marcada` es la marca que dejaba ese flujo.
            ->havingRaw('MAX(CAST(f.fe_marcada AS INT)) = 0')
            // Una factura de $0 de administración no tiene qué facturar.
            ->havingRaw('SUM(ISNULL(CAST(f.admon AS BIGINT), 0)
                           + ISNULL(CAST(f.afiliacion AS BIGINT), 0)) > 0')
            // Un grupo tiene que tener UN solo adquiriente.
            //
            // La factura de un lote empresarial sale a nombre de la empresa por
            // la suma de todos sus afiliados, no una por trabajador. Eso exige
            // que todas las filas del `numero_factura` apunten a la misma
            // empresa. Hay 4 grupos en toda la historia de BRYGAR donde no es
            // así (filas sueltas que quedaron pegadas a un número ajeno, con
            // otra fecha de pago); emitirlos cargaría a esa empresa plata que
            // no es suya. Se dejan fuera y se listan aparte con
            // `gruposAmbiguos()` en vez de facturarse mal.
            ->havingRaw("COUNT(DISTINCT ISNULL(CAST(f.empresa_id AS VARCHAR(20)), 'X')) = 1");

        if ($numeroFactura !== null) {
            $q->where('f.numero_factura', $numeroFactura);
        }

        // Idempotencia: lo ya emitido (o en vuelo) no vuelve a salir.
        //
        // Los que quedaron en `error` sí reaparecen y se reintentan, pero solo
        // hasta `dataico.max_intentos`. Sin ese tope, una factura con un dato
        // que ningún reintento va a arreglar (una ciudad que la DIAN no
        // reconoce, un documento inválido) se reenviaría cada hora para
        // siempre. Agotado el tope se queda quieta esperando que alguien
        // corrija el dato y la reintente desde la pantalla.
        $maxIntentos = (int) config('dataico.max_intentos');

        $q->whereNotExists(function ($sub) use ($cfg, $maxIntentos) {
            $sub->select(DB::raw(1))
                ->from('dataico_envios as de')
                ->whereColumn('de.numero_factura', 'f.numero_factura')
                ->where('de.aliado_id', $cfg->aliado_id)
                ->where(function ($w) use ($maxIntentos) {
                    $w->whereIn('de.estado', ['enviado', 'enviando', 'omitido'])
                        ->orWhere(fn ($x) => $x->where('de.estado', 'error')
                            ->where('de.intentos', '>=', $maxIntentos));
                });
        });

        if ($limite !== null) {
            $q->limit($limite);
        }

        return $q;
    }

    /**
     * Grupos que quedan fuera por tener más de un adquiriente posible.
     *
     * No es una rareza teórica: son facturas reales cuyo `numero_factura`
     * agrupa filas de empresas distintas. Se listan para que alguien las
     * arregle o las facture a mano, porque en silencio serían plata cobrada a
     * quien no corresponde.
     */
    public function gruposAmbiguos(DataicoConfiguracion $cfg): Collection
    {
        return collect(DB::select(
            "SELECT f.numero_factura,
                    COUNT(*) AS filas,
                    COUNT(DISTINCT ISNULL(CAST(f.empresa_id AS VARCHAR(20)), 'X')) AS adquirientes,
                    SUM(ISNULL(CAST(f.admon AS BIGINT), 0)
                      + ISNULL(CAST(f.afiliacion AS BIGINT), 0)) AS base_admon
             FROM facturas f
             WHERE f.aliado_id = ?
               AND f.deleted_at IS NULL
               AND f.estado IN ('pagada','abono','prestamo')
               AND f.fecha_pago >= ?
             GROUP BY f.numero_factura
             HAVING COUNT(DISTINCT ISNULL(CAST(f.empresa_id AS VARCHAR(20)), 'X')) > 1
                AND SUM(ISNULL(CAST(f.admon AS BIGINT), 0)
                      + ISNULL(CAST(f.afiliacion AS BIGINT), 0)) > 0",
            [$cfg->aliado_id, $cfg->fecha_inicio->toDateString()]
        ));
    }

    // ─── Adquirientes ────────────────────────────────────────────────────

    /**
     * Adjunta a cada grupo los datos del adquiriente: la empresa si la factura
     * es de un lote empresarial, el cliente si es individual.
     */
    /** Identificación con la que la DIAN recibe una venta a consumidor final. */
    public const CONSUMIDOR_FINAL_ID = '222222222222';

    private function hydratarAdquirientes(DataicoConfiguracion $cfg, Collection $grupos): Collection
    {
        $cedulas = $grupos->pluck('cedula_muestra')->filter()->unique()->values()->all();
        $empresaIds = $grupos->pluck('empresa_id')->filter()->unique()->values()->all();

        $clientes = [];
        if (! empty($cedulas)) {
            $clientes = DB::table('clientes as cl')
                ->where('cl.aliado_id', $cfg->aliado_id)
                ->whereIn('cl.cedula', $cedulas)
                ->leftJoin('ciudades as ci', 'ci.id', '=', 'cl.municipio_id')
                ->leftJoin('departamentos as de', 'de.id', '=', 'cl.departamento_id')
                ->select([
                    'cl.cedula', 'cl.tipo_doc',
                    'cl.primer_nombre', 'cl.segundo_nombre',
                    'cl.primer_apellido', 'cl.segundo_apellido',
                    'cl.direccion_vivienda', 'cl.celular', 'cl.telefono', 'cl.correo',
                    DB::raw('ci.nombre as ciudad_nombre'),
                    DB::raw('de.nombre as departamento_nombre'),
                ])
                ->get()
                ->keyBy('cedula')
                ->all();
        }

        $empresas = [];
        if (! empty($empresaIds)) {
            $empresas = DB::table('empresas')
                ->whereIn('id', $empresaIds)
                ->select(['id', 'empresa', 'nit', 'contacto', 'telefono', 'celular', 'correo', 'direccion'])
                ->get()
                ->keyBy('id')
                ->all();
        }

        return $grupos->map(function ($g) use ($cfg, $clientes, $empresas) {
            $g->adquiriente = $this->resolverAdquiriente($cfg, $g, $clientes, $empresas);

            return $g;
        });
    }

    /**
     * Quién es el adquiriente de la factura.
     *
     * Ojo con `empresas` en BRYGAR: de 227 registros, solo 15 tienen un NIT de
     * verdad. 75 llevan una cédula en el campo `nit` y 137 lo tienen vacío —
     * son personas naturales que contratan (contratistas, hogares), no
     * sociedades. Emitirlas como PERSONA_JURIDICA con NIT vacío hace que la
     * DIAN rechace la factura, así que aquí se clasifica por la forma del
     * documento y no por el hecho de estar en la tabla `empresas`.
     *
     * Sin documento utilizable el grupo queda retenido, no se emite: BRYGAR
     * nunca ha usado la figura de consumidor final y el dato que falta lo
     * puede llenar una persona en dos minutos.
     */
    private function resolverAdquiriente(DataicoConfiguracion $cfg, object $g, array $clientes, array $empresas): array
    {
        // Lote empresarial: el adquiriente es el empleador, no el trabajador.
        if ($g->empresa_id && isset($empresas[$g->empresa_id])) {
            $e = $empresas[$g->empresa_id];
            $doc = $this->soloDigitos($e->nit ?? '');

            if ($doc === '') {
                return $this->retenidoSinDocumento($cfg, trim($e->empresa ?? ''), trim($e->correo ?? ''));
            }

            $esNit = $this->pareceNitEmpresa($doc);
            $nombre = trim($e->empresa ?? '');

            return [
                'tipo_persona' => $esNit ? 'PERSONA_JURIDICA' : 'PERSONA_NATURAL',
                'tipo_documento' => $esNit ? 'NIT' : 'CC',
                'identificacion' => $doc,
                'nombre_completo' => $nombre,
                'primer_nombre' => $esNit ? $nombre : $this->nombresDe($nombre),
                'apellido' => $esNit ? '' : $this->apellidosDe($nombre),
                'direccion' => trim($e->direccion ?? ''),
                'telefono' => trim($e->celular ?: ($e->telefono ?? '')),
                'ciudad' => '',
                'departamento' => '',
                'correo' => trim($e->correo ?? ''),
                'sin_documento' => false,
            ];
        }

        $cl = $clientes[$g->cedula_muestra] ?? null;

        if (! $cl) {
            return $this->retenidoSinDocumento($cfg, '', '');
        }

        $nombres = trim(($cl->primer_nombre ?? '').' '.($cl->segundo_nombre ?? ''));
        $apellidos = trim(($cl->primer_apellido ?? '').' '.($cl->segundo_apellido ?? ''));

        $mapaDoc = ['CC' => 'CC', 'NIT' => 'NIT', 'CE' => 'CE', 'PAS' => 'PASAPORTE', 'TI' => 'TI'];
        $tipoDoc = strtoupper(trim($cl->tipo_doc ?? 'CC'));

        return [
            'tipo_persona' => $tipoDoc === 'NIT' ? 'PERSONA_JURIDICA' : 'PERSONA_NATURAL',
            'tipo_documento' => $mapaDoc[$tipoDoc] ?? 'CC',
            'identificacion' => (string) ($cl->cedula ?? $g->cedula_muestra),
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
     * Adquiriente sin documento utilizable. NO se emite: queda retenido para
     * que alguien le capture la cédula o el NIT del empleador.
     *
     * Se conserva el nombre para poder identificarlo en el listado.
     */
    private function retenidoSinDocumento(DataicoConfiguracion $cfg, string $nombre, string $correo): array
    {
        return [
            'tipo_persona' => 'PERSONA_NATURAL',
            'tipo_documento' => 'CC',
            'identificacion' => $cfg->consumidor_final ? self::CONSUMIDOR_FINAL_ID : '',
            'nombre_completo' => $nombre !== '' ? $nombre : 'Consumidor final',
            'primer_nombre' => $this->nombresDe($nombre),
            'apellido' => $this->apellidosDe($nombre),
            'direccion' => '',
            'telefono' => '',
            'ciudad' => '',
            'departamento' => '',
            'correo' => $correo,
            'sin_documento' => true,
        ];
    }

    /**
     * NIT de sociedad: 9 o 10 dígitos empezando en 8 o 9. Todo lo demás en el
     * campo `nit` de `empresas` es en la práctica una cédula.
     */
    private function pareceNitEmpresa(string $doc): bool
    {
        return strlen($doc) >= 9
            && strlen($doc) <= 10
            && in_array($doc[0], ['8', '9'], true);
    }

    /** Reparto ingenuo de un nombre suelto: las 2 últimas palabras son apellidos. */
    private function nombresDe(string $completo): string
    {
        $p = preg_split('/\s+/', trim($completo), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($p) <= 2 ? ($p[0] ?? '') : implode(' ', array_slice($p, 0, count($p) - 2));
    }

    private function apellidosDe(string $completo): string
    {
        $p = preg_split('/\s+/', trim($completo), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($p) <= 2 ? ($p[1] ?? '') : implode(' ', array_slice($p, -2));
    }

    private function soloDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }
}
