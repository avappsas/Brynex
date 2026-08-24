<?php

namespace App\Services\Dataico;

use App\Models\DataicoConfiguracion;
use App\Models\DataicoEnvio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la emisión: selecciona → arma → envía → deja rastro.
 *
 * Lo delicado aquí es no emitir dos veces la misma factura ante la DIAN. La
 * protección es doble: el índice único (aliado_id, numero_factura) de
 * `dataico_envios`, y un "reclamo" atómico del envío — se pasa la fila a
 * `enviando` con un UPDATE condicional antes de tocar el API, de modo que si
 * el cierre diario y el disparo por factura coinciden, el segundo ve 0 filas
 * afectadas y se retira.
 */
class EmisionService
{
    public function __construct(
        private readonly SeleccionFacturasService $seleccion,
        private readonly PayloadBuilder $builder,
    ) {}

    /**
     * Emite todo lo pendiente de una configuración.
     *
     * @return array{intentadas:int, enviadas:int, errores:int, omitidas:int, detalle:array}
     */
    public function emitirPendientes(DataicoConfiguracion $cfg, ?int $limite = null, bool $simular = false): array
    {
        $limite ??= (int) config('dataico.lote_maximo');
        $grupos = $this->seleccion->pendientes($cfg, null, $limite);

        $resumen = ['intentadas' => 0, 'enviadas' => 0, 'errores' => 0, 'omitidas' => 0, 'detalle' => []];

        foreach ($grupos as $grupo) {
            $r = $this->emitirGrupo($cfg, $grupo, $simular);
            $resumen['intentadas']++;
            $resumen[$r['resultado']]++;
            $resumen['detalle'][] = $r;
        }

        return $resumen;
    }

    /** Emite un grupo concreto. Lo usa el disparo por factura. */
    public function emitirNumeroFactura(DataicoConfiguracion $cfg, int $numeroFactura, bool $simular = false): ?array
    {
        $grupo = $this->seleccion->pendientes($cfg, $numeroFactura)->first();

        if (! $grupo) {
            return null;
        }

        return $this->emitirGrupo($cfg, $grupo, $simular);
    }

    // ─── Emisión de un grupo ─────────────────────────────────────────────

    /**
     * @return array{numero_factura:int, resultado:'enviadas'|'errores'|'omitidas', mensaje:?string, payload:?array}
     */
    public function emitirGrupo(DataicoConfiguracion $cfg, object $grupo, bool $simular = false): array
    {
        $numero = (int) $grupo->numero_factura;
        $payload = $this->builder->construir($cfg, $grupo);

        if ($simular) {
            return [
                'numero_factura' => $numero,
                'resultado' => 'omitidas',
                'mensaje' => 'Simulación: no se envió nada.',
                'payload' => $payload,
            ];
        }

        $envio = $this->reclamar($cfg, $grupo, $payload);

        if ($envio === null) {
            return [
                'numero_factura' => $numero,
                'resultado' => 'omitidas',
                'mensaje' => 'Otro proceso ya la tomó o ya estaba emitida.',
                'payload' => null,
            ];
        }

        $respuesta = (new ApiClient($cfg))->crearFactura($payload);

        if ($respuesta['ok']) {
            $this->marcarEnviado($cfg, $envio, $respuesta);

            return [
                'numero_factura' => $numero,
                'resultado' => 'enviadas',
                'mensaje' => $envio->dataico_numero ?: 'Emitida',
                'payload' => null,
            ];
        }

        $this->marcarError($envio, $respuesta);

        Log::warning('[dataico] factura rechazada', [
            'aliado_id' => $cfg->aliado_id,
            'numero_factura' => $numero,
            'status' => $respuesta['status'],
            'error' => $respuesta['error'],
        ]);

        return [
            'numero_factura' => $numero,
            'resultado' => 'errores',
            'mensaje' => $respuesta['error'],
            'payload' => null,
        ];
    }

    // ─── Reclamo atómico ─────────────────────────────────────────────────

