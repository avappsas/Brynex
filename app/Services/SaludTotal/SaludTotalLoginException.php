<?php

namespace App\Services\SaludTotal;

use RuntimeException;

/** El portal de Salud Total rechazó el usuario o la clave: no se debe reintentar con la misma. */
class SaludTotalLoginException extends RuntimeException
{
}
