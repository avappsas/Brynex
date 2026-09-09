<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\PrestamoExpediente;
use App\Services\NumeroALetras;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/**
 * Arma los documentos legales de un préstamo formal a partir del expediente.
 *
 * El mismo HTML alimenta las dos salidas: DomPDF produce el PDF para imprimir
 * y firmar, y PHPWord el .docx por si hay que retocar una cláusula antes de
 * imprimir. Por eso las plantillas usan HTML pobre y bien formado — PHPWord
 * las parsea como XML y muere con una entidad `&nbsp;` o una etiqueta abierta.
 */
class ExpedienteDocumentosService
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const ORDINALES = [
        'PRIMERA', 'SEGUNDA', 'TERCERA', 'CUARTA', 'QUINTA', 'SEXTA', 'SÉPTIMA', 'OCTAVA',
        'NOVENA', 'DÉCIMA', 'DÉCIMA PRIMERA', 'DÉCIMA SEGUNDA', 'DÉCIMA TERCERA',
        'DÉCIMA CUARTA', 'DÉCIMA QUINTA', 'DÉCIMA SEXTA', 'DÉCIMA SÉPTIMA', 'DÉCIMA OCTAVA',
        'DÉCIMA NOVENA', 'VIGÉSIMA',
    ];

    /** Hueco para escribir la tasa a mano cuando se deja en blanco. */
    private const LINEA_TASA = '__________';

    private const TITULOS = [
        'contrato' => 'Contrato de mutuo',
        'pagare' => 'Pagaré',
        'carta' => 'Carta de instrucciones',
        'prenda' => 'Contrato de prenda',
        'recibo' => 'Recibo de desembolso',
        'requisitos' => 'Documentos requeridos',
    ];

    /**
     * Documentos que le corresponden a un expediente, en el orden en que se
     * firman. La prenda solo aparece si hay garantía.
     */
    public function documentosDe(PrestamoExpediente $expediente): array
    {
        $documentos = ['contrato', 'pagare', 'carta'];

        if ($expediente->tiene_prenda) {
            $documentos[] = 'prenda';
        }

        $documentos[] = 'recibo';
        $documentos[] = 'requisitos';

        return $documentos;
    }

    public function titulo(string $documento): string
    {
        return self::TITULOS[$documento] ?? $documento;
    }

    /**
     * PDF con todos los documentos del expediente, uno por página.
     */
    public function pdf(PrestamoExpediente $expediente, ?array $documentos = null): \Barryvdh\DomPDF\PDF
    {
        $documentos = $this->validar($expediente, $documentos);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'finanzas.prestamos.documentos.paquete',
            $this->datos($expediente) + [
                'documentos' => $documentos,
                'titulo' => 'Documentos '.$expediente->pagare_numero,
            ]
        )->setPaper('letter');
    }

    /**
     * Documento de Word con los mismos documentos, cada uno en su propia
     * sección para que arranque en página nueva.
     */
    public function word(PrestamoExpediente $expediente, ?array $documentos = null): string
    {
        $documentos = $this->validar($expediente, $documentos);
        $datos = $this->datos($expediente);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10.5);

        foreach ($documentos as $documento) {
            $seccion = $phpWord->addSection([
                'marginTop' => 1250, 'marginBottom' => 1150,
                'marginLeft' => 1250, 'marginRight' => 1250,
            ]);

            Html::addHtml($seccion, $this->html($documento, $datos), false, false);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'expediente_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($ruta);

        return $ruta;
    }

    /**
     * HTML de un solo documento, ya con sus variables resueltas.
     */
    public function html(string $documento, array $datos): string
    {
        return View::make('finanzas.prestamos.documentos._'.$documento, $datos)->render();
    }

    /**
     * Variables que comparten todas las plantillas.
     */
    public function datos(PrestamoExpediente $expediente): array
    {
        $expediente->loadMissing('prestamista');

        // La fecha de suscripción manda sobre la del desembolso previsto: una
        // vez firmado, los documentos deben reimprimirse con la fecha real.
        $suscripcion = $expediente->fecha_firma ?: $expediente->fecha_desembolso;
        $suscripcion = $suscripcion instanceof Carbon ? $suscripcion->copy() : Carbon::parse($suscripcion);

        $tasa = (float) $expediente->tasa_interes_mensual;

        // Con la tasa en blanco, ninguna cifra del paquete puede delatarla.
        $enBlanco = (bool) $expediente->tasa_en_blanco;

        return [
            'exp' => $expediente,
            'prestamista' => $expediente->prestamista,
            'ordinales' => self::ORDINALES,
            'fechaCorta' => $this->fechaCorta($suscripcion),
            'fechaLarga' => $this->fechaLarga($suscripcion),
            'vencimientoLargo' => $this->fechaCorta($expediente->fecha_vencimiento),
            'primerCorteLargo' => $this->fechaCorta($expediente->primer_corte),
            'montoLetras' => NumeroALetras::pesos($expediente->monto),
            'topeLetras' => NumeroALetras::pesos($expediente->pagare_tope),
            'plazoLetras' => NumeroALetras::conCifra($expediente->plazo_meses),
            // En blanco se escribe una sola vez y en cifra: el porcentaje en
            // letras a mano, repetido en cuatro documentos, se presta a errores.
            'tasaLetras' => $enBlanco
                ? self::LINEA_TASA.'%'
                : NumeroALetras::porcentaje($tasa),
            'tasaCorta' => $enBlanco
                ? self::LINEA_TASA.'%'
                : rtrim(rtrim(number_format($tasa, 3, ',', ''), '0'), ',').'%',
        ];
    }

    private function fechaCorta(Carbon|string $fecha): string
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return $fecha->day.' de '.self::MESES[$fecha->month].' de '.$fecha->year;
    }

    private function fechaLarga(Carbon $fecha): string
    {
        return 'a los '.$fecha->day.' días del mes de '.self::MESES[$fecha->month].' de '.$fecha->year;
    }

    /**
     * Filtra lo que pidió el usuario contra lo que el expediente permite: sin
     * garantía no hay contrato de prenda que valga.
     */
    private function validar(PrestamoExpediente $expediente, ?array $documentos): array
    {
        $permitidos = $this->documentosDe($expediente);

        if (! $documentos) {
            return $permitidos;
        }

        $filtrados = array_values(array_intersect($permitidos, $documentos));

        return $filtrados ?: $permitidos;
    }
}
