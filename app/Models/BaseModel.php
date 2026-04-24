<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * BaseModel — todos los modelos de BryNex extienden esta clase.
 * Normaliza fechas de SQL Server: "Apr 1 2026 12:00:00:AM" → Carbon.
 */
class BaseModel extends Model
{
    public function asDateTime($value): ?Carbon
    {
        if (is_null($value) || $value === '') return null;
        if ($value instanceof Carbon) return $value;
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value);
        if (is_numeric($value)) return Carbon::createFromTimestamp($value);
        $normalized = preg_replace('/:(AM|PM)$/i', ' $1', trim((string) $value));
        try { return Carbon::parse($normalized); } catch (\Exception $e) { return null; }
    }

    protected function asDate($value): ?Carbon
    {
        return $this->asDateTime($value)?->startOfDay();
    }
}