    /**
     * Toma la fila de `dataico_envios` en estado `enviando`, o devuelve null si
     * otro proceso ya la tiene o la factura ya se emitió.
     */
    private function reclamar(DataicoConfiguracion $cfg, object $grupo, array $payload): ?DataicoEnvio
    {
        $numero = (int) $grupo->numero_factura;
        $adq = $grupo->adquiriente;

        $comun = [
            'razon_social_id' => $cfg->razon_social_id,
            'estado' => DataicoEnvio::ESTADO_ENVIANDO,
            'base_admon' => (float) $grupo->base_admon,
            'cliente_identificacion' => $adq['identificacion'],
            'cliente_nombre' => mb_substr($adq['nombre_completo'], 0, 250),
            'es_consumidor_final' => (bool) $adq['sin_documento'],
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'error_mensaje' => null,
            'updated_at' => now(),
        ];

        $existente = DataicoEnvio::where('aliado_id', $cfg->aliado_id)
            ->where('numero_factura', $numero)
            ->first();

        if ($existente) {
            if (in_array($existente->estado, [DataicoEnvio::ESTADO_ENVIADO, DataicoEnvio::ESTADO_ENVIANDO], true)) {
                return null;
            }

            // UPDATE condicional: si otro proceso lo movió entre el SELECT y
            // este UPDATE, afecta 0 filas y nos retiramos.
            $tomadas = DB::table('dataico_envios')
                ->where('id', $existente->id)
                ->whereIn('estado', [DataicoEnvio::ESTADO_ERROR, DataicoEnvio::ESTADO_PENDIENTE])
                ->update($comun + ['intentos' => DB::raw('intentos + 1')]);

            return $tomadas === 1 ? $existente->refresh() : null;
        }

        try {
            return DataicoEnvio::create($comun + [
                'aliado_id' => $cfg->aliado_id,
                'numero_factura' => $numero,
                'intentos' => 1,
            ]);
        } catch (QueryException $e) {
            // Choque contra el índice único: otro proceso la creó primero.
            return null;
        }
    }

    // ─── Cierre del envío ────────────────────────────────────────────────

    private function marcarEnviado(DataicoConfiguracion $cfg, DataicoEnvio $envio, array $respuesta): void
    {
        $body = $respuesta['body'] ?? [];

        $envio->forceFill([
            'estado' => DataicoEnvio::ESTADO_ENVIADO,
            'dataico_uuid' => $this->buscar($body, ['uuid', 'invoice_uuid', 'id']),
            'dataico_numero' => $this->buscar($body, ['number', 'invoice_number', 'numero']),
            'cufe' => $this->buscar($body, ['cufe', 'cude']),
            'respuesta' => mb_substr($respuesta['raw'], 0, 8000),
            'error_mensaje' => null,
            'enviado_at' => now(),
        ])->save();

        // Cierra el círculo con el flujo viejo: `fe_marcada` es lo que impide
        // que estas facturas vuelvan a salir en el Excel de importación manual.
        DB::table('facturas')
            ->where('aliado_id', $cfg->aliado_id)
            ->where('numero_factura', $envio->numero_factura)
            ->whereNull('deleted_at')
            ->update([
                'fe_marcada' => 1,
                'fe_marcada_at' => now(),
            ]);
    }

    private function marcarError(DataicoEnvio $envio, array $respuesta): void
    {
        $envio->forceFill([
            'estado' => DataicoEnvio::ESTADO_ERROR,
            'respuesta' => mb_substr($respuesta['raw'], 0, 8000),
            'error_mensaje' => mb_substr((string) $respuesta['error'], 0, 1000),
        ])->save();
    }

    /** Primera llave presente en la respuesta, buscando también un nivel adentro. */
    private function buscar(array $body, array $llaves): ?string
    {
        foreach ($llaves as $k) {
            if (isset($body[$k]) && is_scalar($body[$k])) {
                return (string) $body[$k];
            }
        }

        foreach (['invoice', 'data', 'result'] as $envoltorio) {
            if (isset($body[$envoltorio]) && is_array($body[$envoltorio])) {
                foreach ($llaves as $k) {
                    if (isset($body[$envoltorio][$k]) && is_scalar($body[$envoltorio][$k])) {
                        return (string) $body[$envoltorio][$k];
                    }
                }
            }
        }

        return null;
    }
}
