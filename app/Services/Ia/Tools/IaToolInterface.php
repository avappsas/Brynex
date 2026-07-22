<?php

namespace App\Services\Ia\Tools;

interface IaToolInterface
{
    public function nombre(): string;

    public function descripcion(): string;

    /** JSON Schema de los parámetros de entrada. */
    public function schema(): array;

    /**
     * @param array $input Argumentos que envió el modelo (ya decodificados).
     * @param array $contexto ['aliado_id' => int, 'user' => \App\Models\User, 'canal' => 'web'|'whatsapp']
     * @return array Resultado que se serializa a JSON y se devuelve al modelo.
     */
    public function ejecutar(array $input, array $contexto): array;
}
