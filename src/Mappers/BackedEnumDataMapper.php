<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use BackedEnum;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\MappingValue;

class BackedEnumDataMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        return is_subclass_of($mappingValue->objectClass, BackedEnum::class)
            && (is_string($mappingValue->data) || is_int($mappingValue->data) || $mappingValue->data instanceof Collection);
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return $mappingValue->data instanceof Collection
            ? $mappingValue->data->mapInto($mappingValue->objectClass)
            : $mappingValue->objectClass::tryFrom($mappingValue->data);
    }
}
