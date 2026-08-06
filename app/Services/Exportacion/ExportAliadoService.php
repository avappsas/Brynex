<?php

namespace App\Services\Exportacion;

use App\Models\Aliado;
use App\Models\Canario;
use App\Models\ExportacionAliado;
use App\Services\TrazaArchivoService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Arma el ZIP con los datos de un aliado que se va.
 *
 * Decisiones que no son obvias:
 *
 *  - **Se trocea siempre.** GiMave son ~420.000 filas: 115.696 facturas y
 *    159.717 consignaciones. Se lee con `chunkById` y se escribe al disco fila
 *    por fila; en ningún momento hay un resultado completo en memoria.
 *
 *  - **Los dos formatos salen de la misma pasada.** Se escribe el CSV y el TXT
 *    en el mismo recorrido: consultar dos veces 420.000 filas para cambiar el
 *    separador no tiene sentido.
 *
 *  - **Los canarios van a propósito.** Los clientes trampa del aliado salen en
 *    el archivo de Personas. Un CSV no tiene metadatos donde esconder la traza
 *    como en un Excel, así que la prueba de origen son los canarios (si
 *    aparecen en otro sistema, la copia es un hecho) y el token firmado del
 *    LEEME. Ver [[canarios]] y TrazaArchivoService.
 *
 *  - **El ZIP va en el disco `local`.** Nunca en `public/`: el repo se sirve
 *    desde brynex.co y ya se filtraron tres scripts por URL (C-1/C-2/C-3 de
 *    la auditoría).
 */
class ExportAliadoService
{
    /** BOM para que Excel abra los CSV con tildes sin pelear. */
    private const BOM = "\xEF\xBB\xBF";

    public function __construct(
        private InformesAliado $informes,
        private TrazaArchivoService $trazas,
    ) {}

