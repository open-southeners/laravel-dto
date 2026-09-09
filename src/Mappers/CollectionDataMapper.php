<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\MappingValue;

use function OpenSoutheners\ExtendedPhp\Strings\is_json_structure;
use function OpenSoutheners\LaravelDataMapper\map;

class CollectionDataMapper extends DataMapper
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

        // Falsy items (including `null`) are dropped rather than mapped: there is
        // no sensible target instance for an empty slot, and callers filtering
        // "holes" out of a payload (e.g. `[1, null, 2]`) expect them gone rather
        // than surfaced as an error.
        $collection = $collection->filter();

        if ($mappingValue->objectClass && $mappingValue->objectClass !== Collection::class) {
            // Each item is mapped on its own through `map()->to()` instead of
            // re-dispatching the whole wrapped collection to a single mapper
            // call. This lets item targets that only understand one value at a
            // time (DTOs/POPOs via ObjectDataMapper) participate, and lets items
            // that are already instances of the target short-circuit via
            // instance passthrough instead of being destructured and
            // re-hydrated.
            $collection = $collection->map(
                fn ($item, $key) => map($item)
                    ->withContext($mappingValue->property, $mappingValue->path !== null ? $mappingValue->path.'.'.$key : null)
                    ->to($mappingValue->objectClass)
            );
        }

        return $mappingValue->collectClass === 'array'
            ? $collection->all()
            : $collection;
    }
}
