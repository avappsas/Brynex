<?php

namespace App\Services\Ia\Tools;

use App\Services\Ia\CatalogoModulos;
use Illuminate\Support\Facades\Route as RouteFacade;

class CatalogoModulosTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'catalogo_modulos';
    }

    public function descripcion(): string
    {
        return 'Busca en el catálogo de páginas/módulos del sistema para indicar DÓNDE queda algo '
            . '(ej: "¿dónde reviso las facturas anuladas?"). Devuelve el nombre, descripción y una URL '
            . 'que se puede ofrecer para abrir la página.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'consulta' => ['type' => 'string', 'description' => 'Palabras clave de lo que el usuario busca (ej: "facturas anuladas", "préstamos").'],
            ],
            'required' => ['consulta'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $resultados = CatalogoModulos::buscar($input['consulta'] ?? '');

        $resultados = array_map(function ($item) {
            $item['url'] = RouteFacade::has($item['ruta']) ? route($item['ruta']) : null;
            return $item;
        }, $resultados);

        if (empty($resultados)) {
            return ['error' => 'No encontré ningún módulo relacionado.', 'sugerencia' => 'Pide al usuario más detalle o revisa el menú principal.'];
        }

        return ['resultados' => $resultados];
    }
}
