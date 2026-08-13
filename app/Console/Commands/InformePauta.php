<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\PautaConfig;
use App\Models\Publicacion;
use App\Models\WhatsappConversacion;
use App\Services\AlertaOperativaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Informe diario de la pauta pagada: qué se gastó y qué trajo.
 *
 * Existe porque la rotación automática decide sola con un criterio que puede estar ciego. Si
 * ninguna conversación llega atribuida —hoy son 0 de 829— el orden se resuelve por fecha y
 * gana siempre la pieza más nueva, no la que funciona. Eso no se nota desde afuera: el gasto
 * corre igual y las piezas se turnan igual.
 *
 * El informe pone los dos números juntos —plata gastada y conversaciones traídas— para que la
 * decisión de seguir o cambiar la tome alguien mirando resultados. Y si el costo por
 * conversación no se puede calcular, lo dice en vez de mostrar un cero que parece un dato.
 *
 * Ejecución manual: php artisan marketing:informe-pauta
 */
class InformePauta extends Command
{
    protected $signature = 'marketing:informe-pauta
        {--aliado= : Slug del aliado (por defecto, todos los que tengan pauta activa)}
        {--no-enviar : Solo mostrarlo en pantalla, sin mandar el WhatsApp}';

    protected $description = 'Informe diario de gasto y resultados de la pauta pagada';

    private const BASE_URL = 'https://graph.facebook.com/v23.0';

    public function handle(AlertaOperativaService $alertas): int
    {
        $aliados = $this->option('aliado')
            ? Aliado::where('slug', $this->option('aliado'))->get()
            : Aliado::whereIn('id', PautaConfig::where('activo', true)->pluck('aliado_id'))->get();

        foreach ($aliados as $aliado) {
            $this->informar($aliado, $alertas);
        }

        return self::SUCCESS;
    }

    private function informar(Aliado $aliado, AlertaOperativaService $alertas): void
    {
        $config = PautaConfig::paraAliado($aliado->id);

        $piezas = Publicacion::where('aliado_id', $aliado->id)
            ->whereNotNull('meta_ad_id')
            ->whereIn('pauta_estado', ['activa', 'pausada'])
            ->get();

        if ($piezas->isEmpty()) {
            $this->line("{$aliado->nombre}: no hay piezas con pauta. Nada que informar.");
            return;
        }

        $token = $config->access_token_ads;
        $filas = [];

        foreach ($piezas as $pieza) {
            $hoy = $this->gastoDelDia($pieza->meta_ad_id, $token);
            $conv = WhatsappConversacion::where('origen_publicacion_id', $pieza->id)->count();

            $filas[] = [
                'id'          => $pieza->id,
                'estado'      => $pieza->pauta_estado,
                'gasto_hoy'   => $hoy['gasto'],
                'impresiones' => $hoy['impresiones'],
                'gasto_total' => (float) $pieza->pauta_gasto_total_cop,
                'conv'        => $conv,
                // Sin conversaciones no hay costo por conversación: mostrar el gasto como si
                // fuera el costo sería inventar un dato que no existe todavía.
                'costo_conv'  => $conv > 0 ? (float) $pieza->pauta_gasto_total_cop / $conv : null,
            ];
        }

        $this->mostrar($aliado, $config, $filas);

        if (!$this->option('no-enviar')) {
            $enviado = $alertas->enviar('Pauta ' . $aliado->nombre, $this->resumen($config, $filas));
            $this->line($enviado ? "  → enviado a {$alertas->numeroDestino()}" : '  → no se pudo enviar (ver el log).');
        }
    }

    /** @return array{gasto: float, impresiones: int} */
    private function gastoDelDia(string $adId, ?string $token): array
    {
        $r = Http::get(self::BASE_URL . "/{$adId}/insights", [
            'fields'       => 'spend,impressions',
            'date_preset'  => 'today',
            'access_token' => $token,
        ]);

        return [
            'gasto'       => (float) data_get($r->json(), 'data.0.spend', 0),
            'impresiones' => (int) data_get($r->json(), 'data.0.impressions', 0),
        ];
    }

    private function mostrar(Aliado $aliado, PautaConfig $config, array $filas): void
    {
        $this->info("── {$aliado->nombre} — pauta de hoy");
        $this->table(
            ['Pieza', 'Estado', 'Gasto hoy', 'Impres.', 'Gasto total', 'Conv.', 'Costo/conv'],
            array_map(fn ($f) => [
                "#{$f['id']}",
                $f['estado'],
                '$' . number_format($f['gasto_hoy']),
                number_format($f['impresiones']),
                '$' . number_format($f['gasto_total']),
                $f['conv'],
                $f['costo_conv'] === null ? '—' : '$' . number_format($f['costo_conv']),
            ], $filas)
        );

        $gastadoMes = $config->gastadoEsteMes();
        $this->line('  Diario del conjunto: $' . number_format($config->presupuestoDiarioCop()));
        $this->line('  Mes: $' . number_format($gastadoMes) . ' de $' . number_format((float) $config->limite_mensual_cop));

        if (array_sum(array_column($filas, 'conv')) === 0) {
            $this->warn('  Ninguna conversación viene atribuida a una pieza todavía.');
            $this->warn('  Mientras siga así, la rotación automática ordena por fecha: gana la más nueva, no la que funciona.');
        }
    }

    /** Resumen corto para el WhatsApp: la plantilla aplana los saltos de línea, así que va en una tira. */
    private function resumen(PautaConfig $config, array $filas): string
    {
        $gastoHoy = array_sum(array_column($filas, 'gasto_hoy'));
        $convTotal = array_sum(array_column($filas, 'conv'));

        $partes = [
            'Hoy $' . number_format($gastoHoy) . ' de $' . number_format($config->presupuestoDiarioCop()),
            'mes $' . number_format($config->gastadoEsteMes()) . ' de $' . number_format((float) $config->limite_mensual_cop),
            $convTotal . ' conversacion(es) atribuida(s)',
        ];

        if ($convTotal > 0) {
            // La que menos cuesta por conversación es la que hay que dejar corriendo.
            $conDatos = array_filter($filas, fn ($f) => $f['costo_conv'] !== null);
            usort($conDatos, fn ($a, $b) => $a['costo_conv'] <=> $b['costo_conv']);
            $mejor = reset($conDatos);
            $partes[] = "va ganando #{$mejor['id']} con {$mejor['conv']} a \$" . number_format($mejor['costo_conv']) . ' c/u';
        } else {
            $partes[] = 'sin datos para comparar piezas: la rotacion esta premiando lo mas nuevo, no lo que funciona';
        }

        return implode('. ', $partes) . '.';
    }
}
