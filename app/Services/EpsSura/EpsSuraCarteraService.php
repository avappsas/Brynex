<?php

namespace App\Services\EpsSura;

use App\Models\Tarea;
use App\Services\ArlSura\ArlSuraSesionService;
use App\Services\EpsPortal\CruceAportes;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * El estado de cuenta de EPS SURA, convertido en tareas.
 *
 * SURA no publica una pantalla de morosos: publica un informe —*Consultas →
 * Empresa → Estados de cuenta*— que para un rango de meses lista los períodos
 * en mora de cada cotizante, y si no hay ninguno emite un certificado de no
 * deuda. Llega como CSV separado por `;` y en latin1.
 *
 * Lo que se hace con cada mora sale de cruzarla con BryNex, igual que en las
 * otras EPS: la planilla del mes dice si es deuda de verdad, un cobro que ya se
 * pagó o un retiro que nunca les llegó.
 */
class EpsSuraCarteraService
{
    public const ENTIDAD = 'EPS SURA';

    private const PREFIJO = 'epssura:mora';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @param  int  $meses  cuántos meses hacia atrás, sin contar el actual
     * @return array{ok:bool, error?:string, nit?:string, nuevas?:int, cerradas?:int, detalle?:array}
     */
    public function revisar(string $nit, bool $simular = false, int $meses = 8): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        // El usuario del portal es por empresa, no por aliado: el 0 solo evita
        // que caiga en el usuario "activo" de un aliado, que sería de otra.
        $credencial = ArlSuraSesionService::credencialPara(0, '', $nit);

        if (! $credencial?->exists) {
            return ['ok' => false, 'error' => "La empresa {$nit} no tiene usuario del portal de SURA.", 'nit' => $nit];
        }

        $hasta = now()->startOfMonth()->subMonth();
        $desde = $hasta->copy()->subMonths(max(0, $meses - 1));

        $salida = $this->informe($credencial, $nit, $desde->format('Y-m'), $hasta->format('Y-m'));

        if (! ($salida['ok'] ?? false)) {
            Log::warning('EPS SURA: no se pudo bajar el estado de cuenta', ['nit' => $nit, 'error' => $salida['error'] ?? null]);

            return ['ok' => false, 'error' => $salida['error'] ?? 'El portal no respondió.', 'nit' => $nit];
        }

        // El script deja el archivo en disco y manda la ruta: por la salida del
        // proceso no cabe (se corta a 64 KB).
        $ruta = (string) ($salida['ruta'] ?? '');
        $archivo = is_file($ruta) ? (string) file_get_contents($ruta) : '';

        if ($ruta) {
            @unlink($ruta);
            @rmdir(dirname($ruta));
        }

        if ($archivo === '') {
            return ['ok' => false, 'nit' => $nit, 'error' => 'El informe llegó vacío.'];
        }

