<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Teléfonos de los deudores del dueño del módulo de finanzas.
 *
 * Sirve para una regla de privacidad: las conversaciones de WhatsApp con gente
 * a la que el dueño le prestó plata no deben aparecerle a los demás usuarios.
 *
 * Existía repartido en cuatro sitios, cada uno resolviendo lo mismo a mano y
 * consultando la base en cada llamada. El badge de no leídos sondea cada 30
 * segundos por pestaña abierta, así que eran dos consultas extra por sondeo
 * —una a `users` por cédula, otra a `finanzas_prestamos` en la conexión de
 * finanzas, que es otra base de datos— para leer una lista que casi nunca
 * cambia.
 */
class TelefonosDeudores
{
    private const LLAVE_ID     = 'finanzas:dueno_id';
    private const LLAVE_LISTA  = 'finanzas:telefonos_deudores';

    /**
     * Id del usuario dueño de finanzas, o null si no existe.
     *
     * Devolver null es un caso legítimo, no un error: en un entorno donde ese
     * usuario no esté creado, el filtro simplemente no aplica.
     */
    public static function duenoId(): ?int
    {
        return Cache::remember(self::LLAVE_ID, self::ttl(), function () {
            $cedula = config('finanzas.cedula_dueno');

            return $cedula
                ? User::where('cedula', $cedula)->value('id')
                : null;
        });
    }

    /**
     * Últimos 10 dígitos de cada teléfono de deudor, ya normalizados.
     *
     * Se guardan recortados porque así es como se comparan contra
     * `wa_contact_id`, que trae el número con indicativo. Normalizar aquí evita
     * que cada sitio lo vuelva a hacer y se desincronicen los criterios.
     */
    public static function ultimos10(): array
    {
        return Cache::remember(self::LLAVE_LISTA, self::ttl(), function () {
            $duenoId = self::duenoId();
            if (!$duenoId) {
                return [];
            }

            return Prestamo::where('user_id', $duenoId)
                ->pluck('telefono_deudor')
                ->filter()
                ->map(fn ($tel) => substr(preg_replace('/[^0-9]/', '', (string) $tel), -10))
                ->filter(fn ($tel) => strlen($tel) === 10)
                ->unique()
                ->values()
                ->toArray();
        });
    }

    /**
     * ¿Este número es de un deudor del dueño?
     */
    public static function esDeudor(?string $numero): bool
    {
        if (!$numero) {
            return false;
        }

        $ultimos10 = substr(preg_replace('/[^0-9]/', '', $numero), -10);

        return strlen($ultimos10) === 10
            && in_array($ultimos10, self::ultimos10(), true);
    }

    /**
     * Invalida la caché. Llamar al crear, editar o borrar un préstamo, o el
     * cambio tarda hasta `finanzas.cache_deudores_segundos` en verse.
     */
    public static function olvidar(): void
    {
        Cache::forget(self::LLAVE_ID);
        Cache::forget(self::LLAVE_LISTA);
    }

    private static function ttl(): int
    {
        return (int) config('finanzas.cache_deudores_segundos', 300);
    }
}
