<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\MappingValue;

use function OpenSoutheners\ExtendedPhp\Strings\is_json_structure;
use function OpenSoutheners\LaravelDataMapper\map;

final class CollectionDataMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        if (is_a($mappingValue->objectClass, Collection::class, true)) {
            return true;
        }

        // Model targets fully resolve string/array/collection input (and any
        // requested collectClass wrapping) themselves, they never need this
        // mapper's wrapping step.
        if (is_a($mappingValue->objectClass, Model::class, true)) {
            return false;
        }

        if ($mappingValue->collectClass !== 'array' && $mappingValue->collectClass !== Collection::class) {
            return false;
        }

        // Data already wrapped into a collection has nothing left for this
        // mapper to do; deciding otherwise would recurse into itself forever.
        if ($mappingValue->data instanceof Collection) {
            return false;
        }

        return (is_array($mappingValue->data) && ! Arr::isAssoc($mappingValue->data))
            || (is_string($mappingValue->data) && str_contains($mappingValue->data, ','))
            || (is_string($mappingValue->data) && is_json_structure($mappingValue->data) && str_starts_with(ltrim($mappingValue->data), '['));
    }

    /**
     * Resolve mapper that runs once supports returns true.
     */
    public function resolve(MappingValue $mappingValue): mixed
    {
        if ($mappingValue->objectClass === EloquentCollection::class) {
            return $mappingValue->data->toBase();
        }

        $collection = match (true) {
            is_string($mappingValue->data) && is_json_structure($mappingValue->data) => Collection::make(json_decode($mappingValue->data, true)),
            is_string($mappingValue->data) => Collection::make(explode(',', $mappingValue->data)),
            default => Collection::make($mappingValue->data),
        };

        $collection = $collection->filter();

        if ($mappingValue->objectClass && $mappingValue->objectClass !== Collection::class) {
            $collection = map($collection)
                ->withContext($mappingValue->property, $mappingValue->path)
                ->to($mappingValue->objectClass);
        }

        return $mappingValue->collectClass === 'array'
            ? $collection->all()
            : $collection;
    }
}
