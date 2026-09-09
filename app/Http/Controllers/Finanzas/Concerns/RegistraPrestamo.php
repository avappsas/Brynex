<?php

namespace App\Http\Controllers\Finanzas\Concerns;

use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Prestamo;
use App\Services\Finanzas\PrestamoLiquidacionService;
use Illuminate\Support\Facades\Auth;

/**
 * Alta de un préstamo con su desembolso: crea la ficha, el movimiento inicial
 * y el egreso que descuenta la cuenta de donde salió la plata.
 *
 * Lo comparten el formulario de préstamo suelto y el desembolso de un
 * expediente formal, que llegan por caminos distintos al mismo hecho: se
 * entregó el dinero.
 */
trait RegistraPrestamo
{
    use InvalidaFinanzasCache;
    use ResuelveCuenta;

    /**
     * @param  array  $datos  Campos del préstamo (los mismos de `Prestamo::$fillable`).
     * @param  int|null  $cuentaId  Cuenta de la que sale el dinero.
     */
    protected function crearPrestamoConDesembolso(array $datos, $cuentaId, ?string $descripcionGasto = null): Prestamo
    {
        $userId = Auth::id();

        $prestamo = Prestamo::create($datos + [
            'user_id' => $userId,
            'estado' => 'activo',
            'es_cuenta_corriente' => false,
        ]);

        app(PrestamoLiquidacionService::class)->registrarDesembolso($prestamo);

        Gasto::create([
            'user_id' => $userId,
            'categoria_id' => $this->categoriaOtros($userId),
            'cuenta_id' => $this->resolverCuenta($cuentaId),
            'fecha' => $prestamo->fecha_desembolso,
            'monto' => $prestamo->monto_original,
            'descripcion' => $descripcionGasto ?: "Préstamo otorgado a: {$prestamo->nombre_deudor}",
            'tipo_movimiento' => 'prestamo',
            'es_patrimonio' => false,
            'patrimonio_id' => null,
        ]);

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($prestamo->fecha_desembolso)),
            (int) date('n', strtotime($prestamo->fecha_desembolso))
        );

        return $prestamo;
    }

    /**
     * Categoría contenedora de los egresos que no tienen una propia.
     */
    protected function categoriaOtros(int $userId): int
    {
        $categoria = CategoriaGasto::where('user_id', $userId)->where('nombre', 'Otros')->first();

        if (! $categoria) {
            $categoria = CategoriaGasto::create([
                'user_id' => $userId,
                'nombre' => 'Otros',
                'icono' => '📁',
                'orden' => 99,
            ]);
        }

        return $categoria->id;
    }
}
