<?php

namespace OpenSoutheners\LaravelDataMapper\Mappers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenSoutheners\LaravelDataMapper\MappingValue;
use stdClass;

use function OpenSoutheners\ExtendedPhp\Strings\is_json_structure;

class GenericObjectDataMapper extends DataMapper
{
    public function supports(MappingValue $mappingValue): bool
    {
        if ($mappingValue->objectClass !== stdClass::class) {
            return false;
        }

        return is_json_structure($mappingValue->data)
            || (is_array($mappingValue->data) && Arr::isAssoc($mappingValue->data))
            || $mappingValue->data instanceof Collection
            || (is_array($mappingValue->data[0] ?? null) && Arr::isAssoc($mappingValue->data[0]));
    }

    public function resolve(MappingValue $mappingValue): mixed
    {
        return $mappingValue->data instanceof Collection
            ? $mappingValue->data->map(fn ($item) => $this->newObjectInstance($item))
            : $this->newObjectInstance($mappingValue->data);
    }

    protected function newObjectInstance(mixed $data): stdClass
    {
        return is_array($data) ? (object) $data : json_decode($data);
    }
}
