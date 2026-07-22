<?php

namespace App\Services\Ia\Tools;

use App\Models\IaConocimiento;
use App\Services\Ia\Providers\ClaudeProvider;
use Illuminate\Support\Str;

/**
 * Busca en internet cuando buscar_conocimiento no tiene la respuesta. Solo disponible
 * con el proveedor Claude (usa su server tool nativo de búsqueda web). El resultado se
 * guarda como conocimiento PENDIENTE de aprobación: el entrenador decide si queda
 * aprobado y disponible para futuras respuestas, o si se descarta.
 */
class BuscarInternetTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'buscar_internet';
    }

    public function descripcion(): string
    {
        return 'Busca en internet información actualizada (normativa de seguridad social, salario mínimo vigente, etc.) '
            . 'cuando buscar_conocimiento no encontró nada. Úsala antes de recurrir a preguntar_entrenador. '
            . 'Lo que encuentres aquí es informativo y aún no está verificado por el entrenador: acláraselo al usuario.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'consulta' => ['type' => 'string', 'description' => 'Qué buscar en internet, en pocas palabras clave.'],
            ],
            'required' => ['consulta'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        if (($contexto['proveedor'] ?? null) !== 'claude' || empty($contexto['api_key'])) {
            return ['error' => 'La búsqueda en internet no está disponible en este momento.'];
        }

        $consulta = $input['consulta'] ?? '';

        try {
            $provider = new ClaudeProvider();
            $resultado = $provider->buscarWeb($contexto['api_key'], $contexto['modelo'], $consulta);
        } catch (\Exception $e) {
            return ['error' => 'No pude completar la búsqueda en internet en este momento.'];
        }

        IaConocimiento::create([
            'aliado_id'     => null, // conocimiento de internet se revisa como global; el entrenador puede acotarlo a un aliado al aprobar
            'titulo'        => Str::limit($consulta, 200, ''),
            'contenido'     => $resultado['texto'],
            'categoria'     => 'internet',
            'fuente'        => 'internet',
            'estado'        => 'pendiente',
            'vigente_desde' => now()->toDateString(),
        ]);

        return [
            'texto' => $resultado['texto'],
            'nota'  => 'Esta información viene de una búsqueda en internet y quedó pendiente de aprobación del '
                . 'entrenador. Puedes usarla para responder ahora, pero acláraselo al usuario (por ejemplo: '
                . '"según lo que encontré en internet, aún sin confirmar").',
        ];
    }
}
