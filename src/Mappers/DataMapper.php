<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use OpenSoutheners\LaravelDataMapper\Events\MappingResolved;
use OpenSoutheners\LaravelDataMapper\MappingValue;

abstract class DataMapper
{
    /**
     * Determine whether this mapper can resolve the given mapping value.
     */
    abstract public function supports(MappingValue $mappingValue): bool;

    /**
     * Resolve mapper that runs once supports returns true.
     */
    abstract public function resolve(MappingValue $mappingValue): mixed;

    public function __invoke(MappingValue $mappingValue): mixed
    {
        event(new MappingResolved(static::class, $mappingValue));

        return $this->resolve($mappingValue);
    }
}
