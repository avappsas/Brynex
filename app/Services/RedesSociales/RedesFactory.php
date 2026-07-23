<?php

namespace App\Services\RedesSociales;

use App\Models\RedSocialConfig;

class RedesFactory
{
    /** Construye el publicador correcto según config->red. Agregar redes nuevas aquí. */
    public static function make(RedSocialConfig $config): PublicadorRed
    {
        return match ($config->red) {
            RedSocialConfig::FACEBOOK, RedSocialConfig::INSTAGRAM => new MetaGraphPublicador($config),
            default => throw new \InvalidArgumentException("No hay publicador registrado para la red '{$config->red}'."),
        };
    }
}
