<?php

namespace App\Services\EpsPortal;

use App\Models\Contrato;
use App\Models\EpsAfiliacion;
use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lo que todo trámite de EPS por portal hace con el radicado de BryNex: dejarlo
 * en el estado que diga la EPS con su número y soporte, anotarlo en la bitácora
 * del radicado y en `eps_afiliaciones`. (Nueva EPS y Salud Total tienen aún su
 * propia copia de esto.)
 */
class EpsRadicado
{
    public static function deContrato(Contrato $contrato): Radicado
    {
        return Radicado::firstOrCreate(
            ['contrato_id' => $contrato->id, 'tipo' => Radicado::TIPO_EPS],
            ['aliado_id' => $contrato->aliado_id, 'estado' => Radicado::ESTADO_PENDIENTE]
        );
    }

    /** Nunca retrocede un radicado que ya está en OK. */
    public static function marcar(Radicado $radicado, ?string $numero, string $nuevo, ?string $rutaPdf, string $observacion, ?int $usuarioId): void
    {
        DB::transaction(function () use ($radicado, $numero, $nuevo, $rutaPdf, $observacion, $usuarioId) {
            $r = Radicado::whereKey($radicado->id)->lockForUpdate()->first();
            $anterior = $r->estado;

            if ($anterior === Radicado::ESTADO_OK) {
                $nuevo = Radicado::ESTADO_OK;
            }

            $r->update([
                'estado'               => $nuevo,
                'numero_radicado'      => $numero ?? $r->numero_radicado,
                'canal_envio'          => 'portal',
                'user_id'              => $usuarioId,
                'fecha_inicio_tramite' => $r->fecha_inicio_tramite ?? now(),
                'fecha_confirmacion'   => $nuevo === Radicado::ESTADO_OK ? ($r->fecha_confirmacion ?? now()) : $r->fecha_confirmacion,
                'ruta_pdf'             => $rutaPdf ?? $r->ruta_pdf,
                'observacion'          => trim(($r->observacion ? $r->observacion.' | ' : '').$observacion),
            ]);

            RadicadoMovimiento::create([
                'radicado_id'     => $r->id,
                'contrato_id'     => $r->contrato_id,
                'tipo_proceso'    => 'afiliacion',
                'entidad'         => Radicado::TIPO_EPS,
                'user_id'         => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo'    => $nuevo,
                'observacion'     => $observacion,
            ]);
        });

        $radicado->refresh();
    }

    /** Guarda un PDF (binario) en la carpeta de radicados del contrato, en el disco local. */
    public static function guardarPdf(Contrato $contrato, ?string $binario, string $prefijo = 'eps'): ?string
    {
        if (! $binario || ! str_starts_with($binario, '%PDF')) {
            return null;
        }

        $ruta = self::carpeta($contrato)."/{$prefijo}_".now()->format('Ymd_His').'_'.Str::random(4).'.pdf';
        Storage::disk('local')->put($ruta, $binario);

        return $ruta;
    }

    public static function carpeta(Contrato $contrato): string
    {
        return "radicados/{$contrato->aliado_id}/{$contrato->id}/{$contrato->cedula}";
    }

    public static function bitacora(Contrato $contrato, Radicado $radicado, string $entidad, string $operacion, string $estado, ?string $numero, array $payload, ?array $respuesta, ?string $error, ?int $usuarioId, ?string $ruta = null): void
    {
        EpsAfiliacion::create([
            'aliado_id'       => $contrato->aliado_id,
            'contrato_id'     => $contrato->id,
            'radicado_id'     => $radicado->id,
            'entidad'         => $entidad,
            'operacion'       => $operacion,
            'estado'          => $estado,
            'numero_radicado' => $numero,
            'payload'         => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'respuesta'       => $respuesta ? json_encode($respuesta, JSON_UNESCAPED_UNICODE) : null,
            'mensaje_error'   => $error ? mb_substr($error, 0, 500) : null,
            'ruta_pdf'        => $ruta,
            'usuario_id'      => $usuarioId,
        ]);
    }
}
