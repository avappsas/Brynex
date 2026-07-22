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
        $consulta = $input['consulta'] ?? '';
        $alidoId  = $contexto['aliado_id'] ?? null;

        $resultados = IaConocimiento::vigente($alidoId)
            ->where(function ($q) use ($consulta) {
                $q->where('titulo', 'like', "%{$consulta}%")
                  ->orWhere('contenido', 'like', "%{$consulta}%");
            })
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
