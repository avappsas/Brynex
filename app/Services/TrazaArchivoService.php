<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Marca cada archivo exportado con quién lo generó, sin que se note.
 *
 * No hay nada visible: la traza va en las propiedades del documento (Excel) o
 * en los metadatos (PDF). El archivo se ve, se imprime y se procesa igual — lo
 * que cambia es que deja de ser anónimo. Si mañana aparece una exportación de
 * BryNex en otro sistema, la traza dice de qué cuenta salió y cuándo.
 *
 * El token va firmado con APP_KEY: sin la llave no se puede fabricar uno que
 * `verificar()` acepte, así que tampoco se puede culpar a otro usuario editando
 * las propiedades del archivo. Y si alguien las borra, se borra la prueba pero
 * no se falsifica — que es la propiedad que interesa.
 */
class TrazaArchivoService
{
    /** Nombre de la propiedad personalizada donde vive la traza. */
    public const PROPIEDAD = 'BrynexTrz';

    /** Bytes de HMAC que se conservan: suficiente contra falsificación aquí. */
    private const LARGO_FIRMA = 16;

    /**
     * Token de traza del usuario autenticado.
     *
     * Formato: u{usuario}-a{aliado}-{epoch}.{firma}
     */
    public function token(): string
    {
        $user = Auth::user();

        $payload = sprintf(
            'u%s-a%s-%d',
            $user->id ?? '0',
            $user->aliado_id ?? session('aliado_id_activo') ?? '0',
            now()->timestamp
        );

        return $payload.'.'.$this->firmar($payload);
    }

    /**
     * Descripción legible que acompaña al token dentro del archivo.
     */
    public function descripcion(): string
    {
        $user = Auth::user();

        if (! $user) {
            return 'Generado por un proceso automático de BryNex.';
        }

        return sprintf(
            'Generado por %s (CC %s) el %s desde BryNex.',
            $user->nombre,
            $user->cedula,
            now()->format('d/m/Y H:i')
        );
    }

    /**
     * Marca un Excel. Va en las propiedades del documento, no en una celda:
     * una celda con texto raro la borra cualquiera y además estorba al
     * procesar el archivo — sobre todo en los planos que leen los operadores
     * de planilla, donde una columna de más rompe la carga.
     */
    public function marcarExcel(Spreadsheet $spreadsheet): void
    {
        try {
            $user = Auth::user();

            $props = $spreadsheet->getProperties();
            $props->setCreator('BryNex');
            $props->setLastModifiedBy($user->nombre ?? 'BryNex');
            $props->setCompany($user?->aliado?->nombre ?? 'BryNex');
            $props->setDescription($this->descripcion());
            $props->setCustomProperty(self::PROPIEDAD, $this->token(), 's');
        } catch (\Throwable $e) {
            // Marcar es deseable; exportar es obligatorio. Si algo falla aquí,
            // el usuario tiene que seguir bajando su archivo igual.
            Log::warning('TrazaArchivo: no se pudo marcar el Excel: '.$e->getMessage());
        }
    }

    /**
     * Marca un PDF de DomPDF (envoltorio de Barryvdh o instancia directa).
     */
    public function marcarPdf(object $pdf): void
    {
        try {
            $dompdf = method_exists($pdf, 'getDomPDF') ? $pdf->getDomPDF() : $pdf;

            if (! method_exists($dompdf, 'add_info')) {
                return;
            }

            $dompdf->add_info('Creator', 'BryNex');
            $dompdf->add_info('Author', Auth::user()->nombre ?? 'BryNex');
            $dompdf->add_info('Subject', $this->descripcion());
            $dompdf->add_info('Keywords', self::PROPIEDAD.'='.$this->token());
        } catch (\Throwable $e) {
            Log::warning('TrazaArchivo: no se pudo marcar el PDF: '.$e->getMessage());
        }
    }

    /**
     * Comprueba un token encontrado en un archivo.
     *
     * @return array{user_id:int, aliado_id:int, fecha:\Carbon\Carbon}|null
     *                                                                      null si la firma no cuadra: el token fue alterado o no salió de aquí
     */
    public function verificar(string $token): ?array
    {
        $partes = explode('.', trim($token));

        if (count($partes) !== 2) {
            return null;
        }

        [$payload, $firma] = $partes;

        if (! hash_equals($this->firmar($payload), $firma)) {
            return null;
        }

        if (! preg_match('/^u(\d+)-a(\d+)-(\d+)$/', $payload, $m)) {
            return null;
        }

        return [
            'user_id' => (int) $m[1],
            'aliado_id' => (int) $m[2],
            'fecha' => \Carbon\Carbon::createFromTimestamp((int) $m[3]),
        ];
    }

    private function firmar(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, config('app.key')), 0, self::LARGO_FIRMA);
    }
}
