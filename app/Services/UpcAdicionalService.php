<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Valor de UPC adicional a cobrar por afiliar a alguien que NO pertenece al
 * núcleo familiar del cotizante (modalidad "UPC", tipo_modalidad_id 13).
 *
 * El valor depende de la edad, el sexo y la zona geográfica del BENEFICIARIO
 * (no del cotizante) — ver `database/migrations/..._create_upc_adicional_tarifas_table.php`
 * para la fuente y el año vigente de los valores.
 */
class UpcAdicionalService
{
    /** Zona geográfica UPC para una ciudad de Brynex (`ciudades.id`). */
    public static function zonaParaCiudad(?string $ciudadId): string
    {
        if (!$ciudadId) {
            return 'normal';
        }

        return DB::table('ciudades')->where('id', $ciudadId)->value('zona_upc_adicional') ?? 'normal';
    }

    /**
     * Busca el valor de UPC adicional para una edad/sexo/zona puntuales.
     * Sexo solo distingue en los rangos 15-18 y 19-44; en el resto se ignora.
     */
    public static function valorParaEdadSexoZona(int $edad, ?string $sexo, string $zona, ?int $vigenciaAnio = null): ?int
    {
        $vigenciaAnio ??= (int) date('Y');

        $query = DB::table('upc_adicional_tarifas')
            ->where('vigencia_anio', $vigenciaAnio)
            ->where('zona', $zona)
            ->where('edad_desde', '<=', $edad)
            ->where(function ($q) use ($edad) {
                $q->whereNull('edad_hasta')->orWhere('edad_hasta', '>=', $edad);
            });

        $fila = (clone $query)->where('sexo', $sexo)->first()
            ?? (clone $query)->whereNull('sexo')->first();

        return $fila ? (int) $fila->valor : null;
    }

    /**
     * Resuelve el valor de UPC adicional para un cliente ya registrado en
     * Brynex, usando su fecha de nacimiento, género y municipio.
     *
     * @return array{valor: ?int, zona: string, edad: ?int, advertencia: ?string}
     */
    public static function valorParaCliente(Cliente $cliente, ?int $vigenciaAnio = null): array
    {
        if (empty($cliente->fecha_nacimiento)) {
            return [
                'valor' => null,
                'zona' => self::zonaParaCiudad($cliente->municipio_id),
                'edad' => null,
                'advertencia' => 'El cliente no tiene fecha de nacimiento registrada: no se puede calcular el valor de UPC adicional.',
            ];
        }

        $edad = (int) \Carbon\Carbon::parse($cliente->fecha_nacimiento)->age;
        $zona = self::zonaParaCiudad($cliente->municipio_id);

        // El campo `genero` en clientes trae datos heredados sucios (valores
        // como '0000000000', 'A+', 'NC'...). Solo se confía en 'M' o 'F'
        // exactos; cualquier otra cosa se trata como sexo desconocido.
        $generoNorm = strtoupper(trim((string) $cliente->genero));
        $sexo = match ($generoNorm) {
            'M' => 'H', // en clientes: M = Masculino: en la tarifa: H = Hombre
            'F' => 'M', // en clientes: F = Femenino:  en la tarifa: M = Mujer
            default => null,
        };

        $advertencia = null;

        // Sin sexo confiable, en un rango donde sí importa (15-18 o 19-44):
        // se cobra el valor más alto de los dos, para no dejar a Brynex
        // cobrando de menos por un dato sucio del cliente.
        if ($sexo === null && in_array(true, [
            $edad >= 15 && $edad <= 18,
            $edad >= 19 && $edad <= 44,
        ], true)) {
            $valorH = self::valorParaEdadSexoZona($edad, 'H', $zona, $vigenciaAnio);
            $valorM = self::valorParaEdadSexoZona($edad, 'M', $zona, $vigenciaAnio);
            $valor = max($valorH ?? 0, $valorM ?? 0) ?: null;
            $advertencia = 'El género del cliente no está registrado correctamente (valor: "' . $cliente->genero . '"). '
                . 'Se tomó el valor más alto entre hombre y mujer para esta edad — verifique y corrija el género del cliente.';
        } else {
            $valor = self::valorParaEdadSexoZona($edad, $sexo, $zona, $vigenciaAnio);
        }

        if ($valor === null) {
            $advertencia = "No hay tarifa de UPC adicional configurada para {$edad} años en zona '{$zona}'"
                . ($vigenciaAnio ? " (vigencia {$vigenciaAnio})" : '') . '.';
        }

        return [
            'valor' => $valor,
            'zona' => $zona,
            'edad' => $edad,
            'advertencia' => $advertencia,
        ];
    }
}
