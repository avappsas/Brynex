<?php

namespace App\Models;

use App\Models\Relations\BelongsToDelAliado;
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

    /**
     * belongsTo por una llave que se repite entre aliados (la cédula, típicamente):
     * el aliado entra en la relación tanto en carga perezosa como en eager loading.
     *
     * Ver App\Models\Relations\BelongsToDelAliado y la regla de multi-tenancy sin
     * global scope del CLAUDE.md.
     */
    protected function belongsToDelAliado(
        string $related,
        string $foreignKey,
        string $ownerKey,
        string $relation,
        string $aliadoColumn = 'aliado_id'
    ): BelongsToDelAliado {
        $instance = $this->newRelatedInstance($related);

        return new BelongsToDelAliado(
            $instance->newQuery(), $this, $foreignKey, $ownerKey, $relation, $aliadoColumn
        );
    }
}
