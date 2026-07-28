<?php

namespace App\Services\Adres;

use App\Models\AdresChequeo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orquesta un chequeo de seguridad social contra ADRES.
 *
 * Son dos pasos con una persona en medio:
 *   1. iniciar()            → abre la consulta y devuelve el captcha
 *   2. resolverCaptcha()    → recibe el texto que alguien leyó y trae el resultado
 *
 * Quien resuelve el captcha puede ser el propio titular por WhatsApp o un
 * operador desde el panel. En ningún caso lo resuelve el sistema: ese control es
 * de ADRES y se respeta.
 */
class ChequeoService
{
    public function __construct(
        protected AdresWorkerClient $worker = new AdresWorkerClient(),
    ) {
    }

    /**
     * Crea el chequeo y abre la sesión contra ADRES.
     *
     * @return array{ok:bool, chequeo?:AdresChequeo, captcha_png?:string, error?:string}
     */
    public function iniciar(
        int $aliadoId,
        string $cedula,
        string $autorizacionTexto,
        ?int $conversacionId = null,
        ?int $solicitadoPor = null,
        string $tipoDocumento = 'Cedula de Ciudadania',
    ): array {
        $cedula = preg_replace('/\D+/', '', $cedula);

        if (!preg_match('/^\d{4,15}$/', (string) $cedula)) {
            return ['ok' => false, 'error' => 'La cédula no tiene un formato válido.'];
        }

        // Sin constancia de autorización no se consulta el dato de nadie.
        if (trim($autorizacionTexto) === '') {
            return ['ok' => false, 'error' => 'Falta registrar la autorización del titular.'];
        }

        $chequeo = AdresChequeo::create([
            'aliado_id'          => $aliadoId,
            'conversacion_id'    => $conversacionId,
            'solicitado_por'     => $solicitadoPor,
            'cedula'             => $cedula,
            'tipo_documento'     => $tipoDocumento,
            'autorizado_at'      => now(),
            'autorizacion_texto' => mb_substr($autorizacionTexto, 0, 500),
            'estado'             => AdresChequeo::ESTADO_PENDIENTE,
        ]);

        $r = $this->worker->abrirConsulta($cedula, $tipoDocumento);

        if (!$r['ok']) {
            $chequeo->update(['estado' => AdresChequeo::ESTADO_FALLIDO, 'error' => mb_substr($r['error'], 0, 500)]);
            return ['ok' => false, 'error' => $r['error'], 'chequeo' => $chequeo];
        }

        $chequeo->update([
            'estado'             => AdresChequeo::ESTADO_ESPERANDO_CAPTCHA,
            'sesion_id'          => $r['sesion_id'],
            'captcha_enviado_at' => now(),
        ]);

        return [
            'ok'          => true,
            'chequeo'     => $chequeo->refresh(),
            'captcha_png' => CaptchaImagen::componer($r['captcha_png']),
        ];
    }

    /**
     * Entrega el texto leído por una persona y cierra la consulta.
     *
     * @return array{ok:bool, chequeo:AdresChequeo, reintentar?:bool, captcha_png?:string, error?:string}
     */
    public function resolverCaptcha(AdresChequeo $chequeo, string $texto): array
    {
        if (!$chequeo->esperaCaptcha()) {
            return ['ok' => false, 'chequeo' => $chequeo, 'error' => 'Este chequeo no está esperando un captcha.'];
        }

        $chequeo->update(['estado' => AdresChequeo::ESTADO_CONSULTANDO, 'intentos' => $chequeo->intentos + 1]);

        $r = $this->worker->responderCaptcha($chequeo->sesion_id, $texto);

        if (!($r['ok'] ?? false)) {
            // El captcha se rota tras cada fallo: hay que reenviar la imagen nueva.
            if (($r['motivo'] ?? null) === 'captcha_incorrecto' && !empty($r['captcha_png'])) {
                $chequeo->update(['estado' => AdresChequeo::ESTADO_ESPERANDO_CAPTCHA, 'captcha_enviado_at' => now()]);

                return [
                    'ok'          => false,
                    'reintentar'  => true,
                    'chequeo'     => $chequeo->refresh(),
                    'captcha_png' => CaptchaImagen::componer($r['captcha_png']),
                ];
            }

            $motivo = match ($r['motivo'] ?? null) {
                'sin_intentos' => 'Se agotaron los intentos de captcha.',
                default        => $r['error'] ?? 'La consulta a ADRES no se pudo completar.',
            };

            $chequeo->update([
                'estado'    => AdresChequeo::ESTADO_FALLIDO,
                'sesion_id' => null,
                'error'     => mb_substr($motivo, 0, 500),
            ]);

            return ['ok' => false, 'chequeo' => $chequeo->refresh(), 'error' => $motivo];
        }

        $filas = $r['filas'] ?? [];

        // El PDF va al disco privado: es información de salud de una persona y no
        // puede quedar en el disco público, que se sirve por URL sin autenticar.
        $rutaPdf = null;
        if (!empty($r['pdf'])) {
            $rutaPdf = "adres/chequeos/{$chequeo->id}.pdf";
            Storage::disk('local')->put($rutaPdf, $r['pdf']);
        }

        $diagnostico = DiagnosticoCompensados::analizar($filas);

        // Si el PDF trajo menos filas de las que declaró la web, el diagnóstico
        // se calculó sobre datos parciales y no se puede presentar como completo.
        if (!($r['completo'] ?? false)) {
            $diagnostico['requiere_asesor'] = true;
            Log::warning('ADRES: extracción incompleta', [
                'chequeo_id' => $chequeo->id,
                'extraidas'  => $r['total_filas'] ?? null,
                'declaradas' => $r['total_declarado'] ?? null,
            ]);
        }

        $chequeo->update([
            'estado'      => AdresChequeo::ESTADO_LISTO,
            'sesion_id'   => null,
            'filas'       => $filas,
            'total_filas' => $r['total_filas'] ?? count($filas),
            'completo'    => (bool) ($r['completo'] ?? false),
            'diagnostico' => $diagnostico,
            'pdf_path'    => $rutaPdf,
            'error'       => null,
        ]);

        return ['ok' => true, 'chequeo' => $chequeo->refresh()];
    }

    /** Abandona un chequeo y libera la sesión del navegador en el worker. */
    public function cancelar(AdresChequeo $chequeo, string $motivo = 'Cancelado'): void
    {
        if ($chequeo->sesion_id) {
            $this->worker->cerrarConsulta($chequeo->sesion_id);
        }

        $chequeo->update([
            'estado'    => AdresChequeo::ESTADO_FALLIDO,
            'sesion_id' => null,
            'error'     => mb_substr($motivo, 0, 500),
        ]);
    }
}