    /**
     * Genera el ZIP y deja el registro en estado `generado`.
     *
     * @param  callable|null  $progreso  fn(string $titulo, int $filas) para la consola
     */
    public function generar(ExportacionAliado $registro, ?callable $progreso = null): ExportacionAliado
    {
        $aliado = Aliado::findOrFail($registro->aliado_id);
        $aliadoId = (int) $registro->aliado_id;

        @set_time_limit(0);

        $carpeta = (string) config('exportacion.carpeta');
        $sello = now()->format('Ymd-His');
        $base = 'brynex-'.Str::slug($aliado->nombre).'-'.$sello;

        $tmpRel = $carpeta.'/tmp-'.$registro->id.'-'.Str::random(8);
        $tmpAbs = Storage::disk('local')->path($tmpRel);

        File::ensureDirectoryExists($tmpAbs.'/csv');
        File::ensureDirectoryExists($tmpAbs.'/txt');

        $token = $this->trazas->tokenPara($registro->solicitado_por, $aliadoId);
        $contexto = new ContextoExportacion;
        $resumen = [];

        try {
            foreach ($this->informes->todos() as $informe) {
                $filas = $this->escribirInforme($informe, $aliadoId, $contexto, $tmpAbs);
                $resumen[$informe['titulo']] = $filas;

                if ($progreso) {
                    $progreso($informe['titulo'], $filas);
                }
            }

            File::put(
                $tmpAbs.'/LEEME.txt',
                $this->leeme($aliado, $registro, $resumen, $token)
            );

            $password = $this->password();
            $zipRel = $carpeta.'/'.$base.'.zip';
            $cifrado = $this->comprimir($tmpAbs, Storage::disk('local')->path($zipRel), $password);
            $zipAbs = Storage::disk('local')->path($zipRel);

            $contexto->liberar();

            $registro->update([
                'estado' => 'generado',
                'archivo' => $zipRel,
                'archivo_hash' => hash_file('sha256', $zipAbs),
                'archivo_bytes' => filesize($zipAbs),
                'filas_total' => array_sum($resumen),
                'resumen' => json_encode($resumen, JSON_UNESCAPED_UNICODE),
                'traza_token' => $token,
                'error' => $cifrado ? null : 'El servidor no soporta cifrado AES en ZIP: el archivo quedó SIN contraseña.',
            ]);

            if ($cifrado) {
                $registro->guardarPassword($password);
            }

            return $registro->fresh();
        } catch (\Throwable $e) {
            $registro->update([
                'estado' => 'fallido',
                'error' => Str::limit($e->getMessage(), 900),
            ]);

            Log::error('ExportAliado: falló la generación', [
                'exportacion_id' => $registro->id,
                'aliado_id' => $aliadoId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($tmpAbs);
        }
    }

    // ── Escritura de un informe ──────────────────────────────────────────

    /** @return int filas escritas */
    private function escribirInforme(array $informe, int $aliadoId, ContextoExportacion $ctx, string $tmpAbs): int
    {
        $cabeceras = array_keys($informe['columnas']);

        $csv = fopen($tmpAbs.'/csv/'.$informe['archivo'].'.csv', 'w');
        $txt = fopen($tmpAbs.'/txt/'.$informe['archivo'].'.txt', 'w');

        fwrite($csv, self::BOM);
        fwrite($txt, self::BOM);
        fputcsv($csv, $cabeceras, ',', '"', '');
        fwrite($txt, implode("\t", $cabeceras)."\r\n");

        $filas = 0;
        $chunk = max(200, (int) config('exportacion.chunk'));

        try {
            foreach ($informe['fuentes'] as $fuente) {
                ($fuente['builder'])($aliadoId)
                    ->chunkById($chunk, function ($lote) use (&$filas, $informe, $ctx, $csv, $txt) {
                        foreach ($lote as $fila) {
                            $valores = $this->valores($informe['columnas'], $fila, $ctx);

                            fputcsv($csv, array_map([$this, 'paraCsv'], $valores), ',', '"', '');
                            fwrite($txt, implode("\t", $valores)."\r\n");
                            $filas++;
                        }
                    }, $fuente['id'], 'id');
            }
        } finally {
            fclose($csv);
            fclose($txt);
        }

        return $filas;
    }

    /**
     * Resuelve la fila a valores de texto ya saneados.
     *
     * @param  array<string, string|\Closure>  $columnas
     * @return array<int, string>
     */
    private function valores(array $columnas, object $fila, ContextoExportacion $ctx): array
    {
        $out = [];

        foreach ($columnas as $columna) {
            $valor = $columna instanceof \Closure
                ? $columna($fila, $ctx)
                : ($fila->{$columna} ?? null);

            $out[] = $this->sanear($valor);
        }

        return $out;
    }

    /**
     * Un valor de celda: sin saltos de línea ni tabuladores.
     *
     * Los saltos son válidos dentro de un campo entrecomillado (RFC 4180), pero
     * rompen el TXT separado por tabuladores y hacen que "10.000 filas" se vean
     * como 14.000 en cualquier visor de líneas. Se cambian por " / " en los dos
     * formatos para que el conteo del LEEME cuadre con lo que se ve.
     */
    private function sanear($valor): string
    {
        if ($valor === null || $valor === false) {
            return '';
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y H:i');
        }

        $texto = (string) $valor;

        if ($texto === '') {
            return '';
        }

        // SQL Server puede devolver varchar con colación Latin1: si no es UTF-8
        // válido, un solo byte raro corrompe el archivo completo para Excel.
        if (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        $texto = str_replace(["\r\n", "\r", "\n", "\t"], [' / ', ' / ', ' / ', ' '], $texto);

        return trim(preg_replace('/ {2,}/', ' ', $texto));
    }

    /**
     * Neutraliza la inyección de fórmulas de Excel.
     *
     * Una observación que empieza con "=" o "@" es una fórmula viva al abrir el
     * CSV, y este archivo se lo abre un tercero en su computador. Se le antepone
     * una comilla simple, que Excel entiende como "esto es texto". El "-" queda
     * fuera a propósito: partiría los importes negativos.
     */
    private function paraCsv(string $valor): string
    {
        if ($valor !== '' && ! is_numeric($valor) && str_contains('=+@', $valor[0])) {
            return "'".$valor;
        }

        return $valor;
    }

    // ── Empaquetado ──────────────────────────────────────────────────────

    /** @return bool si el ZIP quedó cifrado */
    private function comprimir(string $origen, string $destino, string $password): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No pude crear el ZIP en '.$destino);
        }

        $cifrable = method_exists($zip, 'setEncryptionName') && defined('ZipArchive::EM_AES_256');

        if ($cifrable) {
            $zip->setPassword($password);
        }

        foreach (File::allFiles($origen) as $archivo) {
            $interno = str_replace('\\', '/', $archivo->getRelativePathname());
            $zip->addFile($archivo->getPathname(), $interno);

            if ($cifrable) {
                $zip->setEncryptionName($interno, ZipArchive::EM_AES_256);
            }
        }

        if (! $zip->close()) {
            throw new \RuntimeException('El ZIP no se pudo cerrar: '.$zip->getStatusString());
        }

        if (! $cifrable) {
            Log::warning('ExportAliado: libzip sin cifrado AES, el ZIP salió sin contraseña', ['zip' => $destino]);
        }

        return $cifrable;
    }

    /**
     * Contraseña dictable por teléfono: sin 0/O ni 1/I/l, en grupos de cuatro.
     */
    private function password(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $grupos = [];

        for ($g = 0; $g < 4; $g++) {
            $grupo = '';
            for ($i = 0; $i < 4; $i++) {
                $grupo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $grupos[] = $grupo;
        }

        return implode('-', $grupos);
    }

    // ── LEEME ────────────────────────────────────────────────────────────

    /** @param array<string,int> $resumen */
    private function leeme(Aliado $aliado, ExportacionAliado $registro, array $resumen, string $token): string
    {
        $usuario = $registro->solicitante;
        $canarios = Canario::where('aliado_id', $registro->aliado_id)->where('activo', true)->count();

        $lineas = [
            '════════════════════════════════════════════════════════════════════',
            '  ENTREGA DE INFORMACIÓN — '.mb_strtoupper($aliado->nombre),
            '════════════════════════════════════════════════════════════════════',
            '',
            'Fecha de corte : '.now()->format('d/m/Y H:i').' (hora de Colombia)',
            'Generado por   : '.($usuario->nombre ?? 'BryNex').' — BryNex',
            'Entrega N°     : '.$registro->id,
            'Referencia     : '.$token,
            '',
            'CONTENIDO',
            '─────────────────────────────────────────────────────────────────────',
            'La misma información va dos veces, para que la abra con lo que tenga:',
            '',
            '  carpeta csv/ → separado por comas (,), campos entre comillas',
            '  carpeta txt/ → separado por tabuladores',
            '',
            'Los dos están en UTF-8. Si al abrir el CSV en Excel las tildes salen',
            'raras o todo cae en una sola columna, use Datos > Obtener datos >',
            'Desde texto/CSV y elija la coma como separador; el archivo de la',
            'carpeta txt/ normalmente se abre bien de una.',
            '',
            'ARCHIVOS',
            '─────────────────────────────────────────────────────────────────────',
        ];

        foreach ($this->informes->todos() as $informe) {
            $filas = $resumen[$informe['titulo']] ?? 0;
            $lineas[] = sprintf('  %-34s %9s registros', $informe['archivo'], number_format($filas, 0, ',', '.'));

            foreach (explode("\n", wordwrap($informe['descripcion'], 62)) as $renglon) {
                $lineas[] = '      '.$renglon;
            }

            $lineas[] = '';
        }

        $lineas = array_merge($lineas, [
            sprintf('  %-34s %8s registros', 'TOTAL', number_format(array_sum($resumen), 0, ',', '.')),
            '',
            'CÓMO CRUZAR LOS ARCHIVOS',
            '─────────────────────────────────────────────────────────────────────',
            'Las personas se identifican por su número de documento.',
            '',
            'Para lo demás hay una columna «Consecutivo». Es un número que existe',
            'solo dentro de esta entrega y sirve para amarrar un archivo con otro:',
            '',
            '  Pagos Recibidos.Consecutivo Factura      → Facturación.Consecutivo',
            '  Gestiones de Cobro.Consecutivo Factura   → Facturación.Consecutivo',
            '  Gestiones de Incapacidades.Consecutivo   → Incapacidades.Consecutivo',
            '  Movimientos de Trámites.Consecutivo      → Trámites.Consecutivo',
            '  Gestiones de Tareas.Consecutivo Tarea    → Tareas.Consecutivo',
            '',
            'No se usa el número de factura para cruzar porque no es único: un',
            'mismo número agrupa a varias personas de la misma planilla.',
            '',
            'CONVENCIONES',
            '─────────────────────────────────────────────────────────────────────',
            '  Fechas   : d/m/aaaa   (con hora: d/m/aaaa hh:mm, 24 horas)',
            '  Importes : punto decimal y sin separador de miles → 1250000.00',
            '  Sí / No  : campos de sí o no; vacío significa sin dato',
            '  Vacío    : el dato no existe en el sistema, no es un cero',
            '',
            'ALCANCE',
            '─────────────────────────────────────────────────────────────────────',
            'Va toda la información propia del aliado: personas, beneficiarios,',
            'empresas, razones sociales, afiliaciones, facturación, pagos,',
            'incapacidades, trámites, tareas, prospectos, usuarios y asesores.',
            '',
            'No van los catálogos del sistema (EPS, ARL, fondos de pensión, cajas',
            'de compensación, ciudades, planes, modalidades): no son información',
            'del aliado. Cuando alguna de esas entidades aplica a un registro,',
            'aparece con su nombre dentro del archivo correspondiente.',
            '',
            'Tampoco van contraseñas, credenciales de operadores de planilla,',
            'archivos adjuntos ni datos de sesión de los usuarios.',
            '',
            'Las facturas anuladas SÍ están incluidas, con Estado = ANULADA y su',
            'motivo, para que los totales históricos cuadren. Los registros',
            'eliminados de los demás archivos no se incluyen.',
            '',
            'PROTECCIÓN DE DATOS PERSONALES',
            '─────────────────────────────────────────────────────────────────────',
            'Esta entrega contiene datos personales y datos sensibles de salud',
            '(diagnósticos de incapacidades) de personas identificadas.',
            '',
            'Con la entrega, '.$aliado->nombre.' queda como responsable del',
            'tratamiento de esta información en los términos de la Ley 1581 de',
            '2012 y sus decretos reglamentarios, y asume el deber de custodiarla,',
            'usarla solo para las finalidades autorizadas por sus titulares y',
            'atender las consultas y reclamos que estos presenten.',
            '',
            'La entrega quedó registrada en BryNex con fecha, responsable y',
            'huella digital del archivo (SHA-256).',
            '',
            'TRAZABILIDAD',
            '─────────────────────────────────────────────────────────────────────',
            'El código de la línea «Referencia» va firmado criptográficamente e',
            'identifica de forma inequívoca esta entrega, la cuenta que la generó',
            'y el momento exacto en que se hizo.',
            $canarios > 0
                ? 'Los archivos incluyen además marcadores de verificación propios de esta base de datos.'
                : '',
            '',
            '════════════════════════════════════════════════════════════════════',
            'BryNex — brynex.co',
            '════════════════════════════════════════════════════════════════════',
        ]);

        return implode("\r\n", array_filter($lineas, fn ($l) => $l !== null));
    }

    // ── Mantenimiento ────────────────────────────────────────────────────

    /**
     * Borra los ZIP que pasaron de la ventana de retención. Se llama al entrar
     * a la pantalla: son datos personales de miles de personas, no deben quedar
     * en el servidor más de lo necesario.
     *
     * @return int entregas purgadas
     */
    public function purgarVencidas(): int
    {
        $limite = now()->subDays((int) config('exportacion.dias_retencion'));
        $purgadas = 0;

        $vencidas = ExportacionAliado::where('estado', 'generado')
            ->whereNull('purgado_at')
            ->where('created_at', '<', $limite)
            ->get();

        foreach ($vencidas as $exportacion) {
            if ($exportacion->archivo) {
                Storage::disk('local')->delete($exportacion->archivo);
            }

            $exportacion->update([
                'purgado_at' => now(),
                'zip_password' => null,
            ]);

            $purgadas++;
        }

        // Solicitudes que nunca se confirmaron.
        ExportacionAliado::where('estado', 'pendiente')
            ->where('codigo_expira_at', '<', now())
            ->update(['estado' => 'vencido', 'codigo_hash' => null]);

        return $purgadas;
    }
}
