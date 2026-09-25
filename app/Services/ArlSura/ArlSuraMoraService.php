<?php

namespace App\Services\ArlSura;

use App\Models\Tarea;
use App\Services\EpsPortal\CruceAportes;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Los trabajadores que ARL Sura tiene afiliados y sin pago, vueltos tareas.
 *
 * La consulta vive en el portal viejo (`enriques.consulta.sl`), se pide por mes
 * y en formato HTML para no descargar archivos. Devuelve una fila por persona,
 * con su sucursal y centro de trabajo, sin valores: la ARL dice quién no está
 * pagado, no cuánto.
 *
 * Como en las EPS, lo que hay que hacer sale de cruzarlo con BryNex: si la
 * planilla de ese mes existe, el problema es que no le llegó; si la persona
 * está retirada, lo que falta es reportar el retiro; y si sigue vigente sin
 * planilla, entonces sí falta pagar.
 */
class ArlSuraMoraService
{
    public const ENTIDAD = 'ARL SURA';

    private const PREFIJO = 'arlsura:mora';

    /** La consulta del portal viejo, con la errata y todo. */
    private const CONSULTA = 'enriques.consulta.sl';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @param  int  $meses  cuántos meses exigibles hacia atrás
     * @return array{ok:bool, error?:string, nit?:string, nuevas?:int, cerradas?:int, detalle?:array}
     */
    public function revisar(string $nit, string $poliza, bool $simular = false, int $meses = 3): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $credencial = ArlSuraSesionService::credencialPara(0, $poliza, $nit);

        if (! $credencial?->exists) {
            return ['ok' => false, 'error' => "La empresa {$nit} no tiene usuario del portal de SURA.", 'nit' => $nit];
        }

        $periodos = $this->periodos($meses);
        $salida = $this->consultar($credencial, $nit, $periodos);

        if (! ($salida['ok'] ?? false)) {
            Log::warning('ARL Sura: no se pudo leer la mora', ['nit' => $nit, 'error' => $salida['error'] ?? null]);

            return ['ok' => false, 'error' => $salida['error'] ?? 'El portal no respondió.', 'nit' => $nit];
        }

        // Una entrada por persona, con todos los meses en que salió.
        $casos = [];

        foreach ($salida['resultados'] ?? [] as $periodo => $tablas) {
            foreach (self::documentosDe($tablas) as $documento => $nombre) {
                $casos[$documento] ??= ['documento' => $documento, 'nombre' => $nombre, 'periodos' => []];
                $casos[$documento]['periodos'][] = $periodo;
            }
        }

        $detalle = [];
        $nuevas = 0;
        $vistas = [];

        foreach ($casos as $caso) {
            $analisis = $this->analizar($nit, $caso);
            $fin = count($detalle);
            $detalle[] = $analisis;

            if (! $analisis['aliado_id']) {
                $detalle[$fin]['accion'] = 'sin_aliado';

                continue;
            }

            $llave = self::PREFIJO.":{$nit}:{$caso['documento']}";
            $vistas[] = $llave;

            if ($ya = $this->tareas->activaPorLlave($analisis['aliado_id'], $llave)) {
                $detalle[$fin]['accion'] = 'ya_existe';

                if (! $simular && trim((string) $ya->observacion) !== trim($analisis['observacion'])) {
                    $this->tareas->anotar($ya, '🤖 '.$analisis['observacion'], 'nota');
                    $detalle[$fin]['accion'] = 'anotada';
                }

                continue;
            }

            if ($simular) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abriria';

                continue;
            }

            $tarea = $this->tareas->abrir([
                'aliado_id' => $analisis['aliado_id'],
                'tipo' => 'mora_eps',
                'cedula' => $caso['documento'],
                'contrato_id' => $analisis['contrato_id'],
                'razon_social_id' => $analisis['razon_social_id'],
                'entidad' => self::ENTIDAD,
                'tarea' => $analisis['tarea'],
                'observacion' => $analisis['observacion'],
                'llave_auto' => $llave,
            ]);

