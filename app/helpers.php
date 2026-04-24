<?php

use Carbon\Carbon;

if (!function_exists('sqldate')) {
    /**
     * Parsea una fecha de SQL Server ("Apr 1 2026 12:00:00:AM") de forma segura.
     *
     * @param  mixed       $value   Fecha (string, Carbon, null)
     * @param  string|null $format  Si se da, devuelve string formateado
     * @return Carbon|string|null
     */
    function sqldate($value, ?string $format = null)
    {
        if (!$value) return null;
        if ($value instanceof Carbon) {
            return $format ? $value->format($format) : $value;
        }
        $normalized = preg_replace('/:(AM|PM)$/i', ' $1', trim((string) $value));
        try {
            $carbon = Carbon::parse($normalized);
            return $format ? $carbon->format($format) : $carbon;
        } catch (\Exception $e) {
            return null;
        }
    }
}