        // Sin mora, SURA no manda informe: manda un certificado de no deuda, y
        // eso viene en PDF aunque se pida XLS. Se lee para confirmarlo: dar por
        // limpia una empresa sin mirar el papel sería inventarse el resultado.
        if (($salida['formato'] ?? null) === 'pdf') {
            $texto = self::textoDelPdf($archivo);

            // Así lo redacta SURA: "no presenta saldos pendientes con nuestra
            // entidad por concepto de cotizaciones".
            if (! preg_match('/no\s+presenta\s+saldos?\s+pendientes|no\s+(presenta|registra|tiene)\s+(deuda|mora)|de\s+no\s+deuda/i', $texto)) {
                return [
                    'ok' => false,
                    'nit' => $nit,
                    'error' => 'El portal entregó un PDF que no es certificado de no deuda; hay que revisarlo a mano.',
                ];
            }

            $casos = [];
        } else {
            $casos = self::leerInforme($archivo);
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
                $detalle[$fin]['tarea_id'] = $ya->id;

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
                $detalle[$fin]['tarea_id'] = $tarea->id;
            }
        }

        return [
            'ok' => true,
            'nit' => $nit,
            'nuevas' => $nuevas,
            'cerradas' => $this->cerrarResueltas($nit, $vistas, $simular, $detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Las filas del informe: una por cotizante, con todos sus períodos en mora.
     *
     * El archivo trae encabezado, cotizantes y totales; los cotizantes son las
     * líneas cuya segunda columna es un documento.
     *
     * @return array<int, array{documento:string, nombre:string, periodos:array, valor:int}>
     */
    public static function leerInforme(string $csv): array
    {
        if (! $csv) {
            return [];
        }

        // El portal lo manda en latin1: sin esto los nombres llegan rotos.
        if (! mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1');
        }

        $casos = [];

        foreach (preg_split('/\r\n|\n|\r/', $csv) as $linea) {
            $celdas = array_map('trim', str_getcsv($linea, ';'));

            if (count($celdas) < 5) {
                continue;
            }

            $documento = ltrim(preg_replace('/\D/', '', $celdas[1] ?? ''), '0');
            $periodo = trim($celdas[4] ?? '');

            // '08/2026' → '2026-08'; cualquier otra cosa es encabezado o total.
            if (! $documento || ! preg_match('#^(\d{2})/(\d{4})$#', $periodo, $m)) {
                continue;
            }

            $casos[$documento] ??= [
                'documento' => $documento,
                'nombre' => trim($celdas[2] ?? ''),
                'periodos' => [],
                'valor' => 0,
            ];

            $casos[$documento]['periodos'][] = "{$m[2]}-{$m[1]}";
            // La cotización esperada; en mora es lo que se debe de ese mes.
            $casos[$documento]['valor'] += (int) preg_replace('/\D/', '', $celdas[6] ?? '0');
        }

        return array_values($casos);
    }

    /** El texto de un PDF, para saber qué dice el certificado. */
    private static function textoDelPdf(string $binario): string
    {
        try {
            return (new \Smalot\PdfParser\Parser)->parseContent($binario)->getText();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Qué hay que hacer con este cotizante, según lo que BryNex sepa de él. */
    private function analizar(string $nit, array $caso): array
    {
        $meses = collect($caso['periodos'])->map(fn ($p) => CruceAportes::mesEnLetras($p))->implode(', ');
        $plata = CruceAportes::plata($caso['valor']);
        $contrato = CruceAportes::contratoDe($nit, $caso['documento']);
        $planos = CruceAportes::planosDe($caso['documento'], $caso['periodos']);
        $aqui = $planos->firstWhere('nit', $nit);
        $fuera = $planos->first(fn ($p) => $p->nit !== $nit);

        $base = [
            'documento' => $caso['documento'],
            'nombre' => $caso['nombre'],
            'periodos' => $caso['periodos'],
            'valor' => $caso['valor'],
            'aliado_id' => $contrato?->aliado_id ?: CruceAportes::aliadoDe($nit),
            'contrato_id' => $contrato?->id,
            'razon_social_id' => $contrato?->razon_social_id ?: CruceAportes::razonSocialDe($nit),
        ];

        if ($aqui) {
            $pago = CruceAportes::pagoDe($aqui->numero_planilla);

            return $base + ($pago
                ? [
                    'causa' => 'planilla_pagada',
                    'tarea' => "Enviar a EPS SURA el soporte de pago: cobra mora de {$meses} ({$plata}) y esa planilla ya está pagada.",
                    'observacion' => "EPS SURA reporta mora de {$meses} por {$plata}. En BryNex la planilla {$aqui->numero_planilla} se pagó el {$pago}. "
                        .'Enviar el soporte para que retiren el cobro.',
                ]
                : [
                    'causa' => 'planilla_sin_pago',
                    'tarea' => "Confirmar el pago de la planilla {$aqui->numero_planilla}: EPS SURA cobra mora de {$meses} ({$plata}).",
                    'observacion' => "EPS SURA reporta mora de {$meses} por {$plata}. La planilla {$aqui->numero_planilla} está en BryNex pero sin pago "
                        .'registrado: confirmar si se pagó y enviar el soporte, o pagarla.',
                ]);
        }

        if ($fuera) {
            return $base + [
                'causa' => 'otra_empresa',
                'tarea' => "Revisar la afiliación en EPS SURA: cobra mora de {$meses} ({$plata}) aquí, pero cotizó por {$fuera->razon_social}.",
                'observacion' => "EPS SURA reporta mora de {$meses} por {$plata} en el NIT {$nit}, pero las planillas de esos meses salieron por "
                    ."{$fuera->razon_social} (NIT {$fuera->nit}). Revisar en cuál empresa debe estar afiliado.",
            ];
        }

        if (! $contrato) {
            return $base + [
                'causa' => 'sin_contrato',
                'tarea' => "Revisar la afiliación en EPS SURA: cobra mora de {$meses} ({$plata}) de alguien sin contrato en esta empresa.",
                'observacion' => "EPS SURA reporta mora de {$meses} por {$plata} en el NIT {$nit}, pero en BryNex esta persona no tiene contrato ahí.",
            ];
        }

        if ($contrato->fecha_retiro) {
            $retiro = Carbon::parse($contrato->fecha_retiro)->format('d/m/Y');

            return $base + [
                'causa' => 'retiro_no_reportado',
                'tarea' => "Reportar a EPS SURA el retiro del {$retiro}: cobra mora de {$meses} ({$plata}) de alguien ya retirado.",
                'observacion' => "EPS SURA reporta mora de {$meses} por {$plata}. En BryNex el contrato está retirado desde el {$retiro}: "
                    .'reportar el retiro para que anulen el cobro.',
            ];
        }

        return $base + [
            'causa' => 'falta_pagar',
            'tarea' => "Revisar y pagar el aporte: EPS SURA cobra mora de {$meses} ({$plata}) y no hay planilla en BryNex.",
            'observacion' => "EPS SURA reporta mora de {$meses} por {$plata}. El contrato sigue vigente y no hay planilla de esos meses en BryNex.",
        ];
    }

    /** Lanza el Chrome que baja el informe. Las claves van por stdin. */
    private function informe($credencial, string $nit, string $desde, string $hasta): array
    {
        $resultado = Process::path(base_path())
            // Login ~40 s y el informe se genera en el momento.
            ->timeout(420)
            ->input(json_encode([
                'tipoDocumento' => $credencial->tipo_documento,
                'usuario' => $credencial->usuario,
                'contrasena' => $credencial->contrasena,
                'nitEmpresa' => $nit,
                'modo' => 'estadoCuenta',
                'desde' => $desde,
                'hasta' => $hasta,
            ], JSON_UNESCAPED_UNICODE))
            ->run(ArlSuraSesionService::binarioNode().' scripts/eps-sura-menu.mjs');

        $crudo = trim($resultado->output());
        $salida = json_decode($crudo, true);

        if (is_array($salida)) {
            return $salida;
        }

        // Si no vino JSON, el error tiene que decir qué vino: "no respondió" a
        // secas obliga a repetir la corrida entera para averiguarlo.
        return ['ok' => false, 'error' => mb_substr(trim($resultado->errorOutput())
            ?: 'El portal no respondió (salida de '.strlen($crudo).' bytes, código '.$resultado->exitCode().': '.mb_substr($crudo, 0, 120).')', 0, 400)];
    }

    private function cerrarResueltas(string $nit, array $vistas, bool $simular, array &$detalle): int
    {
        $abiertas = Tarea::whereNotNull('llave_auto')
            ->where('llave_auto', 'like', self::PREFIJO.":{$nit}:%")
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->get();

        $cerradas = 0;

        foreach ($abiertas as $tarea) {
            if (in_array($tarea->llave_auto, $vistas, true)) {
                continue;
            }

            if ($simular) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerraria', 'tarea_id' => $tarea->id];

                continue;
            }

            if ($this->tareas->cerrar($tarea, 'EPS SURA ya no lo reporta en mora el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada', 'tarea_id' => $tarea->id];
            }
        }

        return $cerradas;
    }
}
