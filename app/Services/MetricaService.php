<?php

namespace App\Services;

use App\Models\PaginaMetrica;

/**
 * Analítica propia mínima de la página pública — sin Google Analytics (decisión del plan).
 * Log de eventos simple: visita, clic a WhatsApp, cotización completada, lead capturado.
 */
class MetricaService
{
    public const VISITA                 = 'visita';
    public const CLIC_WHATSAPP          = 'clic_whatsapp';
    public const COTIZACION_COMPLETADA  = 'cotizacion_completada';
    public const LEAD_CAPTURADO         = 'lead_capturado';

    public const TIPOS = [self::VISITA, self::CLIC_WHATSAPP, self::COTIZACION_COMPLETADA, self::LEAD_CAPTURADO];

    public static function registrar(int $aliadoId, string $tipo, array $metadata = []): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            return;
        }

        PaginaMetrica::create([
            'aliado_id' => $aliadoId,
            'tipo'      => $tipo,
            'metadata'  => $metadata ?: null,
        ]);
    }

    /** Resumen de los últimos $dias días, para el dashboard del admin de la página. */
    public static function resumen(int $aliadoId, int $dias = 30): array
    {
        $conteos = PaginaMetrica::where('aliado_id', $aliadoId)
            ->where('created_at', '>=', now()->subDays($dias))
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return [
            'visitas'                  => (int) ($conteos[self::VISITA] ?? 0),
            'clics_whatsapp'           => (int) ($conteos[self::CLIC_WHATSAPP] ?? 0),
            'cotizaciones_completadas' => (int) ($conteos[self::COTIZACION_COMPLETADA] ?? 0),
            'leads_capturados'         => (int) ($conteos[self::LEAD_CAPTURADO] ?? 0),
            'dias'                     => $dias,
        ];
    }
}
