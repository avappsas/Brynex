<?php

namespace App\Services\Banco;

use InvalidArgumentException;

/**
 * Entrega el adaptador de banco que toque usar.
 *
 * Mismo patrón que IaProviderFactory: el nombre sale de la configuración, no
 * del código que lo llama, para que cambiar de adaptador sea una variable de
 * entorno y no una búsqueda por todo el repo.
 */
class BancoApiFactory
{
    public static function make(?string $proveedor = null): BancoApiInterface
    {
        $proveedor ??= (string) config('banco.proveedor', 'fake');
        $clase = config("banco.proveedores.$proveedor");

        if (! $clase || ! class_exists($clase)) {
            throw new InvalidArgumentException(
                "No hay adaptador de banco registrado para '$proveedor'. Revisa config/banco.php."
            );
        }

        return app($clase);
    }
}
