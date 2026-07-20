<?php

namespace App\Http\Controllers\Finanzas\Concerns;

use App\Services\Finanzas\FinanzasAlertaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Trait InvalidaFinanzasCache
 *
 * Proporciona el método `invalidarCacheFinanzas()` para usar en todos los
 * controladores de escritura del módulo Finanzas. Elimina las claves de
 * caché del dashboard (resumen, evolución, consolidado y cuentas) de modo
 * que la siguiente visita al dashboard recalcula los datos frescos.
 */
trait InvalidaFinanzasCache
{
    /**
     * Invalida la caché del dashboard de Finanzas para el usuario autenticado.
     *
     * @param int|null  $anio  Año del dato modificado (opcional, mejora la precisión de invalidación)
     * @param int|null  $mes   Mes del dato modificado (opcional)
     */
    protected function invalidarCacheFinanzas(?int $anio = null, ?int $mes = null): void
    {
        /** @var FinanzasAlertaService $alertaService */
        $alertaService = app(FinanzasAlertaService::class);
        $alertaService->invalidarCacheUsuario(Auth::id(), $anio, $mes);
    }
}
