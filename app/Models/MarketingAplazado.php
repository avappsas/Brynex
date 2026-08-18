<?php

namespace App\Models;

/**
 * Contactos que pidieron que les escribamos más adelante.
 *
 * Distinto de MarketingBloqueado: ahí está quien NO quiere saber más. Aquí quien sí, pero no
 * ahora. Mezclarlos perdería clientes —un "por ahora no" no es una baja— y no distinguirlos
 * los irritaría hasta convertirlos en baja.
 */
class MarketingAplazado extends BaseModel
{
    protected $table = 'marketing_aplazamientos';

    protected $fillable = ['aliado_id', 'telefono', 'hasta', 'origen', 'motivo', 'conversacion_id'];

    protected $casts = ['hasta' => 'date'];

    /** Días que se espera por defecto cuando alguien dice "por ahora no". */
    public const DIAS_POR_DEFECTO = 90;

    public static function aplazar(
        int $aliadoId,
        ?string $telefono,
        int $dias = self::DIAS_POR_DEFECTO,
        ?string $origen = null,
        ?string $motivo = null,
        ?int $conversacionId = null
    ): ?self {
        $tel = ConsentimientoDato::normalizarTelefono($telefono);
        if (!$tel) {
            return null;
        }

        return self::create([
            'aliado_id'       => $aliadoId,
            'telefono'        => $tel,
            'hasta'           => now()->addDays($dias)->toDateString(),
            'origen'          => $origen,
            'motivo'          => $motivo,
            'conversacion_id' => $conversacionId,
        ]);
    }

    /**
     * Teléfonos con aplazamiento VIGENTE, de una lista dada.
     *
     * Se consulta en lote y por trozos: SQL Server no acepta más de 2.100 parámetros por
     * consulta y un segmento de ex-clientes los pasa fácil.
     *
     * @param  string[]  $telefonos
     * @return string[]  normalizados
     */
    public static function vigentesDe(int $aliadoId, array $telefonos): array
    {
        $normalizados = array_values(array_unique(array_filter(
            array_map([ConsentimientoDato::class, 'normalizarTelefono'], $telefonos)
        )));

        if (!$normalizados) {
            return [];
        }

        $encontrados = [];
        foreach (array_chunk($normalizados, 1000) as $lote) {
            $encontrados = array_merge($encontrados, self::where('aliado_id', $aliadoId)
                ->whereIn('telefono', $lote)
                ->whereDate('hasta', '>=', now()->toDateString())
                ->pluck('telefono')
                ->all());
        }

        return array_values(array_unique($encontrados));
    }
}
