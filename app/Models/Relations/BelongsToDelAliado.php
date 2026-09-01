<?php

namespace App\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * belongsTo cuya llave real es (llave, aliado_id) y no solo la llave.
 *
 * La cédula se repite entre aliados: la misma persona puede existir como
 * cliente en varios. Un belongsTo normal al que se le agrega
 * `->where('aliado_id', $this->aliado_id)` dentro del método de la relación
 * solo acierta en carga perezosa. En eager loading Laravel arma la relación
 * sobre una instancia vacía del modelo (`Relation::noConstraints`), así que ahí
 * no hay `aliado_id` que leer: el where se cae — o peor, se aplica el aliado de
 * la sesión, que no tiene por qué ser el del registro — y el emparejamiento se
 * hace solo por cédula, devolviendo el registro de cualquier aliado.
 *
 * Esta relación mete el aliado en los tres puntos donde importa:
 *   - carga perezosa   → where con el aliado del propio padre
 *   - eager loading    → whereIn con los aliados de los padres cargados, y
 *                        emparejamiento por (llave, aliado)
 *   - whereHas/has     → whereColumn correlacionado contra la tabla del padre
 */
class BelongsToDelAliado extends BelongsTo
{
    /** Columna del aliado, en ambas tablas (padre y relacionada). */
    protected string $aliadoColumn;

    public function __construct(Builder $query, Model $child, $foreignKey, $ownerKey, $relationName, string $aliadoColumn = 'aliado_id')
    {
        $this->aliadoColumn = $aliadoColumn;

        parent::__construct($query, $child, $foreignKey, $ownerKey, $relationName);
    }

    /** Carga perezosa: $contrato->cliente */
    public function addConstraints()
    {
        parent::addConstraints();

        if (static::$constraints && ($aliadoId = $this->aliadoDe($this->child)) !== null) {
            $this->query->where($this->related->qualifyColumn($this->aliadoColumn), $aliadoId);
        }
    }

    /** Eager loading: with('cliente') / load('cliente') */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $aliados = [];
        foreach ($models as $model) {
            if (($aliadoId = $this->aliadoDe($model)) !== null) {
                $aliados[(string) $aliadoId] = $aliadoId;
            }
        }

        // Sin aliado no se puede saber a quién pertenece el registro: se prefiere
        // no traer nada antes que traer el de otro.
        $this->query->whereIn(
            $this->related->qualifyColumn($this->aliadoColumn),
            array_values($aliados)
        );
    }

    /**
     * El emparejamiento también tiene que ser por (llave, aliado): si no, con dos
     * resultados de la misma cédula el diccionario deja solo el último y se lo
     * asigna a todos los padres.
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $llave = $this->llave(
                $this->getRelatedKeyFrom($result),
                $result->getAttribute($this->aliadoColumn)
            );

            $dictionary[$llave] = $result;
        }

        foreach ($models as $model) {
            $llave = $this->llave(
                $this->getForeignKeyFrom($model),
                $this->aliadoDe($model)
            );

            if (isset($dictionary[$llave])) {
                $model->setRelation($relation, $dictionary[$llave]);
            }
        }

        return $models;
    }

    /** whereHas('cliente') / has('cliente') */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->whereColumn(
                $query->qualifyColumn($this->aliadoColumn),
                '=',
                $this->child->qualifyColumn($this->aliadoColumn)
            );
    }

    /**
     * `with('cliente:id,cedula,...')` reemplaza el select por una lista fija. Si
     * el aliado no va en esa lista, el emparejamiento de arriba se queda sin con
     * qué comparar, así que se agregan las columnas de la llave.
     */
    public function getEager()
    {
        $this->agregaColumnasDeLaLlave();

        return parent::getEager();
    }

    protected function agregaColumnasDeLaLlave(): void
    {
        $columnas = $this->query->getQuery()->columns;

        if (empty($columnas)) {
            return; // select * : ya vienen todas
        }

        foreach ([$this->ownerKey, $this->aliadoColumn] as $columna) {
            if (! $this->estaSeleccionada($columnas, $columna)) {
                $this->query->addSelect($this->related->qualifyColumn($columna));
            }
        }
    }

    protected function estaSeleccionada(array $columnas, string $columna): bool
    {
        $tabla = $this->related->getTable();

        foreach ($columnas as $seleccionada) {
            if (! is_string($seleccionada)) {
                continue; // expresiones crudas: no se pueden inspeccionar
            }

            $nombre = strtolower(trim(preg_replace('/\s+as\s+.*$/i', '', $seleccionada)));

            if (in_array($nombre, ['*', $tabla.'.*', strtolower($columna), strtolower($tabla.'.'.$columna)], true)) {
                return true;
            }
        }

        return false;
    }

    /** El aliado del modelo, con la sesión como último recurso (registros nuevos). */
    protected function aliadoDe(?Model $model)
    {
        return $model?->getAttribute($this->aliadoColumn) ?? session('aliado_id_activo');
    }

    /**
     * sqlsrv devuelve los enteros como string, así que la llave se arma siempre
     * con strings para que "1" y 1 caigan en la misma casilla.
     */
    protected function llave($valor, $aliadoId): string
    {
        return $this->getDictionaryKey($valor).'|'.$this->getDictionaryKey($aliadoId);
    }
}
