<?php

namespace App\Services\Marketing;

use App\Models\ConsentimientoDato;
use App\Models\Contrato;
use App\Models\MarketingAplazado;
use App\Models\WhatsappEnvioMasivo;
use App\Models\WhatsappEnvioMasivoDetalle;
use Illuminate\Support\Collection;

/**
 * Quién entra a la campaña de reactivación y quién no.
 *
 * Vive aquí y no dentro del comando porque el panel muestra los mismos números que después se
 * envían: si cada uno los calculara por su lado, el día que cambie un criterio el informe
 * diría una cosa y el envío haría otra.
 */
class CandidatosReactivacion
{
    public const DIAS_DESDE = 31;
    public const DIAS_HASTA = 90;

    /**
     * Retirados en la ventana que ya no son clientes.
     *
     * "Sigue siendo cliente" se decide por ESTADO y no por fecha_retiro: hay miles de
     * contratos marcados 'retirado' a los que nunca se les puso la fecha, y filtrar por
     * `fecha_retiro IS NULL` los daría por vigentes, dejando fuera a gente que sí se fue.
     * Se excluye también a quien tenga un contrato POR EMPEZAR: ya volvió.
     */
    public static function candidatos(int $aliadoId, int $desde = self::DIAS_DESDE, int $hasta = self::DIAS_HASTA): Collection
    {
        return Contrato::query()
            ->where('contratos.aliado_id', $aliadoId)
            ->whereNotNull('contratos.fecha_retiro')
            ->whereBetween('contratos.fecha_retiro', [
                now()->subDays($hasta)->toDateString(),
                now()->subDays($desde)->toDateString(),
            ])
            ->whereNotExists(function ($q) use ($aliadoId) {
                $q->selectRaw('1')
                  ->from('contratos as otros')
                  ->whereColumn('otros.cedula', 'contratos.cedula')
                  ->where('otros.aliado_id', $aliadoId)
                  ->where(function ($w) {
                      $w->where('otros.estado', 'vigente')
                        ->orWhere('otros.fecha_ingreso', '>', now()->toDateString());
                  });
            })
            // El vínculo con el cliente es por CÉDULA, no por un id — ver Contrato::cliente().
            ->join('clientes', 'clientes.cedula', '=', 'contratos.cedula')
            // Solo el PRIMER NOMBRE: en la base están en mayúsculas y con apellido, y "Hola
            // NOLVIA LOANGO" se lee a carta de cobranza. El saludo se capitaliza abajo.
            ->selectRaw("contratos.id as contrato_id, contratos.cedula, contratos.fecha_retiro,
                         LTRIM(RTRIM(clientes.primer_nombre)) as nombre,
                         COALESCE(clientes.celular, clientes.telefono) as telefono,
                         DATEDIFF(day, contratos.fecha_retiro, GETDATE()) as dias")
            ->where(function ($q) {
                $q->whereNotNull('clientes.celular')->orWhereNotNull('clientes.telefono');
            })
            ->orderBy('contratos.fecha_retiro', 'desc')
            ->get()
            // Una persona pudo retirar varios contratos: se le escribe UNA vez.
            ->unique('cedula')
            ->map(function ($c) {
                $c->nombre = mb_convert_case(mb_strtolower(trim((string) $c->nombre), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                return $c;
            })
            ->values();
    }

    /**
     * Los que de verdad se pueden contactar hoy, con el detalle de por qué se cayó cada uno.
     *
     * @return array{elegibles: Collection, candidatos: int, sin_consentimiento: int, aplazados: int, ya_enviados: int}
     */
    public static function elegibles(int $aliadoId, int $desde = self::DIAS_DESDE, int $hasta = self::DIAS_HASTA): array
    {
        $candidatos = self::candidatos($aliadoId, $desde, $hasta);
        $telefonos = $candidatos->pluck('telefono')->all();

        $contactables = ConsentimientoDato::filtrarContactables($aliadoId, $telefonos)['contactables'] ?? [];
        $sinConsentimiento = $candidatos->count() - count($contactables);

        // Quien contestó "por ahora no" queda fuera hasta que venza su aplazamiento: volver a
        // escribirle antes es lo que convierte un "todavía no" en una baja de verdad.
        $aplazados = MarketingAplazado::vigentesDe($aliadoId, $contactables);
        $contactables = array_values(array_diff($contactables, $aplazados));

        // A quien ya se le escribió ESTE MES no se le vuelve a escribir. No es un bloqueo
        // permanente: quien dejó el mensaje en visto puede estar en otro momento el mes que
        // viene. Insistir dentro del mismo mes es lo que se siente acoso.
        $yaEnviados = self::contactadosEsteMes($aliadoId, $contactables);
        $contactables = array_values(array_diff($contactables, $yaEnviados));

        $mapa = array_flip($contactables);
        $elegibles = $candidatos->filter(
            fn ($c) => isset($mapa[ConsentimientoDato::normalizarTelefono($c->telefono)])
        )->values();

        return [
            'elegibles'          => $elegibles,
            'candidatos'         => $candidatos->count(),
            'sin_consentimiento' => $sinConsentimiento,
            'aplazados'          => count($aplazados),
            'ya_enviados'        => count($yaEnviados),
        ];
    }

    /** Teléfonos a los que ya se les mandó reactivación dentro del mes en curso. */
    public static function contactadosEsteMes(int $aliadoId, array $telefonos): array
    {
        if (!$telefonos) {
            return [];
        }

        $envios = WhatsappEnvioMasivo::where('aliado_id', $aliadoId)
            ->where('tipo_envio', 'reactivacion')
            ->where('created_at', '>=', now('America/Bogota')->startOfMonth())
            ->pluck('id');

        if ($envios->isEmpty()) {
            return [];
        }

        $numeros = WhatsappEnvioMasivoDetalle::whereIn('envio_id', $envios)
            ->pluck('wa_numero')
            ->map(fn ($n) => ConsentimientoDato::normalizarTelefono($n))
            ->filter()
            ->unique()
            ->all();

        return array_values(array_intersect($telefonos, $numeros));
    }
}
