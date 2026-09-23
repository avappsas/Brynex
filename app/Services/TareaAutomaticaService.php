<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Tarea;
use App\Models\TareaGestion;
use App\Models\TareaSemaforoConfig;
use Illuminate\Support\Facades\DB;

/**
 * Tareas que abre y cierra un proceso automático, no una persona.
 *
 * El módulo de Tareas nació para trabajarse a mano: el controlador toma el
 * aliado de la sesión y el encargado del formulario, y nada impedía que dos
 * corridas del mismo barrido crearan dos veces la misma tarea. Este servicio
 * pone lo que falta para llamarlo desde un comando:
 *
 *  - **`llave_auto`**: de qué hallazgo nació la tarea. Mientras siga activa, el
 *    mismo hallazgo no vuelve a abrir otra; si se cerró y el problema reaparece,
 *    sí se abre una nueva, que es lo correcto.
 *  - **Encargado**: el del contrato, que es quien ya gestiona a esa persona. Si
 *    el contrato no tiene, cae en el usuario de respaldo.
 *  - **Autor**: el usuario 2 (BryNex), como el resto de lo que corre por comando.
 */
class TareaAutomaticaService
{
    /** A nombre de quién quedan las tareas y gestiones que crea un comando. */
    public const USUARIO_SISTEMA = 2;

    /**
     * Abre una tarea si ese hallazgo no tiene ya una activa.
     *
     * @param  array  $datos  aliado_id, tipo, cedula, tarea, llave_auto y,
     *                        opcionales, contrato_id, razon_social_id, entidad,
     *                        observacion, encargado_id, fecha_radicado,
     *                        numero_radicado, correo.
     * @return Tarea|null la tarea creada, o null si ya existía una activa
     */
    public function abrir(array $datos): ?Tarea
    {
        $aliadoId = (int) $datos['aliado_id'];
        $llave = (string) $datos['llave_auto'];

        if ($this->activaPorLlave($aliadoId, $llave)) {
            return null;
        }

        $contrato = ! empty($datos['contrato_id'])
            ? Contrato::where('aliado_id', $aliadoId)->find($datos['contrato_id'])
            : null;

        $tipo = (string) $datos['tipo'];

        return DB::transaction(function () use ($datos, $aliadoId, $llave, $contrato, $tipo) {
            // Se vuelve a mirar dentro de la transacción: dos corridas a la vez
            // —la nocturna y la que dispara el portal— podrían haber pasado
            // juntas por la comprobación de arriba.
            if ($this->activaPorLlave($aliadoId, $llave)) {
                return null;
            }

            $tarea = Tarea::create([
                'aliado_id' => $aliadoId,
                'tipo' => $tipo,
                'estado' => Tarea::ESTADO_PENDIENTE,
                'cedula' => (string) $datos['cedula'],
                'contrato_id' => $contrato?->id,
                'razon_social_id' => $datos['razon_social_id'] ?? $contrato?->razon_social_id,
                'entidad' => $datos['entidad'] ?? null,
                'tarea' => $datos['tarea'],
                'observacion' => $datos['observacion'] ?? null,
                'encargado_id' => $datos['encargado_id'] ?? $contrato?->encargado_id ?? self::USUARIO_SISTEMA,
                'creado_por' => self::USUARIO_SISTEMA,
                'fecha_limite' => TareaSemaforoConfig::fechaLimiteParaTipo($tipo, $aliadoId),
                'fecha_radicado' => $datos['fecha_radicado'] ?? null,
                'numero_radicado' => $datos['numero_radicado'] ?? null,
                'correo' => $datos['correo'] ?? null,
                'llave_auto' => $llave,
            ]);

            $this->anotar($tarea, '🤖 Tarea creada por la revisión automática: '.$datos['tarea'], 'tramite_realizado', Tarea::ESTADO_PENDIENTE);

            return $tarea;
        });
    }

    /**
     * La tarea activa de ese hallazgo, si la hay.
     */
    public function activaPorLlave(int $aliadoId, string $llave): ?Tarea
    {
        return Tarea::where('aliado_id', $aliadoId)
            ->where('llave_auto', $llave)
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->latest('id')
            ->first();
    }

    /**
     * Cierra la tarea porque el hallazgo desapareció.
     *
     * No toca las que alguien ya cerró a mano ni las de otro aliado.
     */
    public function cerrar(Tarea $tarea, string $observacion, string $resultado = 'positivo'): bool
    {
        if ($tarea->estado === Tarea::ESTADO_CERRADA) {
            return false;
        }

        return (bool) DB::transaction(function () use ($tarea, $observacion, $resultado) {
            $fresca = Tarea::whereKey($tarea->id)->lockForUpdate()->first();

            if (! $fresca || $fresca->estado === Tarea::ESTADO_CERRADA) {
                return false;
            }

            $fresca->update([
                'estado' => Tarea::ESTADO_CERRADA,
                'resultado' => $resultado,
            ]);

            $this->anotar($fresca, '🏁 '.$observacion, 'cambio_estado', Tarea::ESTADO_CERRADA);

            return true;
        });
    }

    /** Deja una nota en la bitácora sin cambiar el estado. */
    public function anotar(Tarea $tarea, string $observacion, string $tipoAccion = 'nota', ?string $estado = null): void
    {
        TareaGestion::create([
            'tarea_id' => $tarea->id,
            'user_id' => self::USUARIO_SISTEMA,
            'tipo_accion' => $tipoAccion,
            'observacion' => mb_substr($observacion, 0, 1000),
            'estado_tarea' => $estado ?? $tarea->estado,
            'created_at' => now(),
        ]);
    }
}
