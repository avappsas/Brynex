<?php

namespace App\Services\Ia\Tools;

use App\Models\IaConocimiento;

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

        // Buscar por PALABRAS (AND), no por la frase completa: un cliente real nunca escribe
        // la consulta con el mismo orden/forma exacta del texto guardado (ej. "nivel de riesgo
        // arl" vs. contenido que dice "...ARL... el nivel de riesgo..."). Exigir cada palabra
        // por separado (en título O contenido) es mucho más tolerante que un LIKE de la frase
        // entera. COLLATE ...CI_AI (accent-insensitive) porque la collation por defecto de la
        // BD es CI_AS (sensible a tildes) y "compensacion" no encontraría "compensación".
        $stopwords = ['de', 'la', 'el', 'los', 'las', 'que', 'es', 'y', 'a', 'en', 'del', 'un',
            'una', 'para', 'con', 'se', 'su', 'lo', 'al', 'mi', 'como', 'qué', 'cómo', 'soy'];

        $palabras = collect(preg_split('/\s+/', $consulta))
            ->map(fn ($w) => trim($w, "¿?¡!.,"))
            ->filter(fn ($w) => $w !== '' && !in_array(mb_strtolower($w), $stopwords, true))
            ->values();

        if ($palabras->isEmpty()) {
            $palabras = collect([$consulta])->filter();
        }

        $query = IaConocimiento::vigente($alidoId);
        foreach ($palabras as $palabra) {
            $like = '%' . $palabra . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('titulo COLLATE Modern_Spanish_CI_AI LIKE ?', [$like])
                  ->orWhereRaw('contenido COLLATE Modern_Spanish_CI_AI LIKE ?', [$like]);
            });
        }

        $resultados = $query
            ->orderByDesc('vigente_desde')
            ->limit(5)
            ->get(['titulo', 'contenido', 'categoria', 'vigente_desde']);

        if ($resultados->isEmpty()) {
            return [
                'encontrado' => false,
                'mensaje'    => 'No hay conocimiento aprobado sobre este tema todavía. Dile al usuario que no tienes certeza y que puedes registrar la pregunta para el entrenador.',
            ];
        }

        return ['encontrado' => true, 'resultados' => $resultados];
    }
}
