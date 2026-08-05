<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use OpenSoutheners\LaravelDataMapper\Contracts\MappableObject;
use OpenSoutheners\LaravelDataMapper\MappingValue;

final class MappableObjectMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return is_a($mappingValue->objectClass, MappableObject::class, true);
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return app($mappingValue->objectClass)->mappingFrom($mappingValue);
    }
}
