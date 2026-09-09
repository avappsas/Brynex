<?php

namespace App\Services;

/**
 * Convierte cifras a letras en español, como las exigen los documentos legales
 * ("TREINTA MILLONES DE PESOS MONEDA CORRIENTE ($30.000.000 M/CTE)").
 *
 * Se implementa a mano en vez de usar NumberFormatter porque la extensión intl
 * no está garantizada en el servidor de producción.
 */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA',
        7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    /**
     * Cifra en letras, sin la palabra "pesos". Ej: 1500 → "MIL QUINIENTOS".
     */
    public static function convertir(float|int $numero): string
    {
        $entero = (int) round($numero);

        if ($entero === 0) {
            return 'CERO';
        }

        if ($entero < 0) {
            return 'MENOS '.self::convertir(abs($entero));
        }

        $millones = intdiv($entero, 1000000);
        $resto = $entero % 1000000;

        $texto = '';

        if ($millones > 0) {
            $texto = $millones === 1
                ? 'UN MILLÓN'
                : self::apocope(self::menorAMillon($millones)).' MILLONES';
        }

        if ($resto > 0) {
            $texto = trim($texto.' '.self::menorAMillon($resto));
        }

        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Cifra en letras acompañada del valor en números, como se escribe en un
     * contrato: "TREINTA MILLONES DE PESOS MONEDA CORRIENTE ($30.000.000 M/CTE)".
     */
    public static function pesos(float|int $numero): string
    {
        $letras = self::convertir($numero);

        // "TREINTA MILLONES DE PESOS", pero "UN MILLÓN CUATROCIENTOS MIL PESOS".
        $preposicion = str_ends_with($letras, 'MILLÓN') || str_ends_with($letras, 'MILLONES') ? ' DE' : '';

        return $letras.$preposicion.' PESOS MONEDA CORRIENTE ($'.number_format((float) $numero, 0, ',', '.').' M/CTE)';
    }

    /**
     * Número en letras seguido de la cifra entre paréntesis: "VEINTINUEVE (29)".
     */
    public static function conCifra(int $numero): string
    {
        return self::convertir($numero)." ({$numero})";
    }

    /**
     * Porcentaje en letras: "DOS PUNTO UNO POR CIENTO (2,1%)".
     */
    public static function porcentaje(float $tasa): string
    {
        $formateada = rtrim(rtrim(number_format($tasa, 3, ',', ''), '0'), ',');
        [$entera, $decimal] = array_pad(explode(',', $formateada), 2, null);

        $texto = self::convertir((int) $entera);

        if ($decimal !== null && $decimal !== '') {
            $digitos = array_map(
                fn ($d) => $d === '0' ? 'CERO' : self::UNIDADES[(int) $d],
                str_split($decimal)
            );
            $texto .= ' PUNTO '.implode(' ', $digitos);
        }

        return $texto." POR CIENTO ({$formateada}%)";
    }

    /**
     * Bloque de menos de un millón (hasta seis cifras).
     */
    private static function menorAMillon(int $numero): string
    {
        $miles = intdiv($numero, 1000);
        $resto = $numero % 1000;

        $texto = '';

        if ($miles === 1) {
            $texto = 'MIL';
        } elseif ($miles > 1) {
            $texto = self::apocope(self::tresCifras($miles)).' MIL';
        }

        if ($resto > 0) {
            $texto = trim($texto.' '.self::tresCifras($resto));
        }

        return trim($texto);
    }

    /**
     * Bloque de tres cifras (1 a 999).
     */
    private static function tresCifras(int $numero): string
    {
        if ($numero === 0) {
            return '';
        }

        if ($numero === 100) {
            return 'CIEN';
        }

        $centenas = intdiv($numero, 100);
        $resto = $numero % 100;

        $texto = $centenas > 0 ? self::CENTENAS[$centenas] : '';

        if ($resto > 0) {
            $texto = trim($texto.' '.self::dosCifras($resto));
        }

        return trim($texto);
    }

    /**
     * Apócope de "uno" delante de mil y millones: veintiún mil, treinta y un
     * millones. Sin esto quedaría "veintiuno mil", que ningún notario firma.
     */
    private static function apocope(string $texto): string
    {
        if ($texto === 'UNO') {
            return 'UN';
        }

        if (str_ends_with($texto, 'VEINTIUNO')) {
            return substr($texto, 0, -9).'VEINTIÚN';
        }

        if (str_ends_with($texto, ' Y UNO')) {
            return substr($texto, 0, -6).' Y UN';
        }

        return $texto;
    }

    /**
     * Bloque de dos cifras (1 a 99).
     */
    private static function dosCifras(int $numero): string
    {
        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        $decena = intdiv($numero, 10);
        $unidad = $numero % 10;

        return $unidad === 0
            ? self::DECENAS[$decena]
            : self::DECENAS[$decena].' Y '.self::UNIDADES[$unidad];
    }
}