            if ($tarea) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abierta';
            }
        }

        return [
            'ok' => true,
            'nit' => $nit,
            'periodos' => $periodos,
            'nuevas' => $nuevas,
            'cerradas' => $this->cerrarResueltas($nit, $vistas, $simular, $detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Los documentos de las tablas del informe.
     *
     * Las filas útiles tienen cinco columnas y el documento con la letra del
     * tipo delante (`C31859394`); el resto son encabezados y títulos de sucursal.
     *
     * @return array<string, string> documento => nombre
     */
    public static function documentosDe(array $tablas): array
    {
        $salida = [];

        foreach ($tablas as $filas) {
            foreach ($filas as $fila) {
                if (count($fila) < 5) {
                    continue;
                }

                $documento = ltrim(preg_replace('/\D/', '', (string) ($fila[3] ?? '')), '0');
                $nombre = trim((string) ($fila[4] ?? ''));

                if (strlen($documento) >= 5 && $nombre !== '' && ! preg_match('/documento/i', $nombre)) {
                    $salida[$documento] = $nombre;
                }
            }
        }

        return $salida;
    }

    /** Qué hay que hacer con este trabajador, según lo que BryNex sepa de él. */
    private function analizar(string $nit, array $caso): array
    {
        $meses = collect($caso['periodos'])->map(fn ($p) => CruceAportes::mesEnLetras($p))->implode(', ');
        $contrato = CruceAportes::contratoDe($nit, $caso['documento']);
        $planos = CruceAportes::planosDe($caso['documento'], $caso['periodos']);
        $aqui = $planos->firstWhere('nit', $nit);
        $fuera = $planos->first(fn ($p) => $p->nit !== $nit);

        $base = [
            'documento' => $caso['documento'],
            'nombre' => $caso['nombre'],
            'periodos' => $caso['periodos'],
            'aliado_id' => $contrato?->aliado_id ?: CruceAportes::aliadoDe($nit),
            'contrato_id' => $contrato?->id,
            'razon_social_id' => $contrato?->razon_social_id ?: CruceAportes::razonSocialDe($nit),
        ];

        if ($aqui) {
            $pago = CruceAportes::pagoDe($aqui->numero_planilla);

            return $base + ($pago
                ? [
                    'causa' => 'planilla_pagada',
                    'tarea' => "Enviar a ARL Sura el soporte de pago de {$caso['nombre']}: lo reporta sin pago en {$meses} y esa planilla ya está pagada.",
                    'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses}. En BryNex ".CruceAportes::etiquetaPlanilla($aqui->numero_planilla)
                        ." se pagó el {$pago}. Enviar el soporte para que lo apliquen.",
                ]
                : [
                    'causa' => 'planilla_sin_pago',
                    'tarea' => 'Confirmar el pago de '.CruceAportes::etiquetaPlanilla($aqui->numero_planilla).": ARL Sura reporta a {$caso['nombre']} sin pago en {$meses}.",
                    'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses}. Está en BryNex ".CruceAportes::etiquetaPlanilla($aqui->numero_planilla)
                        .', pero sin pago registrado: confirmar si se pagó y enviar el soporte, o pagarla.',
                ]);
        }

        if ($fuera) {
            return $base + [
                'causa' => 'otra_empresa',
                'tarea' => "Revisar la ARL de {$caso['nombre']}: Sura lo reporta sin pago aquí, pero cotizó por {$fuera->razon_social}.",
                'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses} en la póliza de este NIT, pero las planillas de esos meses salieron por "
                    ."{$fuera->razon_social} (NIT {$fuera->nit}). Si ya no trabaja aquí, hay que retirarlo de esta póliza.",
            ];
        }

        if ($contrato?->fecha_retiro) {
            $retiro = Carbon::parse($contrato->fecha_retiro)->format('d/m/Y');

            return $base + [
                'causa' => 'retiro_no_reportado',
                'tarea' => "Retirar de ARL Sura a {$caso['nombre']}: está retirado desde el {$retiro} y lo siguen cobrando.",
                'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses}, pero en BryNex el contrato está retirado desde el {$retiro}. "
                    .'Retirarlo de la póliza para que dejen de cobrar su cobertura.',
            ];
        }

        if (! $contrato) {
            return $base + [
                'causa' => 'sin_contrato',
                'tarea' => "Revisar la afiliación de {$caso['nombre']} en ARL Sura: no tiene contrato en esta empresa.",
                'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses} en la póliza de este NIT, pero en BryNex no tiene contrato aquí.",
            ];
        }

        return $base + [
            'causa' => 'falta_pagar',
            'tarea' => "Revisar y pagar la ARL de {$caso['nombre']}: Sura lo reporta sin pago en {$meses} y no hay planilla en BryNex.",
            'observacion' => "ARL Sura lo reporta afiliado y sin pago en {$meses}. El contrato sigue vigente y no hay planilla de esos meses en BryNex.",
        ];
    }

    /** Los meses a mirar, del último exigible hacia atrás. */
    private function periodos(int $meses): array
    {
        $ultimo = Carbon::parse(CruceAportes::ultimoPeriodoExigible().'-01');

        return collect(range(0, max(1, $meses) - 1))
            ->map(fn ($i) => $ultimo->copy()->subMonths($i)->format('Y-m'))
            ->all();
    }

    /** Lanza el Chrome que pide el informe. Las claves van por stdin. */
    private function consultar($credencial, string $nit, array $periodos): array
    {
        $resultado = Process::path(base_path())
            // Login ~1 min y cada mes su consulta; con holgura.
            ->timeout(180 + 90 * count($periodos))
            ->input(json_encode([
                'tipoDocumento' => $credencial->tipo_documento,
                'usuario' => $credencial->usuario,
                'contrasena' => $credencial->contrasena,
                'nitEmpresa' => $nit,
                'consulta' => self::CONSULTA,
                'periodos' => $periodos,
            ], JSON_UNESCAPED_UNICODE))
            ->run(ArlSuraSesionService::binarioNode().' scripts/arl-sura-menu.mjs');

        $salida = json_decode(trim($resultado->output()), true);

        return is_array($salida)
            ? $salida
            : ['ok' => false, 'error' => mb_substr(trim($resultado->errorOutput()) ?: 'El portal no respondió.', 0, 300)];
    }

    private function cerrarResueltas(string $nit, array $vistas, bool $simular, array &$detalle): int
    {
        $cerradas = 0;

        foreach (Tarea::whereNotNull('llave_auto')->where('llave_auto', 'like', self::PREFIJO.":{$nit}:%")
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)->get() as $tarea) {
            if (in_array($tarea->llave_auto, $vistas, true)) {
                continue;
            }

            if ($simular) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerraria'];

                continue;
            }

            if ($this->tareas->cerrar($tarea, 'ARL Sura ya no lo reporta sin pago en los períodos exigibles el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada'];
            }
        }

        return $cerradas;
    }
}
