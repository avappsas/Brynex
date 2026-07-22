<?php

namespace App\Services\Ia;

use App\Services\Ia\Providers\ClaudeProvider;
use App\Services\Ia\Providers\OpenAiProvider;

class IaProviderFactory
{
    public static function make(string $proveedor): IaProviderInterface
    {
        return match ($proveedor) {
            'openai' => new OpenAiProvider(),
            default  => new ClaudeProvider(),
        };
    }
}
