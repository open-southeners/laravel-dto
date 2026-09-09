<?php

namespace OpenSoutheners\LaravelDataMapper\Contracts;

use OpenSoutheners\LaravelDataMapper\MappingValue;

interface MappableObject
{
    /**
     * Build and return the mapping result for the given mapping value.
     */
    public function mappingFrom(MappingValue $mappingValue): mixed;
}
