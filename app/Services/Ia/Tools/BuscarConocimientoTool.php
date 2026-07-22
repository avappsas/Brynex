<?php

namespace App\Services\Ia\Tools;

use App\Models\IaConocimiento;
use Illuminate\Support\Str;

class BuscarConocimientoTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'buscar_conocimiento';
    }

    public function descripcion(): string
    {
        return 'Busca en la base de conocimiento aprobada por el entrenador sobre seguridad social '
            . '(normativa, procedimientos, preguntas frecuentes) y guías internas de Brynex. '
            . 'Úsala antes de responder preguntas conceptuales de seguridad social.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'consulta' => ['type' => 'string', 'description' => 'Palabras clave del tema (ej: "ARL nivel de riesgo", "incapacidad", "traslado EPS").'],
            ],
            'required' => ['consulta'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $consulta = trim($input['consulta'] ?? '');
        $alidoId  = $contexto['aliado_id'] ?? null;

        // Buscar por PALABRAS, no por la frase completa: un cliente real nunca escribe la
        // consulta con el mismo orden/forma exacta del texto guardado. Se filtran muletillas
        // conocidas, pero exigir el 100% de las palabras restantes sigue siendo frágil — una
        // sola palabra de cortesía que no anticipamos ("porfa", "confirma") rompía el match
        // entero. Por eso ahora basta con que aparezca la MAYORÍA (60%, mínimo 1).
        //
        // El match se hace en PHP (no SQL) para poder normalizar tildes con Str::ascii() de
        // forma portable, sin depender de un COLLATE específico de SQL Server — la base de
        // conocimiento es pequeña (decenas de filas), así que traer todo y filtrar aquí es
        // más simple y mantenible que pelear con collations.
        $stopwords = ['de', 'la', 'el', 'los', 'las', 'que', 'es', 'y', 'a', 'en', 'del', 'un',
            'una', 'para', 'con', 'se', 'su', 'lo', 'al', 'mi', 'como', 'qué', 'cómo', 'soy',
            'me', 'te', 'porfa', 'porfavor', 'favor', 'puedes', 'podrias', 'podrías',
            'confirma', 'confirmar', 'dime', 'ayudame', 'ayúdame', 'ayuda', 'necesito',
            'quiero', 'quisiera', 'sabes', 'hola'];

        $palabras = collect(preg_split('/\s+/', $consulta))
            ->map(fn ($w) => Str::ascii(mb_strtolower(trim($w, "¿?¡!.,"))))
            ->filter(fn ($w) => $w !== '' && !in_array($w, $stopwords, true))
            ->values();

        if ($palabras->isEmpty()) {
            $palabras = collect([Str::ascii(mb_strtolower($consulta))])->filter();
        }

        $umbral = max(1, (int) ceil($palabras->count() * 0.6));

        $resultados = IaConocimiento::vigente($alidoId)
            ->get(['titulo', 'contenido', 'categoria', 'vigente_desde'])
            ->map(function ($c) use ($palabras) {
                $texto = Str::ascii(mb_strtolower($c->titulo . ' ' . $c->contenido));
                $matchCount = $palabras->filter(fn ($p) => str_contains($texto, $p))->count();
                return ['modelo' => $c, 'match_count' => $matchCount];
            })
            ->filter(fn ($item) => $item['match_count'] >= $umbral)
            ->sortByDesc('match_count')
            ->take(5)
            ->pluck('modelo')
            ->values();

        if ($resultados->isEmpty()) {
            return [
                'encontrado' => false,
                'mensaje'    => 'No hay conocimiento aprobado sobre este tema todavía. Dile al usuario que no tienes certeza y que puedes registrar la pregunta para el entrenador.',
            ];
        }

        return ['encontrado' => true, 'resultados' => $resultados];
    }
}
